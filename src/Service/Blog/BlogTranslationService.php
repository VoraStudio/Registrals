<?php

declare(strict_types=1);

namespace App\Service\Blog;

use App\Repository\BlogTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Servei de traducció sobirana per al Blog de Registrals amb memòria cau persistent per hash SHA-256.
 * Utilitza el node d'IA local SGLang (OpenAI Chat Completions compatible) sense enviar dades a tercers.
 */
class BlogTranslationService
{
    private const SUPPORTED_LANGS = [
        'es' => 'castellà (espanyol neutre professional)',
        'en' => 'anglès (professional, neutral business English)',
    ];

    public function __construct(
        private readonly BlogTranslationRepository $cacheRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'SGLANG_API_BASE_URL')]
        private readonly string $apiBaseUrl = 'http://127.0.0.1:30000/v1',
        #[Autowire(env: 'SGLANG_MODEL_NAME')]
        private readonly string $modelName = 'Qwen/Qwen2.5-VL-7B-Instruct',
        #[Autowire(env: 'default::SGLANG_API_KEY')]
        private readonly ?string $apiKey = null,
    ) {
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * Tradueix un fragment de text curt o llarg amb memòria cau SHA-256.
     *
     * @return array{text: string, from_cache: bool, fallback: bool}
     */
    public function translate(string $text, string $targetLang, bool $force = false): array
    {
        $lang = $this->normalizeLang($targetLang);
        if ($lang === 'cat' || trim($text) === '' || !isset(self::SUPPORTED_LANGS[$lang])) {
            return ['text' => $text, 'from_cache' => false, 'fallback' => false];
        }

        $hash = BlogTranslationRepository::generateHash($text, $lang);

        if (!$force) {
            $cached = $this->cacheRepository->findByHash($hash);
            if ($cached !== null) {
                $cached->incrementHits();
                $this->entityManager->flush();
                return ['text' => $cached->getTranslatedText(), 'from_cache' => true, 'fallback' => false];
            }
        }

        $translated = $this->callLocalAi($text, $lang);
        if ($translated !== null && $translated !== '' && $translated !== $text) {
            $this->cacheRepository->saveTranslation($hash, $lang, $text, $translated, $this->modelName);
            return ['text' => $translated, 'from_cache' => false, 'fallback' => false];
        }

        return ['text' => $text, 'from_cache' => false, 'fallback' => true];
    }

    /**
     * Tradueix un article complet dividint-lo per seccions (##) i desant tant les seccions
     * individuals com el cos complet per hash SHA-256.
     *
     * @return array{
     *     title: string,
     *     summary: string,
     *     content: string,
     *     from_cache: bool,
     *     fallback: bool,
     *     model_used: string
     * }
     */
    public function translatePost(BlogPost $post, string $targetLang, bool $force = false): array
    {
        $lang = $this->normalizeLang($targetLang);
        $rawContent = (string) ($post->content ?? '');

        if ($lang === 'cat' || !isset(self::SUPPORTED_LANGS[$lang])) {
            return [
                'title' => $post->title,
                'summary' => $post->summary,
                'content' => $rawContent,
                'from_cache' => false,
                'fallback' => false,
                'model_used' => $this->modelName,
            ];
        }

        $titleResult = $this->translate($post->title, $lang, $force);
        $summaryResult = $this->translate($post->summary, $lang, $force);

        if ($rawContent === '') {
            return [
                'title' => $titleResult['text'],
                'summary' => $summaryResult['text'],
                'content' => '',
                'from_cache' => $titleResult['from_cache'],
                'fallback' => $titleResult['fallback'],
                'model_used' => $this->modelName,
            ];
        }

        // Comprovar si el cos sencer ja és a la memòria cau
        $bodyHash = BlogTranslationRepository::generateHash($rawContent, $lang);
        if (!$force) {
            $cachedBody = $this->cacheRepository->findByHash($bodyHash);
            if ($cachedBody !== null) {
                $cachedBody->incrementHits();
                $this->entityManager->flush();

                return [
                    'title' => $titleResult['text'],
                    'summary' => $summaryResult['text'],
                    'content' => $cachedBody->getTranslatedText(),
                    'from_cache' => true,
                    'fallback' => false,
                    'model_used' => $cachedBody->getModelUsed(),
                ];
            }
        }

        // Traducció per seccions (##) per evitar timeouts i maximitzar reutilització de hash
        $sections = preg_split('/(\n(?=##\s+))/', $rawContent) ?: [$rawContent];
        $translatedSections = [];
        $hasErrors = false;

        foreach ($sections as $sec) {
            $secTrim = trim($sec);
            if ($secTrim === '') {
                continue;
            }
            $secRes = $this->translate($secTrim, $lang, $force);
            $translatedSections[] = $secRes['text'];
            if ($secRes['fallback']) {
                $hasErrors = true;
            }
        }

        $fullBody = implode("\n\n", $translatedSections);
        if (!$hasErrors && $fullBody !== '' && $fullBody !== $rawContent) {
            $this->cacheRepository->saveTranslation($bodyHash, $lang, $rawContent, $fullBody, $this->modelName);
        }

        return [
            'title' => $titleResult['text'],
            'summary' => $summaryResult['text'],
            'content' => $fullBody,
            'from_cache' => false,
            'fallback' => $hasErrors,
            'model_used' => $this->modelName,
        ];
    }

    public function normalizeLang(string $lang): string
    {
        $clean = strtolower(trim($lang));
        if ($clean === 'ca') {
            return 'cat';
        }
        return in_array($clean, ['cat', 'es', 'en'], true) ? $clean : 'cat';
    }

    private function callLocalAi(string $text, string $targetLang): ?string
    {
        $destName = self::SUPPORTED_LANGS[$targetLang] ?? $targetLang;
        $wordCount = str_word_count($text);
        $isShort = $wordCount < 35 && !str_contains($text, "\n\n");

        if ($isShort) {
            $prompt = sprintf(
                "Ets un traductor directe al %s. Tradueix ÚNICAMENT la següent frase o títol. NO afegeixis explicacions, NO escriguis cap paràgraf nou, NO desenvolupis el tema. Retorna exactament una sola línia amb la traducció directa.\n\nText:\n%s",
                $destName,
                $text
            );
            $maxTokens = 150;
        } else {
            $prompt = sprintf(
                "Ets un traductor tècnic i jurídic especialitzat en propietat civil, prova digital, eIDAS i custòdia documental. Tradueix fidelment el següent text escrit en català al %s. Mantén el format Markdown, llistes, negretes, taules i enllaços intactes. Retorna ÚNICAMENT la traducció directa del text facilitat, sense salutacions ni comentaris.\n\nText a traduir:\n%s",
                $destName,
                $text
            );
            $maxTokens = 4096;
        }

        $endpoint = rtrim($this->apiBaseUrl, '/');
        if (!str_ends_with($endpoint, '/chat/completions')) {
            $endpoint = str_ends_with($endpoint, '/v1')
                ? $endpoint . '/chat/completions'
                : $endpoint . '/v1/chat/completions';
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => $headers,
                'timeout' => 180,
                'max_duration' => 240,
                'json' => [
                    'model' => $this->modelName,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ets un assistent de traducció literal i directa.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => $maxTokens,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('BlogTranslationService: codi no esperat del node SGLang', [
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $data = $response->toArray(false);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            $this->logger->warning('BlogTranslationService: error connectant amb el node local SGLang', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

