<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\KycStatus;
use App\Service\Identity\KycVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/perfil/verificacio')]
#[IsGranted('ROLE_USER')]
class KycController extends AbstractController
{
    public function __construct(
        private readonly KycVerificationService $kycService
    ) {
    }

    #[Route('/iniciar', name: 'app_kyc_start', methods: ['POST'])]
    public function start(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('kyc_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_profile');
        }

        if ($user->getKycStatus() === KycStatus::APPROVED) {
            $this->addFlash('info', 'La teva identitat oficial ja està verificada.');
            return $this->redirectToRoute('app_profile');
        }

        if (!$this->kycService->canStartWithoutCheckout($user)) {
            $this->addFlash('warning', 'El Pla Free requereix abonar prèviament la taxa de verificació abans d\'iniciar el procés.');
            return $this->redirectToRoute('app_profile');
        }

        $baseCallback = $this->generateUrl('app_kyc_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $wizardUrlBack = $baseCallback . '?requestId=<requestId>&referenceId=<referenceId>&status=<status>&actor=<actor>';

        try {
            $session = $this->kycService->initiateVerification($user, $wizardUrlBack, $request->getClientIp());

            return $this->redirect($session->wizardUrl);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No s\'ha pogut iniciar la verificació d\'identitat: ' . $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }
    }

    #[Route('/retorn', name: 'app_kyc_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $storedRequestId = (string) ($user->getSignaturitKycId() ?? '');
        $queryRequestId = trim((string) $request->query->get('requestId', ''));

        if ($storedRequestId === '' || ($queryRequestId !== '' && !str_contains($queryRequestId, '<') && $queryRequestId !== $storedRequestId)) {
            $this->addFlash('warning', 'No s\'ha trobat cap sessió KYC activa coincident al teu perfil.');
            return $this->redirectToRoute('app_profile');
        }

        try {
            $verdict = $this->kycService->syncUserVerificationStatus($user, $request->getClientIp(), 'callback');

            match ($verdict->status) {
                KycStatus::APPROVED => $this->addFlash('success', 'Identitat verificada oficialment amb Namirial OnBoarding (eIDV). Ja pots emetre certificats oficials.'),
                KycStatus::REJECTED => $this->addFlash('error', 'La verificació d\'identitat ha estat rebutjada: ' . ($verdict->reason ?? 'Revisa que nom i cognoms coincideixin exactament amb el document oficial.')),
                default => $this->addFlash('info', 'La teva sol·licitud està en procés a Namirial OnBoarding. Pots actualitzar l\'estat en uns instants.'),
            };
        } catch (\Throwable $e) {
            $this->addFlash('warning', 'No s\'ha pogut comprovar encara el resultat final a Namirial: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/sincronitzar', name: 'app_kyc_sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('kyc_sync', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_profile');
        }

        try {
            $verdict = $this->kycService->syncUserVerificationStatus($user, $request->getClientIp(), 'manual_sync');

            match ($verdict->status) {
                KycStatus::APPROVED => $this->addFlash('success', 'Verificació confirmada favorablement (eIDAS / Namirial OnBoarding).'),
                KycStatus::REJECTED => $this->addFlash('error', 'La verificació ha resultat rebutjada: ' . ($verdict->reason ?? 'Dades o biometria no vàlides.')),
                default => $this->addFlash('info', sprintf('Estat actual a Namirial: %s. Torna-ho a comprovar un cop completat.', $verdict->providerStatus)),
            };
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile');
    }
}

