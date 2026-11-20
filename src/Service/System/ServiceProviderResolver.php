<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Enum\ServiceProviderCategory;
use App\Repository\ServiceProviderConfigRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Resol el nom públic vigent d'un proveïdor de serveis externs segons la data de consulta.
 */
final class ServiceProviderResolver
{
    /** @var array<string, string> */
    private array $memo = [];

    public function __construct(
        private readonly ServiceProviderConfigRepository $repository
    ) {
    }

    public function getPublicName(ServiceProviderCategory|string $category, ?DateTimeInterface $at = null): string
    {
        $cat = is_string($category) ? ServiceProviderCategory::tryFrom($category) : $category;
        if ($cat === null) {
            return '';
        }

        $target = match (true) {
            $at instanceof DateTimeImmutable => $at,
            $at instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($at),
            default => new DateTimeImmutable(),
        };

        $cacheKey = $cat->value . '@' . $target->format('Y-m-d H:i');
        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        try {
            $config = $this->repository->findActiveForCategory($cat, $target);
            if ($config !== null) {
                return $this->memo[$cacheKey] = $config->getPublicName();
            }
        } catch (Throwable) {
        }

        return $this->memo[$cacheKey] = $cat->defaultPublicName();
    }
}

