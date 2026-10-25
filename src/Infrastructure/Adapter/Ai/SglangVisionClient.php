<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Ai;

use App\DTO\AiVisionExtractionDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adaptador d'inferència de visió per computador i OCR connectat al node local SGLang (Qwen VL).
 */
class SglangVisionClient implements AiVisionAnalyzerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'SGLANG_API_BASE_URL')]
        private readonly string $sglangBaseUrl,
        #[Autowire(env: 'SGLANG_MODEL_NAME')]
        private readonly string $modelName,
        #[Autowire(env: 'int:SGLANG_TIMEOUT_SECONDS')]
        private readonly int $timeoutSeconds = 30,
        #[Autowire(env: 'default::SGLANG_API_KEY')]
        private readonly ?string $apiKey = null,
    ) {
    }

    public function analyzeMedia(string $fileContent, string $mimeType, string $promptType = 'photo'): AiVisionExtractionDto
    {
        $systemPrompt = $this->getSystemPrompt($promptType);
        $endpoint = rtrim($this->sglangBaseUrl, '/') . '/chat/completions';

        // Si és un document PDF, n'extraiem el text i l'enviem com a prompt de text directe (sense passar per Pillow)
        if ($mimeType === 'application/pdf' || str_starts_with($fileContent, '%PDF-')) {
            $extractedText = '';
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseContent($fileContent);
                $extractedText = trim($pdf->getText());
            } catch (\Throwable $e) {
                $this->logger->warning('Error extraient text del PDF amb PdfParser', ['error' => $e->getMessage()]);
            }

            if ($extractedText === '') {
                $this->logger->info('El PDF no conté capa de text llegible (possible escaneig com a imatge pura)');
                return new AiVisionExtractionDto(
                    confidence: 0.0,
                    notes: 'El document PDF no conté text digitalitzat llegible.'
                );
            }

            // Limitar longitud de text per eficiència de tokens
            $extractedText = mb_substr($extractedText, 0, 8000);

            $payload = [
                'model' => $this->modelName,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => "A continuació tens el text extret del document o factura:\n\n" . $extractedText . "\n\nAnalitza aquestes dades i respon amb el JSON especificat.",
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 1024,
                'response_format' => [
                    'type' => 'json_object',
                ],
            ];
        } else {
            // Per a imatges (JPEG, PNG, WEBP), utilitzem la canonada visual Qwen-VL
            $base64Data = base64_encode($fileContent);
            $imageUrl = sprintf('data:%s;base64,%s', $mimeType, $base64Data);

            $payload = [
                'model' => $this->modelName,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $promptType === 'invoice'
                                    ? 'Analitza aquest tiquet, factura o document de compra i extreu les dades en format JSON.'
                                    : 'Analitza aquesta fotografia del bé de valor i extreu les especificacions en format JSON.',
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $imageUrl,
                                ],
                            ],
                        ],
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 1024,
                'response_format' => [
                    'type' => 'json_object',
                ],
            ];
        }

        try {
            $this->logger->info('Enviant petició de visió a SGLang', [
                'endpoint' => $endpoint,
                'model' => $this->modelName,
                'prompt_type' => $promptType,
                'mime_type' => $mimeType,
            ]);

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey && trim($this->apiKey) !== '') {
                $headers['Authorization'] = 'Bearer ' . trim($this->apiKey);
            }

            $response = $this->httpClient->request('POST', $endpoint, [
                'json' => $payload,
                'timeout' => $this->timeoutSeconds,
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('SGLang ha retornat un codi d\'estat no esperat', [
                    'status_code' => $statusCode,
                    'content' => substr($response->getContent(false), 0, 500),
                ]);
                return new AiVisionExtractionDto(
                    confidence: 0.0,
                    notes: 'El servei d\'IA ha retornat un error de resposta HTTP ' . $statusCode
                );
            }

            $responseData = $response->toArray();
            $rawContent = $responseData['choices'][0]['message']['content'] ?? null;

            if (!$rawContent || !is_string($rawContent)) {
                $this->logger->warning('Resposta d\'SGLang buida o malformada', ['response' => $responseData]);
                return new AiVisionExtractionDto(confidence: 0.0, notes: 'Sense contingut generat per la IA');
            }

            // Netejar possibles blocs de codi markdown ```json ... ```
            $cleanJson = trim($rawContent);
            if (str_starts_with($cleanJson, '```json')) {
                $cleanJson = trim(substr($cleanJson, 7));
            }
            if (str_starts_with($cleanJson, '```')) {
                $cleanJson = trim(substr($cleanJson, 3));
            }
            if (str_ends_with($cleanJson, '```')) {
                $cleanJson = trim(substr($cleanJson, 0, -3));
            }

            $decoded = json_decode($cleanJson, true);
            if (!is_array($decoded)) {
                $this->logger->warning('El contingut d\'SGLang no és un JSON vàlid', ['raw' => $rawContent]);
                return new AiVisionExtractionDto(confidence: 0.0, notes: 'JSON invàlid retornat pel model');
            }

            $this->logger->info('Anàlisi SGLang completada amb èxit', ['extracted' => $decoded]);

            return AiVisionExtractionDto::fromArray($decoded, $responseData);

        } catch (\Throwable $e) {
            $this->logger->error('Error de comunicació amb el node SGLang', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            return new AiVisionExtractionDto(
                confidence: 0.0,
                notes: 'Node d\'inferència SGLang no disponible: ' . $e->getMessage()
            );
        }
    }

    private function getSystemPrompt(string $promptType): string
    {
        if ($promptType === 'invoice') {
            return <<<PROMPT
Ets un assistent notarial d'alta precisió encarregat d'analitzar factures, tiquets de compra i documents de garantia de béns mobles.
Has d'extreure estrictament les dades fiscals i identificadores i respondre EXCLUSIVAMENT amb un objecte JSON amb les següents claus:
- "name": (string o null) Nom complet del producte o bé comprat.
- "category_slug": (string o null) Categoria principal ("tecnologia", "joieria-rellotges", "mobilitat-esport", "art-colleccions", "moda-complements", "altres-bens").
- "subcategory_slug": (string o null) Subcategoria segons la categoria:
  * tecnologia: "tecnologia-smartphones-tauletes", "tecnologia-portatils-ordinadors", "tecnologia-fotografia-video", "tecnologia-audio-hifi", "tecnologia-altres"
  * joieria-rellotges: "joieria-rellotges-luxe", "joieria-anells-bracalets-collarets", "joieria-monedes-lingots", "joieria-altres"
  * mobilitat-esport: "mobilitat-bicicletes", "mobilitat-patinets-electrics", "mobilitat-muntanya-esqui", "mobilitat-altres"
  * art-colleccions: "art-pintura-escultura", "art-colleccionisme-segells-cartes", "art-instruments-musicals", "art-altres"
  * moda-complements: "moda-bosses-marroquineria", "moda-sneakers-limitades", "moda-altres"
  * altres-bens: "altres-general"
- "brand": (string o null) Marca del producte.
- "model": (string o null) Model del producte.
- "serial_number": (string o null) Número de sèrie o IMEI si apareix imprès a la factura.
- "declared_value_euros": (float o null) Import total final pagat en euros (sense símbol de moneda).
- "subtotal_euros": (float o null) Base imposable o subtotal abans d'impostos.
- "tax_amount_euros": (float o null) Import de l'IVA o impostos.
- "tax_rate_percent": (float o null) Tipus impositiu aplicat si consta (ex: 21.0, 10.0, 4.0).
- "is_arithmetic_valid": (boolean o null) True si el subtotal + impostos és igual al total declarat; false si no quadren les matemàtiques del document.
- "math_explanation": (string o null) Breu explicació aritmètica si hi ha desquadrament o confirmació de càlcul.
- "acquisition_date": (string o null) Data de compra en format "YYYY-MM-DD".
- "seller_name": (string o null) Raó social o nom comercial de la botiga o venedor.
- "tax_id": (string o null) CIF, NIF o número d'identificació fiscal del venedor.
- "confidence": (float) Nivell de certesa entre 0.0 i 1.0.
- "notes": (string o null) Observacions rellevants d'autenticitat o format del document.
No afegeixis text explicatiu fora del JSON.
PROMPT;
        }

        return <<<PROMPT
Ets un assistent notarial d'alta precisió expert en taxació i catalogació visual de béns mobles valuosos (joies, tecnologia, rellotges, bicicletes, obres d'art).
Has d'analitzar la fotografia de l'objecte i respondre EXCLUSIVAMENT amb un objecte JSON amb les següents claus:
- "name": (string) Títol identificatiu clar i concís de l'objecte (ex: "Càmera Nikon D70s amb objectiu AF-S Nikkor 18-70mm" o "Rellotge Rolex Submariner Date").
- "category_slug": (string) Categoria principal ("tecnologia", "joieria-rellotges", "mobilitat-esport", "art-colleccions", "moda-complements", "altres-bens").
- "subcategory_slug": (string o null) Subcategoria segons la categoria:
  * tecnologia: "tecnologia-smartphones-tauletes", "tecnologia-portatils-ordinadors", "tecnologia-fotografia-video", "tecnologia-audio-hifi", "tecnologia-altres"
  * joieria-rellotges: "joieria-rellotges-luxe", "joieria-anells-bracalets-collarets", "joieria-monedes-lingots", "joieria-altres"
  * mobilitat-esport: "mobilitat-bicicletes", "mobilitat-patinets-electrics", "mobilitat-muntanya-esqui", "mobilitat-altres"
  * art-colleccions: "art-pintura-escultura", "art-colleccionisme-segells-cartes", "art-instruments-musicals", "art-altres"
  * moda-complements: "moda-bosses-marroquineria", "moda-sneakers-limitades", "moda-altres"
  * altres-bens: "altres-general"
- "brand": (string o null) Marca o fabricant detectat.
- "model": (string o null) Model específic o línia del producte.
- "serial_number": (string o null) Número de sèrie, contrast o gravat si és visible.
- "declared_value_euros": (float o null) Valor de mercat estimat orientatiu en euros (sense símbol de moneda).
- "confidence": (float) Nivell de certesa entre 0.0 i 1.0.
- "notes": (string o null) Descripció de colors, materials i estat visual de l'objecte.
No afegeixis text explicatiu fora del JSON.
PROMPT;
    }
}
