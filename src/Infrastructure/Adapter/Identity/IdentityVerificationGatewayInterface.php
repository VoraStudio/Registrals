<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Identity;

use App\DTO\KycSessionDto;
use App\DTO\KycVerdictDto;
use App\Entity\User;

/**
 * Port hexagonal per a la verificació biomètrica i documental d'identitat (eIDV / KYC).
 */
interface IdentityVerificationGatewayInterface
{
    /**
     * Crea una sol·licitud d'identificació remota i retorna la URL segura del wizard per a l'usuari.
     */
    public function startVerificationSession(User $user, string $referenceId, string $wizardUrlBack): KycSessionDto;

    /**
     * Consulta servidor-a-servidor l'estat i veredicte real d'una sol·licitud de verificació.
     */
    public function fetchVerificationVerdict(string $requestId): KycVerdictDto;

    /**
     * Verifica en temps constant la capçalera secreta configurada al webhook extern.
     */
    public function verifyWebhookSecret(?string $providedSecret): bool;

    /**
     * Indica si l'adaptador opera amb credencials reals o en mode de simulació local.
     */
    public function isLiveConfigured(): bool;
}

