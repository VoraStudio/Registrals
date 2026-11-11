<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LegacyContact;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\LegacyContactStatus;
use App\Enum\SuccessionClaimStatus;
use App\Repository\LegacyContactRepository;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\SuccessionClaimCheckoutService;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Portal autenticat per a la Persona de Confiança acceptada (Art. 96 LOPDGDD),
 * que permet iniciar i seguir l'expedient successori sense dependre d'haver rebut
 * físicament el PDF del titular ni exposar cap inventari en vida (Art. 14 RGPD).
 */
#[Route('/app/perfil/herencia/{uuid}/expedient')]
#[IsGranted('ROLE_USER')]
class LegacyBeneficiaryClaimController extends AbstractController
{
    public function __construct(
        private readonly LegacyContactRepository $legacyContactRepository,
        private readonly SuccessionClaimRepository $claimRepository,
        private readonly SuccessionClaimWorkflowService $workflowService,
        private readonly SuccessionClaimCheckoutService $checkoutService,
    ) {
    }

    #[Route('', name: 'app_profile_legacy_claim_portal', methods: ['GET'])]
    public function show(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = $this->resolveAuthorizedContactOrThrow($user, $uuid);
        $latestClaim = $this->claimRepository->findLatestByLegacyContact($contact);

        if ($contact->getStatus() !== LegacyContactStatus::ACCEPTED && $latestClaim === null) {
            $this->addFlash('error', 'Només les designacions acceptades i actives poden iniciar un expedient successori.');

            return $this->redirectToProfileHeritage();
        }

        $openingFeeCents = $contact->getOwner()->getPlan()?->getLegacyClaimOpeningFeeCents() ?? 1900;
        $startNew = $latestClaim instanceof SuccessionClaim
            && $latestClaim->getStatus() === SuccessionClaimStatus::REJECTED
            && $contact->getStatus()->isAccepted()
            && $request->query->getBoolean('nou');

        return $this->render('legacy/beneficiary_claim_portal.html.twig', [
            'user' => $user,
            'contact' => $contact,
            'contactUuid' => $contact->getUuid()->toRfc4122(),
            'latestClaim' => $startNew ? null : $latestClaim,
            'rejectedHistoryClaim' => $startNew ? $latestClaim : null,
            'openingFeeCents' => $openingFeeCents,
            'prefillTaxId' => trim((string) ($user->getTaxId() ?? '')),
        ]);
    }

