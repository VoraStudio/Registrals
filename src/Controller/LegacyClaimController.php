<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Legacy\LegacyCertificateIssuerService;
use App\Service\Security\PrivacyMasker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Punt d'entrada públic del QR dedicat d'obertura successòria post-mortem
 * incrustat als Certificats d'Inventari Global de Llegat Digital.
 */
class LegacyClaimController extends AbstractController
{
    public function __construct(
        private readonly LegacyCertificateIssuerService $legacyCertificateIssuer,
    ) {
    }

    #[Route('/herencia/reclamar/{token}', name: 'app_legacy_claim_entry', methods: ['GET'])]
    public function show(string $token): Response
    {
        $resolution = $this->legacyCertificateIssuer->resolveClaimByRawToken($token);
        $certificate = $resolution['certificate'];
        $state = $resolution['state'];

        if ($state === 'invalid' || $certificate === null) {
            return $this->render('legacy/claim_entry.html.twig', [
                'state' => 'invalid',
                'certificate' => null,
                'maskedOwnerName' => null,
                'maskedContactName' => null,
                'openingFeeCents' => null,
                'isCurrentSnapshot' => false,
                'itemsCount' => 0,
            ], new Response('', Response::HTTP_NOT_FOUND));
        }

        $owner = $certificate->getIssuedBy();
        $contact = $certificate->getLegacyContact();

        $statusCode = in_array($state, ['revoked', 'superseded'], true)
            ? Response::HTTP_GONE
            : Response::HTTP_OK;

        return $this->render('legacy/claim_entry.html.twig', [
            'state' => $state,
            'certificate' => $certificate,
            'maskedOwnerName' => PrivacyMasker::maskName($owner->getFullName()),
            'maskedContactName' => $contact !== null ? PrivacyMasker::maskName($contact->getFullName()) : null,
            'openingFeeCents' => $owner->getPlan()?->getLegacyClaimOpeningFeeCents() ?? 1900,
            'isCurrentSnapshot' => $resolution['isCurrentSnapshot'],
            'itemsCount' => count($certificate->getIncludedItemCuisSnapshot() ?? []),
        ], new Response('', $statusCode));
    }
}
