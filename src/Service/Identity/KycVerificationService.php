<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\DTO\KycSessionDto;
use App\DTO\KycVerdictDto;
use App\Entity\User;
use App\Enum\KycStatus;
use App\Infrastructure\Adapter\Identity\IdentityVerificationGatewayInterface;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;

/**
 * Cas d'ús de verificació d'identitat biomètrica eIDV / KYC (Namirial OnBoarding API v2).
 */
class KycVerificationService
{
    public const DEFAULT_KYC_PRICE_CENTS = 350;

    public function __construct(
        private readonly IdentityVerificationGatewayInterface $identityGateway,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function getKycPriceCents(User $user): int
    {
        $userPlan = $user->getPlan();
        if ($userPlan !== null && $userPlan->getKycPriceCents() > 0) {
            return $userPlan->getKycPriceCents();
        }

        $freePlan = $this->planRepository->findDefaultFreePlan();
        if ($freePlan !== null && $freePlan->getKycPriceCents() > 0) {
            return $freePlan->getKycPriceCents();
        }

        return self::DEFAULT_KYC_PRICE_CENTS;
    }

    public function isIncludedInPlan(User $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return ($user->getPlan()?->getKycIncluded() ?? 0) > 0;
    }

    public function canStartWithoutCheckout(User $user): bool
    {
        return $this->isIncludedInPlan($user) || $user->getKycPaidCredits() > 0;
    }

    public function hasCompleteLegalName(User $user): bool
    {
        return trim($user->getFirstName()) !== '' && trim($user->getLastName()) !== '';
    }

    public function hasCompleteIdentityProfile(User $user): bool
    {
        return $this->hasCompleteLegalName($user)
            && trim((string) ($user->getTaxId() ?? '')) !== '';
    }

    /**
     * Inicia una sessió real o simulada a Namirial OnBoarding validant quota o crèdit Stripe previ.
     */
    public function initiateVerification(User $user, string $wizardUrlBack, ?string $clientIp = null): KycSessionDto
    {
        if ($user->getKycStatus() === KycStatus::APPROVED) {
            throw new DomainException('La teva identitat ja ha estat verificada favorablement.');
        }

        if (!$this->hasCompleteIdentityProfile($user)) {
            throw new DomainException('Cal informar el teu nom, cognoms i NIF / NIE / CIF al perfil abans d\'iniciar la verificació.');
        }

        if (!$this->canStartWithoutCheckout($user)) {
            throw new DomainException('El teu pla actual requereix abonar prèviament la taxa de verificació d\'identitat.');
        }

        $referenceId = sprintf('REG-KYC-%d-%s', $user->getId() ?? 0, strtoupper(bin2hex(random_bytes(4))));
        $session = $this->identityGateway->startVerificationSession($user, $referenceId, $wizardUrlBack);

        // Nomes consumim el credit un cop la sessio externa s'ha creat amb exit
        if (!$this->isIncludedInPlan($user)) {
            $user->consumeKycPaidCredit();
        }

        $user->setSignaturitKycId($session->requestId);
        $user->setKycReferenceId($session->referenceId);
        $user->setKycStatus(KycStatus::PENDING);
        $user->setKycLastReason(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->activityLogger?->log(
            user: $user,
            action: UserActivityAction::KYC_INITIATED,
            description: 'Sol·licitud de verificació d\'identitat eIDV iniciada via Namirial OnBoarding.',
            details: [
                'request_id' => $session->requestId,
                'reference_id' => $session->referenceId,
                'simulated' => $session->isSimulated,
                'client_ip' => $clientIp,
            ]
        );

        return $session;
    }

    /**
     * Consulta servidor-a-servidor l'estat real de la verificació i actualitza l'entitat User de forma idempotent.
     */
    public function syncUserVerificationStatus(User $user, ?string $clientIp = null, string $source = 'callback'): KycVerdictDto
    {
        $requestId = trim((string) ($user->getSignaturitKycId() ?? ''));
        if ($requestId === '') {
            throw new DomainException('No existeix cap procés de verificació d\'identitat actiu per consultar.');
        }

        $previousStatus = $user->getKycStatus();
        $verdict = $this->identityGateway->fetchVerificationVerdict($requestId);

        $user->setKycStatus($verdict->status);
        $user->setKycLastReason($verdict->reason);

        if ($verdict->status === KycStatus::APPROVED) {
            if ($user->getIdentityVerifiedAt() === null) {
                $user->setIdentityVerifiedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            }

            if ($previousStatus !== KycStatus::APPROVED) {
                $this->activityLogger?->log(
                    user: $user,
                    action: UserActivityAction::KYC_APPROVED,
                    description: 'Identitat verificada favorablement amb document oficial i prova de vida (Namirial eIDV).',
                    details: $this->buildVerdictAuditDetails($verdict, $clientIp, $source)
                );
            }
        } elseif ($verdict->status === KycStatus::REJECTED && $previousStatus !== KycStatus::REJECTED) {
            $this->activityLogger?->log(
                user: $user,
                action: UserActivityAction::KYC_REJECTED,
                description: sprintf('Verificació eIDV rebutjada: %s', $verdict->reason ?? 'Resolució desfavorable.'),
                details: $this->buildVerdictAuditDetails($verdict, $clientIp, $source)
            );
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $verdict;
    }

    /**
     * Processa notificacions webhook de Namirial OnBoarding contrastant sempre l'estat real via API.
     *
     * @param array<string, mixed> $payload
     */
    public function handleWebhookPayload(array $payload, ?string $clientIp = null): bool
    {
        $requestId = $this->extractFirstString($payload, [
            ['requestId'],
            ['id'],
            ['data', 'requestId'],
            ['data', 'id'],
            ['request', 'id'],
        ]);

        $referenceId = $this->extractFirstString($payload, [
            ['referenceId'],
            ['data', 'referenceId'],
            ['request', 'referenceId'],
        ]);

        $user = null;
        if ($requestId !== null) {
            $user = $this->userRepository->findOneBy(['signaturitKycId' => $requestId]);
        }
        if ($user === null && $referenceId !== null) {
            $user = $this->userRepository->findOneBy(['kycReferenceId' => $referenceId]);
        }

        if (!$user instanceof User) {
            $this->logger?->warning('Webhook Namirial OnBoarding sense usuari coincident', [
                'request_id' => $requestId,
                'reference_id' => $referenceId,
            ]);
            return false;
        }

        if ($user->getSignaturitKycId() === null && $requestId !== null) {
            $user->setSignaturitKycId($requestId);
        }

        $this->syncUserVerificationStatus($user, $clientIp, 'webhook');

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVerdictAuditDetails(KycVerdictDto $verdict, ?string $clientIp, string $source): array
    {
        return array_filter([
            'request_id' => $verdict->requestId,
            'reference_id' => $verdict->referenceId,
            'provider_status' => $verdict->providerStatus,
            'reason' => $verdict->reason,
            'verified_first_name' => $verdict->verifiedFirstName,
            'verified_last_name' => $verdict->verifiedLastName,
            'biometric_match_score' => $verdict->biometricMatchScore,
            'simulated' => $verdict->isSimulated,
            'source' => $source,
            'client_ip' => $clientIp,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<list<string>> $paths
     */
    private function extractFirstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $segments) {
            $cursor = $payload;
            foreach ($segments as $segment) {
                if (!\is_array($cursor) || !array_key_exists($segment, $cursor)) {
                    $cursor = null;
                    break;
                }
                $cursor = $cursor[$segment];
            }

            if (\is_string($cursor) && trim($cursor) !== '') {
                return trim($cursor);
            }
        }

        return null;
    }
}
