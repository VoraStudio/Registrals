<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\UserRepository;

/**
 * Service to compute live platform statistics displayed on the public landing page.
 */
final class PlatformStatsService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ItemRepository $itemRepository,
        private readonly CertificateRepository $certificateRepository,
    ) {
    }

    /**
     * Computes real-time statistics for the public home page.
     *
     * @return array{
     *     registeredUsers: int,
     *     certifiedItems: int,
     *     totalDeclaredValueCents: int,
     *     totalDeclaredValueEuros: float,
     *     totalDeclaredValueFormatted: string
     * }
     */
    public function getHomeStats(): array
    {
        $registeredUsers = $this->userRepository->countTotal();
        $certifiedItems = $this->itemRepository->countActiveTotal();
        $totalDeclaredValueCents = $this->itemRepository->sumTotalDeclaredValue();
        $totalDeclaredValueEuros = $totalDeclaredValueCents / 100.0;

        // Format as e.g. "3.334€"
        $totalDeclaredValueFormatted = number_format($totalDeclaredValueEuros, 0, ',', '.') . '€';

        return [
            'registeredUsers' => $registeredUsers,
            'certifiedItems' => $certifiedItems,
            'totalDeclaredValueCents' => $totalDeclaredValueCents,
            'totalDeclaredValueEuros' => $totalDeclaredValueEuros,
            'totalDeclaredValueFormatted' => $totalDeclaredValueFormatted,
        ];
    }
}
