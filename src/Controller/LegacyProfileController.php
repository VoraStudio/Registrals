<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LegacyContact;
use App\Entity\User;
use App\Form\LegacyDesignationFormType;
use App\Repository\LegacyContactRepository;
use App\Service\Legacy\LegacyDesignationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/perfil/herencia')]
#[IsGranted('ROLE_USER')]
class LegacyProfileController extends AbstractController
{
    public function __construct(
        private readonly LegacyDesignationService $legacyService,
        private readonly LegacyContactRepository $legacyContactRepository,
    ) {
    }

    #[Route('/designar', name: 'app_profile_legacy_designate', methods: ['POST'])]
    public function designate(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(LegacyDesignationFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $firstError = 'Revisa les dades del formulari i marca la casella de consentiment exprés.';
            foreach ($form->getErrors(true) as $error) {
                $firstError = $error->getMessage();
                break;
            }
            $this->addFlash('error', $firstError);

            return $this->redirectToProfileHeritage();
        }

        try {
            $contact = $this->legacyService->designate(
                owner: $user,
                fullName: (string) $form->get('fullName')->getData(),
                email: (string) $form->get('email')->getData(),
                relationship: $form->get('relationship')->getData(),
                clientIp: $request->getClientIp(),
            );

            $this->addFlash('success', sprintf(
                'S\'ha enviat la notificació de transparència (Art. 14 RGPD) a %s (%s). Resta pendent d\'acceptació.',
                $contact->getFullName(),
                $contact->getEmail()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProfileHeritage();
    }

    #[Route('/{uuid}/reenviar', name: 'app_profile_legacy_resend', methods: ['POST'])]
    public function resend(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_resend_' . $uuid, $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToProfileHeritage();
        }

        $contact = $this->findOwnedContact($user, $uuid);
        if (!$contact instanceof LegacyContact) {
            throw $this->createNotFoundException('No s\'ha trobat la designació sol·licitada.');
        }

        try {
            $this->legacyService->resendInvitation($user, $contact);
            $this->addFlash('success', sprintf('S\'ha reenviat la invitació amb un nou enllaç temporal a %s.', $contact->getEmail()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProfileHeritage();
    }

    #[Route('/{uuid}/revocar', name: 'app_profile_legacy_revoke', methods: ['POST'])]
    public function revoke(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_revoke_' . $uuid, $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToProfileHeritage();
        }

        $contact = $this->findOwnedContact($user, $uuid);
        if (!$contact instanceof LegacyContact) {
            throw $this->createNotFoundException('No s\'ha trobat la designació sol·licitada.');
        }

        try {
            $revokedCerts = $this->legacyService->revoke($user, $contact);
            $msg = sprintf('S\'ha revocat la designació de %s.', $contact->getFullName());
            if ($revokedCerts > 0) {
                $msg .= sprintf(' %d certificat(s) d\'Inventari Global vinculats han quedat revocats automàticament.', $revokedCerts);
            }
            $this->addFlash('info', $msg);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProfileHeritage();
    }

    #[Route('/{uuid}/acceptar-rebuda', name: 'app_profile_legacy_incoming_accept', methods: ['POST'])]
    public function acceptIncoming(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_incoming_accept_' . $uuid, $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToProfileHeritage();
        }

        $contact = $this->findContactByUuid($uuid);
        if (!$contact instanceof LegacyContact) {
            throw $this->createNotFoundException('No s\'ha trobat la designació sol·licitada.');
        }

        try {
            $this->legacyService->accept($contact, $user, $request->getClientIp());
            $this->addFlash('success', sprintf(
                'Has confirmat el teu consentiment com a Persona de Confiança de %s.',
                $contact->getOwner()->getFullName()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProfileHeritage();
    }

    #[Route('/{uuid}/refusar-rebuda', name: 'app_profile_legacy_incoming_decline', methods: ['POST'])]
    public function declineIncoming(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_incoming_decline_' . $uuid, $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToProfileHeritage();
        }

        $contact = $this->findContactByUuid($uuid);
        if (!$contact instanceof LegacyContact) {
            throw $this->createNotFoundException('No s\'ha trobat la designació sol·licitada.');
        }

        try {
            $this->legacyService->declineByRecipient($contact, $user, $request->getClientIp());
            $this->addFlash('info', sprintf(
                'Has declinat la designació rebuda de %s.',
                $contact->getOwner()->getFullName()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProfileHeritage();
    }

    #[Route('/{uuid}/renunciar', name: 'app_profile_legacy_incoming_renounce', methods: ['POST'])]
    public function renounceIncoming(string $uuid, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_incoming_renounce_' . $uuid, $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToProfileHeritage();
        }

        $contact = $this->findContactByUuid($uuid);
        if (!$contact instanceof LegacyContact) {
            throw $this->createNotFoundException('No s\'ha trobat la designació sol·licitada.');
        }

        try {
            $this->legacyService->renounceByBeneficiary($contact, $user, $request->getClientIp());
            $this->addFlash('info', sprintf(
                'Has renunciat al rol de Persona de Confiança de %s i se n\'ha notificat el titular.',
                $contact->getOwner()->getFullName()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProfileHeritage();
    }

    private function findOwnedContact(User $owner, string $uuid): ?LegacyContact
    {
        $contact = $this->findContactByUuid($uuid);

        if (!$contact instanceof LegacyContact || $contact->getOwner()->getId() !== $owner->getId()) {
            return null;
        }

        return $contact;
    }

    private function findContactByUuid(string $uuid): ?LegacyContact
    {
        if (!\Symfony\Component\Uid\Uuid::isValid($uuid)) {
            return null;
        }

        return $this->legacyContactRepository->findOneBy([
            'uuid' => \Symfony\Component\Uid\Uuid::fromString($uuid),
        ]);
    }

    private function redirectToProfileHeritage(): Response
    {
        return $this->redirect($this->generateUrl('app_profile') . '#herencia-digital');
    }
}

