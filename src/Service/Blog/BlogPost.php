<?php

declare(strict_types=1);

namespace App\Service\Blog;

/**
 * Value object immutable que representa un article del blog carregat des de Markdown.
 */
final readonly class BlogPost
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $summary,
        public string $date,
        public string $author,
        public string $readTime,
        public array $tags,
        public bool $featured,
        public bool $draft,
        public string $filepath,
        public ?string $content = null,
    ) {
    }

    public function getFormattedDate(): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $this->date);
        if ($dt === false) {
            return $this->date;
        }

        $months = [
            1 => 'de gener',
            2 => 'de febrer',
            3 => 'de març',
            4 => 'd\'abril',
            5 => 'de maig',
            6 => 'de juny',
            7 => 'de juliol',
            8 => 'd\'agost',
            9 => 'de setembre',
            10 => 'd\'octubre',
            11 => 'de novembre',
            12 => 'de desembre',
        ];

        $day = (int) $dt->format('j');
        $month = $months[(int) $dt->format('n')] ?? $dt->format('m');
        $year = $dt->format('Y');

        return sprintf('%d %s de %s', $day, $month, $year);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'date' => $this->date,
            'formattedDate' => $this->getFormattedDate(),
            'author' => $this->author,
            'readTime' => $this->readTime,
            'tags' => $this->tags,
            'featured' => $this->featured,
            'draft' => $this->draft,
        ];
    }
}

