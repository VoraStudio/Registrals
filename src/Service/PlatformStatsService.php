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
    // Temporalment a 0 per validar les mètriques abans d'activar el llindar de 1000.
    public const DEFAULT_MIN_THRESHOLD = 0;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ItemRepository $itemRepository,
        private readonly CertificateRepository $certificateRepository,
        private readonly int $minThreshold = self::DEFAULT_MIN_THRESHOLD,
    ) {
    }

    /**
     * Computes real-time statistics for the public home page.
     *
     * @return array{
     *     registeredUsers: int,
     *     custodiedItems: int,
     *     issuedCertificates: int,
     *     showStats: bool
     * }
     */
    public function getHomeStats(): array
    {
        $registeredUsers = $this->userRepository->countNonAdmin();
        $custodiedItems = $this->itemRepository->countCustodiedTotal();
        $issuedCertificates = $this->certificateRepository->countValid();

        return [
            'registeredUsers' => $registeredUsers,
            'custodiedItems' => $custodiedItems,
            'issuedCertificates' => $issuedCertificates,
            'showStats' => $this->minThreshold <= 0 || $registeredUsers >= $this->minThreshold,
        ];
    }
}
