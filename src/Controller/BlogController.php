<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Blog\BlogRepository;
use App\Service\Blog\BlogTranslationService;
use App\Service\Blog\MarkdownRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly BlogTranslationService $translationService,
    ) {
    }

    #[Route('/blog', name: 'app_blog', methods: ['GET', 'HEAD'])]
    public function index(Request $request): Response
    {
        $isPreview = $this->resolvePreviewMode($request);
        $selectedTag = trim((string) $request->query->get('tag', ''));
        $sort = $request->query->get('sort', 'newest') === 'oldest' ? 'oldest' : 'newest';
        $lang = $this->translationService->normalizeLang((string) $request->query->get('lang', 'cat'));

        $posts = $this->blogRepository->getPublishedPosts(
            includeFuture: $isPreview,
            sort: $sort,
            tag: $selectedTag !== '' ? $selectedTag : null
        );
        $tags = $this->blogRepository->getUniqueTags($isPreview);

        $featuredPost = null;
        $otherPosts = $posts;

        if ($selectedTag === '' && $posts !== []) {
            foreach ($posts as $idx => $post) {
                if ($post->featured) {
                    $featuredPost = $post;
                    unset($otherPosts[$idx]);
                    $otherPosts = array_values($otherPosts);
                    break;
                }
            }

            if ($featuredPost === null) {
                $featuredPost = array_shift($otherPosts);
            }
        }

        return $this->render('page/blog.html.twig', [
            'posts' => $posts,
            'featuredPost' => $featuredPost,
            'otherPosts' => $otherPosts,
            'tags' => $tags,
            'selectedTag' => $selectedTag,
            'sort' => $sort,
            'lang' => $lang,
            'isPreview' => $isPreview,
        ]);
    }

    #[Route('/blog/{slug}', name: 'app_blog_show', methods: ['GET', 'HEAD'])]
    public function show(string $slug, Request $request): Response
    {
        $isPreview = $this->resolvePreviewMode($request);
        $post = $this->blogRepository->getPostBySlug($slug, $isPreview);

        if ($post === null) {
            throw $this->createNotFoundException('Article del blog no trobat.');
        }

        $lang = $this->translationService->normalizeLang((string) $request->query->get('lang', 'cat'));
        $title = $post->title;
        $summary = $post->summary;
        $rawContent = (string) ($post->content ?? '');

        if ($lang !== 'cat') {
            $translated = $this->translationService->translatePost($post, $lang);
            $title = $translated['title'];
            $summary = $translated['summary'];
            $rawContent = $translated['content'];
        }

        $headings = $this->markdownRenderer->extractHeadings($rawContent);
        $htmlContent = $this->markdownRenderer->toHtml($rawContent, true);

        // Altres articles relacionats (màxim 3)
        $relatedPosts = array_values(array_filter(
            $this->blogRepository->getPublishedPosts($isPreview),
            static fn ($p): bool => $p->slug !== $post->slug
        ));
        $relatedPosts = array_slice($relatedPosts, 0, 3);

        return $this->render('page/blog_show.html.twig', [
            'post' => $post,
            'title' => $title,
            'summary' => $summary,
            'htmlContent' => $htmlContent,
            'headings' => $headings,
            'relatedPosts' => $relatedPosts,
            'lang' => $lang,
            'isPreview' => $isPreview,
        ]);
    }

    #[Route('/api/blog/translate', name: 'app_blog_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        $lang = $this->translationService->normalizeLang((string) ($data['lang'] ?? 'cat'));

        // Cas 1: Traducció d'article complet per slug
        if (!empty($data['slug'])) {
            $isPreview = $this->resolvePreviewMode($request);
            $post = $this->blogRepository->getPostBySlug((string) $data['slug'], $isPreview);
            if ($post === null) {
                return new JsonResponse(['success' => false, 'error' => 'Article no trobat'], Response::HTTP_NOT_FOUND);
            }

            $res = $this->translationService->translatePost($post, $lang);
            $html = $this->markdownRenderer->toHtml($res['content'], true);
            $headings = $this->markdownRenderer->extractHeadings($res['content']);

            return new JsonResponse([
                'success' => !$res['fallback'],
                'lang' => $lang,
                'from_cache' => $res['from_cache'],
                'fallback' => $res['fallback'],
                'model_used' => $res['model_used'],
                'title' => $res['title'],
                'summary' => $res['summary'],
                'html' => $html,
                'headings' => $headings,
            ]);
        }

        // Cas 2: Traducció en bloc de textos curts (títols i resums de targetes)
        if (!empty($data['items']) && is_array($data['items'])) {
            $results = [];
            $allFromCache = true;
            foreach ($data['items'] as $item) {
                $strItem = (string) $item;
                $res = $this->translationService->translate($strItem, $lang);
                $results[$strItem] = $res['text'];
                if (!$res['from_cache']) {
                    $allFromCache = false;
                }
            }

            return new JsonResponse([
                'success' => true,
                'lang' => $lang,
                'from_cache' => $allFromCache,
                'model_used' => $this->translationService->getModelName(),
                'items' => $results,
            ]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Paràmetres insuficients'], Response::HTTP_BAD_REQUEST);
    }

    private function resolvePreviewMode(Request $request): bool
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        $previewParam = $request->query->get('preview');

        if ($previewParam !== null) {
            if (in_array(strtolower((string) $previewParam), ['0', 'off', 'exit', 'false'], true)) {
                $session?->remove('registrals_blog_preview');
                return false;
            }
            $expected = $_ENV['BLOG_PREVIEW_TOKEN'] ?? 'registrals_preview_2026';
            if (hash_equals((string) $expected, (string) $previewParam)) {
                $session?->set('registrals_blog_preview', true);
                return true;
            }
        }

        return (bool) ($session?->get('registrals_blog_preview', false));
    }
}

