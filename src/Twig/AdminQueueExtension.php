<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\SuccessionClaimRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminQueueExtension extends AbstractExtension
{
    public function __construct(
        private readonly SuccessionClaimRepository $claimRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_succession_claims_count', $this->getPendingSuccessionClaimsCount(...)),
        ];
    }

    public function getPendingSuccessionClaimsCount(): int
    {
        try {
            return $this->claimRepository->countPendingHumanReview();
        } catch (\Throwable) {
            return 0;
        }
    }
}
