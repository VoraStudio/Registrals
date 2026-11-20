<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\System\ContactEmailRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ContactEmailExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContactEmailRegistry $registry
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('contact_email', $this->getEmail(...)),
        ];
    }

    public function getEmail(string $channel = 'general'): string
    {
        return $this->registry->get($channel);
    }
}

