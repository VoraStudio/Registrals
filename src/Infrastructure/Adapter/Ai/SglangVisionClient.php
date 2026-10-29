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

        $isPdf = ($mimeType === 'application/pdf' || str_starts_with($fileContent, '%PDF-'));

        if ($isPdf) {
            $extractedText = '';
            try {
                $parser = $this->createPdfParser();
                $pdf = $parser->parseContent($fileContent);
                $extractedText = trim($pdf->getText());
            } catch (\Throwable $e) {
                $this->logger->warning('Error extraient text del PDF amb PdfParser', ['error' => $e->getMessage()]);
            }

            // Si el parser estàndard no ha obtingut text (possible PDF xifrat o amb estructura complexa), provem extracció raw
            if ($extractedText === '' || mb_strlen($extractedText) < 20) {
                $rawText = $this->extractRawTextFallback($fileContent);
                if (mb_strlen($rawText) >= 30) {
                    $extractedText = $rawText;
                }
            }

            // Si el PDF conté capa de text digital útil (mínim 30 caràcters)
            if ($extractedText !== '' && mb_strlen($extractedText) >= 30) {
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
                    'max_tokens' => 3072,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ];
            } else {
                // Si el PDF no té text digitalitzat (escaneig, foto de contracte o document rasteritzat),
                // n'extraiem la imatge de la pàgina per enviar-la a la canonada de visió de Qwen-VL
                $extractedImage = $this->extractImageFromPdf($fileContent);

                if ($extractedImage !== null) {
                    $this->logger->info('PDF sense text digital: s\'ha extret la imatge de la pàgina per enviar a Qwen-VL', [
                        'mime' => $extractedImage['mime'],
                        'size' => $extractedImage['size'],
                        'width' => $extractedImage['width'] ?? null,
                        'height' => $extractedImage['height'] ?? null,
                    ]);

                    $fileContent = $extractedImage['data'];
                    $mimeType = $extractedImage['mime'];
                    $isPdf = false; // Procedim com a imatge visual
                } else {
                    $this->logger->info('El PDF no conté capa de text llegible ni imatges extractables (possible PDF xifrat amb contrasenya o protegit)');
                    return new AiVisionExtractionDto(
                        confidence: 0.0,
                        notes: 'Aquest document PDF té protecció de seguretat o xifratge bancari (Secured PDF). Pots fer servir "Mantenir igualment" per custodiar-lo xifrat com a prova notarial vàlida, o pujar una captura de pantalla del contracte per a lectura automàtica.'
                    );
                }
            }
        }

        if (!$isPdf) {
            // Optimitzem la imatge a una resolució òptima per a inferència visual (màxim 1600px)
            // evitant enviar payloads gegants de 20MB o 24MP que sobrecarreguin la GPU i provoquin timeouts d'inferència a SGLang
            [$optimizedContent, $optimizedMime] = $this->prepareImageForInference($fileContent, $mimeType);

            // Per a imatges (JPEG, PNG, WEBP o PDF escanejat extret), utilitzem la canonada visual Qwen-VL
            $base64Data = base64_encode($optimizedContent);
            $imageUrl = sprintf('data:%s;base64,%s', $optimizedMime, $base64Data);

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
                'max_tokens' => 3072,
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

            // Fallback per a models de raonament (DeepSeek / Qwen-VL reasoning):
            // Si el contingut principal ha quedat buit o el JSON s'ha inserit al buffer de raonament intern
            if ((!$rawContent || trim($rawContent) === '') && !empty($responseData['choices'][0]['message']['reasoning_content'])) {
                $reasoning = $responseData['choices'][0]['message']['reasoning_content'];
                if (preg_match('/\{[\s\S]*"name"[\s\S]*\}/', $reasoning, $matches)) {
                    $rawContent = $matches[0];
                }
            }

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
Ets un assistent notarial d'alta precisió expert en taxació i catalogació visual de béns mobles valuosos (joies, tecnologia, rellotges, bicicletes, vehicles o cotxes, obres d'art).
IMPORTANT: Sigues molt breu en el teu raonament intern i emet immediatament l'objecte JSON requerit.
Has d'analitzar la fotografia de l'objecte i respondre EXCLUSIVAMENT amb un objecte JSON amb les següents claus:
- "name": (string) Títol identificatiu clar i concís de l'objecte (ex: "Audi RS 5 Sportback", "Càmera Nikon D70s amb objectiu AF-S Nikkor 18-70mm" o "Rellotge Rolex Submariner Date").
- "category_slug": (string) Categoria principal ("tecnologia", "joieria-rellotges", "mobilitat-esport", "art-colleccions", "moda-complements", "altres-bens").
- "subcategory_slug": (string o null) Subcategoria segons la categoria:
  * tecnologia: "tecnologia-smartphones-tauletes", "tecnologia-portatils-ordinadors", "tecnologia-fotografia-video", "tecnologia-audio-hifi", "tecnologia-altres"
  * joieria-rellotges: "joieria-rellotges-luxe", "joieria-anells-bracalets-collarets", "joieria-monedes-lingots", "joieria-altres"
  * mobilitat-esport: "mobilitat-bicicletes", "mobilitat-patinets-electrics", "mobilitat-muntanya-esqui", "mobilitat-altres" (inclou vehicles i automoció)
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

    /**
     * Extreu la imatge principal d'un document PDF escanejat o rasteritzat.
     * Retorna array amb 'mime', 'data', 'width', 'height', 'size' o null si no se'n troba cap.
     *
     * @return array{mime: string, data: string, width: int, height: int, size: int}|null
     */
    private function extractImageFromPdf(string $pdfContent): ?array
    {
        $images = [];
        $len = \strlen($pdfContent);

        // 1. Cercar directament streams JPEG (DCTDecode) dins l'estructura del PDF.
        // La immensa majoria d'escàners mòbils i documents PDF escanejats desen la pàgina com a stream JPEG directe.
        $offset = 0;
        while ($offset < $len && ($start = strpos($pdfContent, "\xFF\xD8\xFF", $offset)) !== false) {
            $end = strpos($pdfContent, "\xFF\xD9", $start);
            if ($end !== false) {
                $jpegData = substr($pdfContent, $start, ($end + 2) - $start);
                $size = @getimagesizefromstring($jpegData);
                if ($size !== false && $size[0] >= 100 && $size[1] >= 100) {
                    $images[] = [
                        'mime' => 'image/jpeg',
                        'data' => $jpegData,
                        'width' => $size[0],
                        'height' => $size[1],
                        'size' => \strlen($jpegData),
                    ];
                }
                $offset = $end + 2;
            } else {
                break;
            }
        }

        // 2. Cercar streams PNG (\x89PNG)
        $offset = 0;
        while ($offset < $len && ($start = strpos($pdfContent, "\x89PNG\r\n\x1a\n", $offset)) !== false) {
            $end = strpos($pdfContent, "IEND\xae\x42\x60\x82", $start);
            if ($end !== false) {
                $pngData = substr($pdfContent, $start, ($end + 8) - $start);
                $size = @getimagesizefromstring($pngData);
                if ($size !== false && $size[0] >= 100 && $size[1] >= 100) {
                    $images[] = [
                        'mime' => 'image/png',
                        'data' => $pngData,
                        'width' => $size[0],
                        'height' => $size[1],
                        'size' => \strlen($pngData),
                    ];
                }
                $offset = $end + 8;
            } else {
                break;
            }
        }

        // 3. Fallback: Extracció via Smalot PdfParser XObjects
        if (empty($images)) {
            try {
                $parser = $this->createPdfParser();
                $pdf = $parser->parseContent($pdfContent);
                $xobjects = $pdf->getObjectsByType('XObject', 'Image');
                foreach ($xobjects as $xobj) {
                    $raw = $xobj->getContent();
                    if ($raw) {
                        $size = @getimagesizefromstring($raw);
                        if ($size !== false && $size[0] >= 100 && $size[1] >= 100) {
                            $images[] = [
                                'mime' => $size['mime'] ?? 'image/jpeg',
                                'data' => $raw,
                                'width' => $size[0],
                                'height' => $size[1],
                                'size' => \strlen($raw),
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Error en fallback d\'extracció d\'imatges Smalot', ['error' => $e->getMessage()]);
            }
        }

        if (empty($images)) {
            return null;
        }

        // Ordenem per mida descendent: la imatge de la pàgina principal és la de major resolució/volum
        usort($images, fn($a, $b) => $b['size'] <=> $a['size']);

        return $images[0];
    }

    /**
     * Crea una instància de Parser de Smalot ignorant proteccions de permisos (Secured PDF)
     * i retenint el contingut d'imatges per permetre l'extracció de documents financers i bancaris.
     */
    private function createPdfParser(): \Smalot\PdfParser\Parser
    {
        $config = new \Smalot\PdfParser\Config();
        $config->setIgnoreEncryption(true);
        $config->setRetainImageContent(true);

        return new \Smalot\PdfParser\Parser([], $config);
    }

    /**
     * Fallback per extreure cadenes de text si el parser de PDF no pot desxifrar la taula d'objectes.
     */
    private function extractRawTextFallback(string $pdfContent): string
    {
        $text = '';
        if (preg_match_all('/BT[\s\S]*?ET/m', $pdfContent, $matches)) {
            foreach ($matches[0] as $block) {
                if (preg_match_all('/\((.*?)\)\s*(?:Tj|\'|")/s', $block, $tMatches)) {
                    $text .= implode(' ', $tMatches[1]) . "\n";
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $tMatches)) {
                    foreach ($tMatches[1] as $tj) {
                        if (preg_match_all('/\((.*?)\)/s', $tj, $subMatches)) {
                            $text .= implode('', $subMatches[1]) . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        return trim(strip_tags($text));
    }

    /**
     * Optimitza una imatge a una resolució òptima per a inferència visual de Qwen-VL (màxim 1600px).
     * Evita enviar fitxers massius de 20-50 MB o 8K que sobrecarreguin la memòria de vídeo (VRAM)
     * i disparin timeouts de 60 segons a la xarxa local.
     *
     * @return array{0: string, 1: string} [binaryContent, mimeType]
     */
    private function prepareImageForInference(string $fileContent, string $mimeType): array
    {
        if (!extension_loaded('gd') || !str_starts_with($mimeType, 'image/')) {
            return [$fileContent, $mimeType];
        }

        $image = @imagecreatefromstring($fileContent);
        if ($image === false) {
            return [$fileContent, $mimeType];
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        $maxDimension = 1600;
        if ($origWidth <= $maxDimension && $origHeight <= $maxDimension && strlen($fileContent) <= 1500000) {
            imagedestroy($image);
            return [$fileContent, $mimeType];
        }

        $ratio = min($maxDimension / $origWidth, $maxDimension / $origHeight);
        $newWidth = max(1, (int) round($origWidth * $ratio));
        $newHeight = max(1, (int) round($origHeight * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        ob_start();
        imagejpeg($resized, null, 85);
        $optimizedData = ob_get_clean();

        imagedestroy($image);
        imagedestroy($resized);

        if ($optimizedData !== false && strlen($optimizedData) > 0) {
            $this->logger->info('Imatge optimitzada per a inferència SGLang', [
                'orig_dim' => "{$origWidth}x{$origHeight}",
                'new_dim' => "{$newWidth}x{$newHeight}",
                'orig_size_kb' => round(strlen($fileContent) / 1024),
                'new_size_kb' => round(strlen($optimizedData) / 1024),
            ]);
            return [$optimizedData, 'image/jpeg'];
        }

        return [$fileContent, $mimeType];
    }
}
