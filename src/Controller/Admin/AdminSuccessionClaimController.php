<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\SuccessionClaimStatus;
use App\Repository\ItemRepository;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cua d'expedients successoris acotada estrictament al bloc GESTIÓ & CUSTÒDIA (ROLE_GESTOR).
 */
#[Route('/admin/gestions/herencies')]
#[IsGranted('ROLE_GESTOR')]
class AdminSuccessionClaimController extends AbstractController
{
    public function __construct(
        private readonly SuccessionClaimRepository $claimRepository,
        private readonly ItemRepository $itemRepository,
        private readonly SuccessionClaimWorkflowService $workflowService,
        private readonly FilesystemOperator $itemDocumentStorage,
    ) {
    }

    #[Route('', name: 'app_admin_gestions_herencies', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('q');
        $status = $request->query->get('status');
        $claims = $this->claimRepository->findFilteredQueue($search, $status);

        $allClaims = $this->claimRepository->findQueueOrdered();
        $counts = [
            'total' => count($allClaims),
            'pending_review' => 0,
            'hold_window' => 0,
            'resolved' => 0,
        ];
        foreach ($allClaims as $c) {
            if ($c->getStatus() === SuccessionClaimStatus::UNDER_HUMAN_REVIEW) {
                ++$counts['pending_review'];
            } elseif ($c->getStatus() === SuccessionClaimStatus::OWNER_NOTICE_WINDOW) {
                ++$counts['hold_window'];
            } elseif ($c->getStatus()->isResolved()) {
                ++$counts['resolved'];
            }
        }

        return $this->render('admin/succession/index.html.twig', [
            'claims' => $claims,
            'counts' => $counts,
            'currentSearch' => $search,
            'currentStatus' => $status ?: 'all',
            'statuses' => SuccessionClaimStatus::cases(),
        ]);
    }

    #[Route('/{uuid}', name: 'app_admin_gestions_herencia_show', methods: ['GET'])]
    public function show(string $uuid): Response
    {
        $claim = $this->findClaimOrThrow($uuid);
        $activeItems = $this->itemRepository->findActiveForLegacyInventory($claim->getDeceasedOwner());

        return $this->render('admin/succession/show.html.twig', [
            'claim' => $claim,
            'activeItems' => $activeItems,
        ]);
    }

    #[Route('/{uuid}/document/{kind}', name: 'app_admin_gestions_herencia_document', methods: ['GET'])]
    public function streamDocument(string $uuid, string $kind): Response
    {
        $claim = $this->findClaimOrThrow($uuid);

        $storageKey = match ($kind) {
            'defuncio' => $claim->getDeathCertificateStorageKey(),
            'titol' => $claim->getSuccessionTitleStorageKey(),
            default => throw $this->createNotFoundException('Tipus de document no reconegut.'),
        };

        if (!$this->itemDocumentStorage->fileExists($storageKey)) {
            throw $this->createNotFoundException('El document no es troba a la custòdia privada.');
        }

        $extension = strtolower((string) pathinfo($storageKey, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $filename = sprintf('%s_%s.%s', $claim->getClaimReference(), $kind, $extension);
        $storage = $this->itemDocumentStorage;

        $response = new StreamedResponse(function () use ($storage, $storageKey): void {
            $stream = $storage->readStream($storageKey);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    #[Route('/{uuid}/ai-retriatge', name: 'app_admin_gestions_herencia_retriatge', methods: ['POST'])]
    public function triggerAiPrescreening(string $uuid, Request $request): Response
    {
        $claim = $this->findClaimOrThrow($uuid);

        if (!$this->isCsrfTokenValid('succession_ai_' . $claim->getUuid()->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Testimoni de seguretat CSRF invàlid.');

            return $this->redirectToRoute('app_admin_gestions_herencia_show', ['uuid' => $uuid]);
        }

        $this->workflowService->runAiPrescreening($claim);
        $this->addFlash('success', 'Pre-triatge sobirà (Qwen-VL) executat sobre els dos documents custodiats.');

        return $this->redirectToRoute('app_admin_gestions_herencia_show', ['uuid' => $uuid]);
    }

    #[Route('/{uuid}/aprovar', name: 'app_admin_gestions_herencia_approve', methods: ['POST'])]
    public function approve(string $uuid, Request $request): Response
    {
        $claim = $this->findClaimOrThrow($uuid);
        /** @var User $reviewer */
        $reviewer = $this->getUser();

        if (!$this->isCsrfTokenValid('succession_decision_' . $claim->getUuid()->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Testimoni de seguretat CSRF invàlid.');

            return $this->redirectToRoute('app_admin_gestions_herencia_show', ['uuid' => $uuid]);
        }

        $notes = (string) $request->request->get('resolution_notes', '');

        try {
            $transferred = $this->workflowService->approveClaim($claim, $reviewer, $notes);
            $this->addFlash('success', sprintf(
                'Expedient %s aprovat correctament! S\'han traspassat %d bé(ns) a %s.',
                $claim->getClaimReference(),
                $transferred,
                $claim->getClaimantEmail()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_gestions_herencia_show', ['uuid' => $uuid]);
    }

    #[Route('/{uuid}/rebutjar', name: 'app_admin_gestions_herencia_reject', methods: ['POST'])]
    public function reject(string $uuid, Request $request): Response
    {
        $claim = $this->findClaimOrThrow($uuid);
        /** @var User $reviewer */
        $reviewer = $this->getUser();

        if (!$this->isCsrfTokenValid('succession_decision_' . $claim->getUuid()->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Testimoni de seguretat CSRF invàlid.');

            return $this->redirectToRoute('app_admin_gestions_herencia_show', ['uuid' => $uuid]);
        }

        $notes = (string) $request->request->get('resolution_notes', '');

        try {
            $this->workflowService->rejectClaim($claim, $reviewer, $notes);
            $this->addFlash('info', sprintf('Expedient %s rebutjat motivadament.', $claim->getClaimReference()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_gestions_herencia_show', ['uuid' => $uuid]);
    }

    private function findClaimOrThrow(string $uuid): SuccessionClaim
    {
        if (!\Symfony\Component\Uid\Uuid::isValid($uuid)) {
            throw $this->createNotFoundException('Identificador d\'expedient invàlid.');
        }

        $claim = $this->claimRepository->findByUuid($uuid);
        if (!$claim instanceof SuccessionClaim) {
            throw $this->createNotFoundException('Expedient successori no trobat.');
        }

        return $claim;
    }
}