    #[Route('/pujar-documents', name: 'app_profile_legacy_claim_upload', methods: ['POST'])]
    public function uploadDocuments(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = $this->resolveAuthorizedContactOrThrow($user, $uuid);
        $contactUuid = $contact->getUuid()->toRfc4122();

        if (!$this->isCsrfTokenValid('legacy_beneficiary_claim_upload_' . $contactUuid, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Torna-ho a provar.');

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        if ($request->request->get('confirm_death_declaration') !== '1') {
            $this->addFlash('error', 'Cal confirmar la declaració responsable d\'obertura per defunció i acceptar la finestra preventiva de 72 hores.');

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        $taxId = strtoupper(trim((string) $request->request->get('claimant_tax_id', '')));
        $deathFile = $request->files->get('death_certificate');
        $titleFile = $request->files->get('succession_title');

        if (!$deathFile instanceof UploadedFile || !$titleFile instanceof UploadedFile) {
            $this->addFlash('error', 'Cal adjuntar obligatòriament tant el Certificat Oficial de Defunció com el Títol Successori.');

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        try {
            $claim = $this->workflowService->submitBeneficiaryDirectClaim(
                contact: $contact,
                actor: $user,
                claimantTaxId: $taxId,
                deathCertificateFile: $deathFile,
                successionTitleFile: $titleFile,
                clientIp: $request->getClientIp(),
            );

            $this->addFlash('success', sprintf(
                'Expedient %s registrat sota custòdia privada (SHA-256). Abona la taxa d\'obertura per activar la finestra de 72 h i la revisió.',
                $claim->getClaimReference()
            ));
        } catch (DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
    }

    #[Route('/cancelar', name: 'app_profile_legacy_claim_cancel', methods: ['POST'])]
    public function cancelDraft(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = $this->resolveAuthorizedContactOrThrow($user, $uuid);
        $contactUuid = $contact->getUuid()->toRfc4122();

        if (!$this->isCsrfTokenValid('legacy_beneficiary_claim_cancel_' . $contactUuid, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        $claim = $this->claimRepository->findLatestByLegacyContact($contact);
        if (!$claim instanceof SuccessionClaim) {
            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        try {
            $this->workflowService->cancelPendingDraft($claim, $user);
            $this->addFlash('info', 'Esborrany documental cancel·lat. Pots tornar a adjuntar la documentació si ho necessites.');
        } catch (DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
    }

    #[Route('/pagar', name: 'app_profile_legacy_claim_checkout', methods: ['POST'])]
    public function checkoutOpeningFee(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = $this->resolveAuthorizedContactOrThrow($user, $uuid);
        $contactUuid = $contact->getUuid()->toRfc4122();

        if (!$this->isCsrfTokenValid('legacy_beneficiary_claim_pay_' . $contactUuid, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        $claimUuid = trim((string) $request->request->get('claim_uuid', ''));
        $claim = $claimUuid !== '' && Uuid::isValid($claimUuid)
            ? $this->claimRepository->findByUuid($claimUuid)
            : $this->claimRepository->findLatestByLegacyContact($contact);

        if (!$claim instanceof SuccessionClaim || $claim->getLegacyContact()?->getId() !== $contact->getId()) {
            $this->addFlash('error', 'No s\'ha localitzat l\'expedient documental per abonar.');

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        if ($claim->getStatus()->isResolved() || $claim->getOpeningFeePaidCents() > 0) {
            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }

        $successUrl = $this->generateUrl('app_profile_legacy_claim_return', [
            'uuid' => $contactUuid,
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('app_profile_legacy_claim_portal', ['uuid' => $contactUuid], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $checkoutUrl = $this->checkoutService->startOpeningFeeCheckout($claim, $successUrl, $cancelUrl);

            return $this->redirect($checkoutUrl);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error en iniciar el pagament de la taxa d\'obertura: ' . $e->getMessage());

            return $this->redirectToRoute('app_profile_legacy_claim_portal', ['uuid' => $contactUuid]);
        }
    }

    #[Route('/retorn', name: 'app_profile_legacy_claim_return', methods: ['GET'])]
    public function paymentReturn(string $uuid): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = $this->resolveAuthorizedContactOrThrow($user, $uuid);

        $this->addFlash('success', 'Taxa d\'obertura abonada! S\'ha notificat preventivament al titular (finestra de seguretat de 72 h) i iniciat l\'anàlisi documental.');

        return $this->redirectToRoute('app_profile_legacy_claim_portal', [
            'uuid' => $contact->getUuid()->toRfc4122(),
        ]);
    }

    private function resolveAuthorizedContactOrThrow(User $user, string $uuid): LegacyContact
    {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException('Designació no trobada.');
        }

        $contact = $this->legacyContactRepository->findOneBy([
            'uuid' => Uuid::fromString($uuid),
        ]);

        if (!$contact instanceof LegacyContact || !$contact->isDesignatedUser($user)) {
            throw $this->createNotFoundException('No s\'ha trobat cap designació associada al teu compte.');
        }

        if ($user->getId() !== null && $contact->getOwner()->getId() === $user->getId()) {
            throw $this->createNotFoundException('Designació no vàlida per a aquest tràmit.');
        }

        return $contact;
    }

    private function redirectToProfileHeritage(): Response
    {
        return $this->redirect($this->generateUrl('app_profile') . '#herencia-digital');
    }
}

