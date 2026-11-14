<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Item\MobileHandoffService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controlador del Pont QR (Mobile Handoff).
 * Permet transferir fotos directament des de la càmera del telèfon cap al formulari del PC.
 */
class MobileHandoffController extends AbstractController
{
    public function __construct(
        private readonly MobileHandoffService $handoffService,
    ) {
    }

    /**
     * Inicialitza una sessió de handoff i retorna el codi QR en SVG per al PC.
     */
    #[Route('/app/items/mobile-handoff/init', name: 'app_mobile_handoff_init', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function initSession(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Determinem la URL base (prioritzant la IP de xarxa per a accés des del mòbil)
        $host = $request->getHttpHost();
        $scheme = $request->getScheme();
        $baseUrl = sprintf('%s://%s', $scheme, $host);

        $target = $request->getPayload()->getString('target') ?: $request->query->getString('target', 'photo');
        if (!in_array($target, ['photo', 'invoice'], true)) {
            $target = 'photo';
        }

        $plan = $user->getPlan();
        $planLimit = $user->isAdmin()
            ? 20
            : ($target === 'invoice'
                ? ($plan?->getMaxEvidencesPerItem() ?? 1)
                : ($plan?->getMaxPhotosPerItem() ?? 1));
        $effectivePlanLimit = $planLimit <= 0 ? 20 : $planLimit;
        $requestedRemaining = $request->getPayload()->getInt('remainingSlots', $effectivePlanLimit);
        $maxUploads = max(1, min($effectivePlanLimit, $requestedRemaining));

        $session = $this->handoffService->createSession($user, $baseUrl, $target, $maxUploads);

        return $this->json([
            'success' => true,
            'token' => $session['token'],
            'url' => $session['url'],
            'qrDataUri' => $session['qrDataUri'],
            'expiresAt' => $session['expiresAt'],
            'target' => $session['target'],
            'maxUploads' => $session['maxUploads'],
        ]);
    }

    /**
     * Consulta l'estat de la sessió des del PC (polling cada 2s).
     */
    #[Route('/app/items/mobile-handoff/status/{token}', name: 'app_mobile_handoff_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function checkStatus(string $token): JsonResponse
    {
        $session = $this->handoffService->getSession($token);

        if (!$session) {
            return $this->json([
                'success' => false,
                'status' => 'expired',
                'error' => 'La sessió ha caducat.',
            ]);
        }

        return $this->json([
            'success' => true,
            'status' => $session['status'],
            'target' => $session['target'] ?? 'photo',
            'photoOriginalName' => $session['photoOriginalName'] ?? null,
            'uploadCount' => (int) ($session['uploadCount'] ?? 0),
            'maxUploads' => (int) ($session['maxUploads'] ?? 1),
        ]);
    }

    /**
     * Descarrega la imatge temporal sincronitzada per injectar-la al formulari del PC.
     */
    #[Route('/app/items/mobile-handoff/download/{token}', name: 'app_mobile_handoff_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadPhoto(string $token): Response
    {
        $session = $this->handoffService->getSession($token);
        if (!$session || $session['status'] !== 'completed') {
            throw $this->createNotFoundException('Fotografia no disponible o sessió no completada.');
        }

        $photoPath = $this->handoffService->getPhotoPath($token);
        if (!$photoPath || !file_exists($photoPath)) {
            throw $this->createNotFoundException('L\'arxiu de la fotografia ha caducat.');
        }

        $response = new BinaryFileResponse($photoPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $session['photoOriginalName'] ?? 'foto_mobil.jpg'
        );
        $response->headers->set('Content-Type', $session['photoMimeType'] ?? 'image/jpeg');

        return $response;
    }

    /**
     * Pantalla pública mòbil de captura (enllaçada pel codi QR).
     */
    #[Route('/handoff/{token}', name: 'app_mobile_handoff_capture', methods: ['GET'])]
    public function mobileCapture(string $token): Response
    {
        $session = $this->handoffService->getSession($token);

        if (!$session) {
            return $this->render('item/mobile_handoff.html.twig', [
                'token' => $token,
                'isExpired' => true,
                'session' => null,
            ]);
        }

        return $this->render('item/mobile_handoff.html.twig', [
            'token' => $token,
            'isExpired' => false,
            'session' => $session,
        ]);
    }

    /**
     * Endpoint d'enviament de la fotografia des del telèfon mòbil.
     */
    #[Route('/handoff/{token}/upload', name: 'app_mobile_handoff_upload', methods: ['POST'])]
    public function uploadFromMobile(string $token, Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('photo');

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'success' => false,
                'error' => 'No s\'ha rebut cap fitxer de fotografia.',
            ], Response::HTTP_BADREQUEST);
        }

        $result = $this->handoffService->saveUploadedPhoto($token, $file);

        if (!$result['success']) {
            return $this->json($result, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'success' => true,
            'message' => 'Fotografia enviada amb èxit cap al formulari del PC.',
            'uploadCount' => $result['uploadCount'] ?? 1,
            'maxUploads' => $result['maxUploads'] ?? 1,
            'remaining' => $result['remaining'] ?? 0,
        ]);
    }

    /**
     * Finalitza manualment la sessió des del mòbil quan l'usuari decideix no pujar més fitxers.
     */
    #[Route('/handoff/{token}/finish', name: 'app_mobile_handoff_finish', methods: ['POST'])]
    public function finishFromMobile(string $token): JsonResponse
    {
        $finished = $this->handoffService->finishSession($token);

        return $this->json([
            'success' => $finished,
            'status' => $finished ? 'finished' : 'expired',
        ]);
    }
}
