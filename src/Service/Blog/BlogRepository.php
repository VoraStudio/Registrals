<?php

declare(strict_types=1);

namespace App\Service\Blog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Repositori d'articles del blog basat en fitxers Markdown (.md) amb Frontmatter YAML.
 */
class BlogRepository
{
    private string $postsDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%/content/blog')]
        ?string $postsDir = null
    ) {
        $this->postsDir = rtrim($postsDir ?? (__DIR__ . '/../../../content/blog'), '/\\');
    }

    /**
     * Retorna tots els articles publicats ordenats per data.
     *
     * @return BlogPost[]
     */
    public function getPublishedPosts(
        bool $includeFuture = false,
        string $sort = 'newest',
        ?string $tag = null
    ): array {
        if (!is_dir($this->postsDir)) {
            return [];
        }

        $files = scandir($this->postsDir);
        if ($files === false) {
            return [];
        }

        $posts = [];
        $today = date('Y-m-d');

        foreach ($files as $file) {
            if (!str_ends_with($file, '.md')) {
                continue;
            }
            if (in_array(strtoupper($file), ['README.MD', 'GUIA_REDACTORS.MD'], true)) {
                continue;
            }

            $filepath = $this->postsDir . DIRECTORY_SEPARATOR . $file;
            $post = $this->parsePostFile($filepath, false);

            if ($post === null || $post->draft) {
                continue;
            }

            if (!$includeFuture && $post->date > $today) {
                continue;
            }

            if ($tag !== null && $tag !== '') {
                $normalizedTag = mb_strtolower(trim($tag));
                $postTags = array_map(static fn (string $t): string => mb_strtolower(trim($t)), $post->tags);
                if (!in_array($normalizedTag, $postTags, true)) {
                    continue;
                }
            }

            $posts[] = $post;
        }

        usort($posts, static function (BlogPost $a, BlogPost $b) use ($sort): int {
            return $sort === 'oldest'
                ? strcmp($a->date, $b->date)
                : strcmp($b->date, $a->date);
        });

        return $posts;
    }

    /**
     * Retorna totes les etiquetes úniques dels articles publicats.
     *
     * @return string[]
     */
    public function getUniqueTags(bool $includeFuture = false): array
    {
        $tags = [];
        foreach ($this->getPublishedPosts($includeFuture) as $post) {
            foreach ($post->tags as $tag) {
                $t = trim($tag);
                if ($t !== '' && !in_array($t, $tags, true)) {
                    $tags[] = $t;
                }
            }
        }

        sort($tags, SORT_LOCALE_STRING);
        return $tags;
    }

    /**
     * Cerca un article publicat pel seu slug (protegit contra path traversal).
     */
    public function getPostBySlug(string $slug, bool $includeFuture = false): ?BlogPost
    {
        $cleanSlug = (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        if ($cleanSlug === '') {
            return null;
        }

        foreach ($this->getPublishedPosts($includeFuture) as $post) {
            if ($post->slug === $cleanSlug || $post->id === $cleanSlug) {
                return $this->parsePostFile($post->filepath, true);
            }
        }

        return null;
    }

    private function parsePostFile(string $filepath, bool $includeContent = false): ?BlogPost
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            return null;
        }

        $normalized = str_replace("\r\n", "\n", $content);
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $normalized, $matches)) {
            return null;
        }

        try {
            $meta = Yaml::parse($matches[1]);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($meta)) {
            return null;
        }

        $body = trim($matches[2]);
        $filename = basename($filepath);
        $fallbackSlug = (string) preg_replace('/^\d{4}-\d{2}-\d{2}-|\.md$/', '', $filename);
        $slug = (string) ($meta['slug'] ?? $meta['id'] ?? $fallbackSlug);
        $id = (string) ($meta['id'] ?? $slug);

        // Normalitzar data (Yaml::parse pot retornar un timestamp enter per dates ISO YYYY-MM-DD)
        $rawDate = $meta['date'] ?? '2026-01-01';
        if (is_int($rawDate)) {
            $date = date('Y-m-d', $rawDate);
        } elseif ($rawDate instanceof \DateTimeInterface) {
            $date = $rawDate->format('Y-m-d');
        } else {
            $date = trim((string) $rawDate);
        }

        $readTime = (string) ($meta['readTime'] ?? '');
        if ($readTime === '') {
            $words = str_word_count(strip_tags($body));
            $minutes = max(1, (int) ceil($words / 200));
            $readTime = $minutes . ' min';
        }

        $rawTags = $meta['tags'] ?? [];
        $tags = is_array($rawTags) ? array_values(array_map(static fn ($t): string => trim((string) $t), $rawTags)) : [];

        return new BlogPost(
            id: $id,
            slug: $slug,
            title: (string) ($meta['title'] ?? 'Sense títol'),
            summary: (string) ($meta['summary'] ?? $meta['description'] ?? ''),
            date: $date,
            author: (string) ($meta['author'] ?? 'Equip Registrals'),
            readTime: $readTime,
            tags: $tags,
            featured: !empty($meta['featured']),
            draft: !empty($meta['draft']),
            filepath: $filepath,
            content: $includeContent ? $body : null,
        );
    }
}

