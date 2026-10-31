<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Certificate;
use App\Entity\Item;
use App\Enum\CertificateMode;
use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Enum\CertificateStatus;
use App\Enum\ItemStatus;
use App\Repository\CertificateRepository;
use App\Service\ItemFingerprintCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Endpoint públic de verificació de certificats digitals (eIDAS & Blockchain).
 * Accessible mitjançant el codi QR del document PDF sense necessitat d'autenticació.
 * Dissenyat sota el principi de privadesa per disseny i minimització de dades (Art. 5 RGPD).
 */
class VerifyCertificateController extends AbstractController
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly ItemFingerprintCalculator $fingerprintCalculator,
        private readonly ?UserActivityLogger $activityLogger = null
    ) {
    }

    #[Route('/verify/{uuid}', name: 'app_verify_certificate', methods: ['GET'])]
    public function verify(string $uuid): Response
    {
        // 1. Validació de format UUID v7
        if (!Uuid::isValid($uuid)) {
            return $this->render('verify/not_found.html.twig', [
                'identifier' => $uuid,
                'reason' => 'El format de l\'identificador de certificat no és un UUID vàlid.',
            ], new Response('', Response::HTTP_NOT_FOUND));
        }

        // 2. Cerca del certificat
        $certificate = $this->certificateRepository->findByUuid($uuid);

        if (!$certificate instanceof Certificate) {
            return $this->render('verify/not_found.html.twig', [
                'identifier' => $uuid,
                'reason' => 'No s\'ha trobat cap certificat registrat amb aquest codi criptogràfic a la custòdia de Registrals.',
            ], new Response('', Response::HTTP_NOT_FOUND));
        }

        $item = $certificate->getItem();

        // 3. Comprovació de vigència i integritat del bé respecte al certificat
        $currentFingerprint = $this->fingerprintCalculator->calculateForItem($item);
        $isFingerprintMatching = hash_equals($certificate->getItemFingerprintAtIssue(), $currentFingerprint);

        // 4. Estat de seguretat (Robatori / sostracció)
        $isStolen = $item->getStatus() === ItemStatus::STOLEN;

        // 5. Estat global de verificació
        $verificationStatus = match (true) {
            $isStolen => 'stolen',
            !$certificate->isValid() => 'revoked',
            !$isFingerprintMatching => 'superseded',
            $certificate->getMode() === CertificateMode::DRAFT => 'draft',
            default => 'valid',
        };

        // 6. Pseudonimització RGPD del titular (minimització de dades personals)
        $maskedOwnerName = $this->maskPersonalName($certificate->getIssuedBy()->getFullName());

        // 7. Registre d'auditoria forense (registrals_audit)
        $this->activityLogger?->logAnonymous(
            identifier: $certificate->getCertificateNumber(),
            action: UserActivityAction::CERTIFICATE_VERIFIED,
            description: sprintf('Verificació pública notarial del certificat %s (%s)', $certificate->getCertificateNumber(), $item->getCui()),
            details: [
                'certificate_uuid' => $uuid,
                'certificate_number' => $certificate->getCertificateNumber(),
                'cui' => $item->getCui(),
                'status' => $verificationStatus,
            ],
            userName: 'Verificador públic'
        );

        return $this->render('verify/show.html.twig', [
            'certificate' => $certificate,
            'item' => $item,
            'isStolen' => $isStolen,
            'isFingerprintMatching' => $isFingerprintMatching,
            'currentFingerprint' => $currentFingerprint,
            'verificationStatus' => $verificationStatus,
            'maskedOwnerName' => $maskedOwnerName,
            'latestBlockchainReceipt' => $item->getLatestBlockchainReceipt(),
        ]);
    }

    /**
     * Aplica màscara de privadesa RGPD a noms i cognoms per a consulta pública:
     * "Carles Sánchez" -> "C***** S******"
     */
    private function maskPersonalName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return 'T****** A******';
        }

        $parts = preg_split('/\s+/', $trimmed);
        if ($parts === false || count($parts) === 0) {
            return 'T******';
        }

        $maskedParts = [];
        foreach ($parts as $part) {
            $length = mb_strlen($part);
            if ($length <= 1) {
                $maskedParts[] = $part . '***';
            } else {
                $firstChar = mb_substr($part, 0, 1);
                $maskedParts[] = $firstChar . str_repeat('*', min(6, $length - 1));
            }
        }

        return implode(' ', $maskedParts);
    }
}
