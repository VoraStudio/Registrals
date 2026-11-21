<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdCampaign;
use App\Repository\AdCampaignRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/landing/publicitat')]
#[IsGranted('ROLE_ADMIN')]
class AdminAdCampaignController extends AbstractController
{
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_VIDEO_MIMES = ['video/mp4', 'video/webm'];

    #[Route('', name: 'app_admin_ads', methods: ['GET'])]
    public function index(AdCampaignRepository $repository): Response
    {
        return $this->render('admin/ad_campaign/index.html.twig', [
            'campaigns' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/nova', name: 'app_admin_ads_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_ad_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_ads');
        }

        $title = trim((string) $request->request->get('title'));
        $description = trim((string) $request->request->get('description'));
        $duration = max(2, min(120, (int) $request->request->get('duration_seconds', 8)));
        $sortOrder = (int) $request->request->get('sort_order', 0);
        $isActive = $request->request->getBoolean('is_active', true);

        if ($title === '' || $description === '') {
            $this->addFlash('error', 'El títol i la descripció són obligatoris.');
            return $this->redirectToRoute('app_admin_ads');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('media');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Cal pujar una imatge, GIF animat o vídeo.');
            return $this->redirectToRoute('app_admin_ads');
        }

        $upload = $this->storeUploadedMedia($file);
        if ($upload === null) {
            $this->addFlash('error', 'Format de fitxer no admès. Formats permesos: JPG, PNG, WebP, GIF, MP4 o WebM.');
            return $this->redirectToRoute('app_admin_ads');
        }

        try {
            $startsAtRaw = trim((string) $request->request->get('starts_at'));
            $endsAtRaw = trim((string) $request->request->get('ends_at'));
            $startsAt = $startsAtRaw !== '' ? new DateTimeImmutable($startsAtRaw) : new DateTimeImmutable();
            $endsAt = $endsAtRaw !== '' ? new DateTimeImmutable($endsAtRaw) : null;
        } catch (\Throwable) {
            $this->addFlash('error', 'Format de data invàlid.');
            return $this->redirectToRoute('app_admin_ads');
        }

        $campaign = new AdCampaign(
            title: $title,
            description: $description,
            mediaPath: $upload['path'],
            mediaType: $upload['type'],
            durationSeconds: $duration,
            startsAt: $startsAt,
            endsAt: $endsAt,
            isActive: $isActive,
            sortOrder: $sortOrder
        );

        $entityManager->persist($campaign);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Campanya "%s" creada correctament.', $title));

        return $this->redirectToRoute('app_admin_ads');
    }

    #[Route('/{id}/editar', name: 'app_admin_ads_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        AdCampaignRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_ad_edit_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_ads');
        }

        $campaign = $repository->find($id);
        if (!$campaign) {
            throw $this->createNotFoundException('Campanya no trobada.');
        }

        $title = trim((string) $request->request->get('title'));
        $description = trim((string) $request->request->get('description'));
        if ($title === '' || $description === '') {
            $this->addFlash('error', 'El títol i la descripció són obligatoris.');
            return $this->redirectToRoute('app_admin_ads');
        }

        try {
            $startsAtRaw = trim((string) $request->request->get('starts_at'));
            $endsAtRaw = trim((string) $request->request->get('ends_at'));
            if ($startsAtRaw !== '') {
                $campaign->setStartsAt(new DateTimeImmutable($startsAtRaw));
            }
            $campaign->setEndsAt($endsAtRaw !== '' ? new DateTimeImmutable($endsAtRaw) : null);
        } catch (\Throwable) {
            $this->addFlash('error', 'Format de data invàlid.');
            return $this->redirectToRoute('app_admin_ads');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('media');
        if ($file instanceof UploadedFile) {
            $upload = $this->storeUploadedMedia($file);
            if ($upload === null) {
                $this->addFlash('error', 'Format de fitxer no admès.');
                return $this->redirectToRoute('app_admin_ads');
            }
            $this->deleteMediaFile($campaign->getMediaPath());
            $campaign->setMediaPath($upload['path']);
            $campaign->setMediaType($upload['type']);
        }

        $campaign->setTitle($title);
        $campaign->setDescription($description);
        $campaign->setDurationSeconds(max(2, min(120, (int) $request->request->get('duration_seconds', 8))));
        $campaign->setSortOrder((int) $request->request->get('sort_order', 0));
        $campaign->setIsActive($request->request->getBoolean('is_active', false));

        $entityManager->flush();

        $this->addFlash('success', sprintf('Campanya "%s" actualitzada.', $title));

        return $this->redirectToRoute('app_admin_ads');
    }

    #[Route('/{id}/toggle', name: 'app_admin_ads_toggle', methods: ['POST'])]
    public function toggle(
        int $id,
        Request $request,
        AdCampaignRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_ad_toggle_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_ads');
        }

        $campaign = $repository->find($id);
        if ($campaign) {
            $campaign->setIsActive(!$campaign->isActive());
            $entityManager->flush();
            $this->addFlash('success', sprintf(
                'Campanya "%s" %s.',
                $campaign->getTitle(),
                $campaign->isActive() ? 'activada' : 'desactivada'
            ));
        }

        return $this->redirectToRoute('app_admin_ads');
    }

    #[Route('/{id}/eliminar', name: 'app_admin_ads_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        AdCampaignRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_ad_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_ads');
        }

        $campaign = $repository->find($id);
        if ($campaign) {
            $this->deleteMediaFile($campaign->getMediaPath());
            $entityManager->remove($campaign);
            $entityManager->flush();
            $this->addFlash('success', 'Campanya eliminada.');
        }

        return $this->redirectToRoute('app_admin_ads');
    }

    /**
     * @return array{path: string, type: 'image'|'video'}|null
     */
    private function storeUploadedMedia(UploadedFile $file): ?array
    {
        $mime = (string) ($file->getMimeType() ?? $file->getClientMimeType());
        $isImage = in_array($mime, self::ALLOWED_IMAGE_MIMES, true);
        $isVideo = in_array($mime, self::ALLOWED_VIDEO_MIMES, true);

        if (!$isImage && !$isVideo) {
            return null;
        }

        $ext = $file->guessExtension() ?: ($isVideo ? 'mp4' : 'png');
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDir = $this->getParameter('kernel.project_dir') . '/web/uploads/ads';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $file->move($targetDir, $filename);

        return [
            'path' => 'uploads/ads/' . $filename,
            'type' => $isVideo ? 'video' : 'image',
        ];
    }

    private function deleteMediaFile(string $relativePath): void
    {
        $clean = ltrim($relativePath, '/');
        if (!str_starts_with($clean, 'uploads/ads/')) {
            return;
        }
        $fullPath = $this->getParameter('kernel.project_dir') . '/web/' . $clean;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
