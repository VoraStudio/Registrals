<?php

declare(strict_types=1);

namespace App\Tests\Service\Blog;

use App\Service\Blog\BlogRepository;
use App\Service\Blog\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class BlogRepositoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'registrals_blog_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = scandir($this->tempDir) ?: [];
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    @unlink($this->tempDir . DIRECTORY_SEPARATOR . $file);
                }
            }
            @rmdir($this->tempDir);
        }
    }

    public function testParsesPublishedPostsAndFiltersDraftsAndFutureDates(): void
    {
        file_put_contents($this->tempDir . '/2026-01-10-article-published.md', <<<'MD'
---
id: "art-1"
slug: "article-published"
title: "Article Publicat"
summary: "Resum de prova"
date: "2026-01-10"
author: "Albert"
readTime: 4
tags: ["Legal", "Blockchain"]
featured: true
draft: false
---

# Article Publicat

## Introducció

Text de **prova**.
MD);

        file_put_contents($this->tempDir . '/2026-01-15-esborrany.md', <<<'MD'
---
slug: "esborrany"
title: "Esborrany"
date: "2026-01-15"
draft: true
---

Contingut esborrany.
MD);

        file_put_contents($this->tempDir . '/2099-01-01-futur.md', <<<'MD'
---
slug: "article-futur"
title: "Article Futur"
date: "2099-01-01"
draft: false
---

Contingut futur.
MD);

        file_put_contents($this->tempDir . '/GUIA_REDACTORS.md', '# Ignorar');

        $repo = new BlogRepository($this->tempDir);

        $published = $repo->getPublishedPosts();
        $this->assertCount(1, $published);
        $this->assertSame('article-published', $published[0]->slug);
        $this->assertTrue($published[0]->featured);
        $this->assertSame(['Legal', 'Blockchain'], $published[0]->tags);

        $withFuture = $repo->getPublishedPosts(includeFuture: true);
        $this->assertCount(2, $withFuture);

        $byTag = $repo->getPublishedPosts(tag: 'blockchain');
        $this->assertCount(1, $byTag);

        $this->assertSame(['Blockchain', 'Legal'], $repo->getUniqueTags());
    }

    public function testGetPostBySlugBlocksPathTraversal(): void
    {
        $repo = new BlogRepository($this->tempDir);
        $this->assertNull($repo->getPostBySlug('../secrets'));
        $this->assertNull($repo->getPostBySlug('invalid/slug'));
    }

    public function testMarkdownRendererRendersHeadingsListsAndAdmonitions(): void
    {
        $renderer = new MarkdownRenderer();
        $md = <<<'MD'
# Títol Principal

## Primera Secció

Text amb **negreta** i `codi`.

- Element 1
- Element 2

!!! tip "Consell"
    Això és un avís important.
MD;

        $headings = $renderer->extractHeadings($md);
        $this->assertCount(1, $headings);
        $this->assertSame('Primera Secció', $headings[0]['title']);

        $html = $renderer->toHtml($md, true);
        $this->assertStringNotContainsString('<h1>Títol Principal</h1>', $html);
        $this->assertStringContainsString('<h2 id="' . $headings[0]['id'] . '">Primera Secció</h2>', $html);
        $this->assertStringContainsString('<strong>negreta</strong>', $html);
        $this->assertStringContainsString('<code>codi</code>', $html);
        $this->assertStringContainsString('<li>Element 1</li>', $html);
        $this->assertStringContainsString('class="admonition admonition-tip"', $html);
    }
}

