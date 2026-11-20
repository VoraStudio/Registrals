<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\System\ServiceProviderResolver;
use DateTimeInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ServiceProviderExtension extends AbstractExtension
{
    public function __construct(
        private readonly ServiceProviderResolver $resolver
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('service_provider_name', $this->getPublicName(...)),
        ];
    }

    public function getPublicName(string $category, ?DateTimeInterface $at = null): string
    {
        return $this->resolver->getPublicName($category, $at);
    }
}

