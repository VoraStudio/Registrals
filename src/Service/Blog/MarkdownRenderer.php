<?php

declare(strict_types=1);

namespace App\Service\Blog;

/**
 * Renderitzador segur de Markdown a HTML per al Blog de Registrals.
 * Suporta capçaleres, llistes, negreta, cursiva, codi, taules, cites i admonitions (!!! / ???).
 */
class MarkdownRenderer
{
    /**
     * Extreu els subtítols de secció (##) per construir l'índex lateral de l'article.
     *
     * @return array<int, array{id: string, title: string}>
     */
    public function extractHeadings(string $md): array
    {
        $headings = [];
        if (preg_match_all('/^##\s+(.+)$/m', $md, $matches)) {
            foreach ($matches[1] as $rawTitle) {
                $title = trim(strip_tags($rawTitle));
                $id = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title), '-'));
                if ($id === '') {
                    $id = 'sec-' . (count($headings) + 1);
                }
                $headings[] = [
                    'id' => $id,
                    'title' => $title,
                ];
            }
        }

        return $headings;
    }

    public function toHtml(string $md, bool $stripLeadingH1 = true): string
    {
        $cleanMd = trim($md);
        if ($stripLeadingH1) {
            $cleanMd = (string) preg_replace('/^\s*#\s+[^\r\n]*(?:\r?\n)*/', '', $cleanMd);
            $cleanMd = trim($cleanMd);
        }

        $html = htmlspecialchars($cleanMd, ENT_NOQUOTES, 'UTF-8');

        // Blocs de codi ```
        $html = (string) preg_replace_callback('/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/', static function (array $m): string {
            return '<pre><code class="language-' . htmlspecialchars($m[1]) . '">' . trim($m[2]) . '</code></pre>';
        }, $html);

        // Codi inline `
        $html = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Headings amb àncora id per als h2
        $html = (string) preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
        $html = (string) preg_replace_callback('/^## (.*$)/m', static function (array $m): string {
            $title = trim($m[1]);
            $id = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strip_tags($title)) ?: $title), '-'));
            return sprintf('<h2 id="%s">%s</h2>', $id, $title);
        }, $html);
        $html = (string) preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);

        // Admonitions / Callouts (!!! tipus "Títol" o ??? tipus)
        $lines = explode("\n", str_replace("\r\n", "\n", $html));
        $newLines = [];
        $lineCount = count($lines);
        $idx = 0;

        while ($idx < $lineCount) {
            $line = $lines[$idx];
            if (preg_match('/^(!{3}|\?{3}\+?)\s+([a-zA-Z0-9_-]+)(?:\s+(.*))?$/', trim($line), $am)) {
                $marker = $am[1];
                $rawType = strtolower($am[2]);
                $typeMap = [
                    'exemple' => 'example',
                    'ejemplo' => 'example',
                    'nota' => 'note',
                    'veure' => 'seealso',
                    'important' => 'important',
                    'atencio' => 'attention',
                    'atenció' => 'attention',
                    'pregunta' => 'question',
                    'ajuda' => 'help',
                    'faq' => 'question',
                    'perill' => 'danger',
                    'error' => 'error',
                    'avis' => 'warning',
                    'avís' => 'warning',
                    'consell' => 'tip',
                    'exit' => 'success',
                    'èxit' => 'success',
                    'cita' => 'quote',
                ];
                $type = $typeMap[$rawType] ?? $rawType;
                $rawTitle = trim($am[3] ?? '');
                $title = $rawTitle;
                if ((str_starts_with($title, '"') && str_ends_with($title, '"')) ||
                    (str_starts_with($title, "'") && str_ends_with($title, "'"))) {
                    $title = substr($title, 1, -1);
                }
                $title = trim($title);
                if ($title === '') {
                    $title = ucfirst($rawType);
                }

                $idx++;
                $contentLines = [];
                while ($idx < $lineCount) {
                    $curr = $lines[$idx];
                    if (preg_match('/^(?: {4}|\t)(.*)$/', $curr, $cm)) {
                        $contentLines[] = $cm[1];
                        $idx++;
                    } elseif (trim($curr) === '') {
                        $peek = $idx + 1;
                        while ($peek < $lineCount && trim($lines[$peek]) === '') {
                            $peek++;
                        }
                        if ($peek < $lineCount && preg_match('/^(?: {4}|\t)/', $lines[$peek])) {
                            $contentLines[] = '';
                            $idx++;
                        } else {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                $innerMd = trim(implode("\n", $contentLines));
                $innerHtml = $innerMd !== '' ? $this->toHtml($innerMd, false) : '';

                $isCollapsible = str_starts_with($marker, '???');
                $isOpen = ($marker === '???+');

                if ($isCollapsible) {
                    $openAttr = $isOpen ? ' open' : '';
                    $admonitionHtml = sprintf(
                        '<details class="admonition admonition-%s admonition-collapsible"%s><summary class="admonition-title">%s</summary><div class="admonition-content">%s</div></details>',
                        htmlspecialchars($type),
                        $openAttr,
                        htmlspecialchars_decode($title),
                        $innerHtml
                    );
                } else {
                    $admonitionHtml = sprintf(
                        '<div class="admonition admonition-%s"><div class="admonition-title">%s</div><div class="admonition-content">%s</div></div>',
                        htmlspecialchars($type),
                        htmlspecialchars_decode($title),
                        $innerHtml
                    );
                }

                $newLines[] = "\n\n" . $admonitionHtml . "\n\n";
            } else {
                $newLines[] = $line;
                $idx++;
            }
        }
        $html = implode("\n", $newLines);

        // Blockquotes
        $html = (string) preg_replace('/^(?:&gt;|>)\s*(.*$)/m', '<blockquote>$1</blockquote>', $html);
        $html = (string) preg_replace('/<\/blockquote>\s*<blockquote>/', '<br>', $html);

        // Separadors horitzontals
        $html = (string) preg_replace('/^(?:-{3,}|\*{3,}|_{3,})\s*$/m', "\n\n<hr>\n\n", $html);

        // Llistes desordenades (- o *)
        $html = (string) preg_replace('/^(?:[\-*])\s+(.*$)/m', '<li>$1</li>', $html);
        $html = (string) preg_replace('/^<li>.*<\/li>(?:\n<li>.*<\/li>)*$/m', "\n\n<ul>\n$0\n</ul>\n\n", $html);

        // Llistes ordenades (1. item)
        $html = (string) preg_replace('/^\d+\.\s+(.*$)/m', '<oli>$1</oli>', $html);
        $html = (string) preg_replace('/^<oli>.*<\/oli>(?:\n<oli>.*<\/oli>)*$/m', "\n\n<ol>\n$0\n</ol>\n\n", $html);
        $html = str_replace(['<oli>', '</oli>'], ['<li>', '</li>'], $html);

        // Negreta i cursiva
        $html = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);
        $html = (string) preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html);

        // Enllaços [text](url)
        $html = (string) preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $html);

        // Taules Markdown
        $html = (string) preg_replace_callback('/((?:^\|.+?\|(?:\r?\n|$))+)/m', static function (array $m): string {
            $rawLines = array_values(array_filter(array_map('trim', explode("\n", trim($m[1])))));
            if (count($rawLines) < 2) {
                return $m[0];
            }

            if (!preg_match('/^\|(?:\s*:?-+:?\s*\|)+$/', $rawLines[1])) {
                return $m[0];
            }

            $sepCols = array_values(array_filter(array_map('trim', explode('|', $rawLines[1]))));
            $aligns = [];
            foreach ($sepCols as $c) {
                $left = str_starts_with($c, ':');
                $right = str_ends_with($c, ':');
                $aligns[] = ($left && $right) ? 'center' : ($right ? 'right' : 'left');
            }

            $headerCols = array_values(array_filter(array_map('trim', explode('|', $rawLines[0]))));
            $tableHtml = '<div class="overflow-x-auto my-6"><table class="w-full text-left border-collapse text-sm"><thead><tr class="border-b-2 border-gold/40 bg-ink/5">';
            foreach ($headerCols as $i => $col) {
                $align = $aligns[$i] ?? 'left';
                $tableHtml .= sprintf('<th class="py-2.5 px-3 font-bold text-ink" style="text-align: %s;">%s</th>', $align, $col);
            }
            $tableHtml .= '</tr></thead><tbody class="divide-y divide-steel/15">';

            for ($i = 2; $i < count($rawLines); $i++) {
                $rowCols = array_values(array_filter(array_map('trim', explode('|', $rawLines[$i]))));
                if ($rowCols === []) {
                    continue;
                }
                $tableHtml .= '<tr>';
                foreach ($rowCols as $j => $col) {
                    $align = $aligns[$j] ?? 'left';
                    $tableHtml .= sprintf('<td class="py-2.5 px-3 text-ink/85" style="text-align: %s;">%s</td>', $align, $col);
                }
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';

            return $tableHtml;
        }, $html);

        $html = (string) preg_replace('/\s*<\/(ul|ol|table|blockquote|pre|details)>\s*/', "\n\n</$1>\n\n", $html);
        $html = (string) preg_replace('/\s*<(ul|ol|table|blockquote|pre|details)([^>]*)>\s*/', "\n\n<$1$2>\n", $html);

        $parts = preg_split('/\n{2,}/', $html) ?: [];
        $output = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^<\/?(h[1-6]|pre|blockquote|ul|ol|li|hr|div|details|summary|table|tbody|thead|tr|th|td)/i', $p)) {
                $output[] = $p;
            } else {
                $output[] = '<p>' . nl2br($p) . '</p>';
            }
        }

        $res = implode("\n", $output);
        return (string) preg_replace('/<p>\s*(<(?:ul|ol|table|blockquote|hr|div|details)[\s\S]*?<\/(?:ul|ol|table|blockquote|div|details)>|<hr>)\s*<\/p>/i', '$1', $res);
    }
}

