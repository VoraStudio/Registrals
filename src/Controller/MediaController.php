<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ItemDocument;
use App\Security\Voter\ItemVoter;
use App\Service\Storage\SecureDocumentStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MediaController extends AbstractController
{
    /**
     * Serveix un document o fotografia de custòdia de forma segura mitjançant StreamedResponse,
     * verificant prèviament que l'usuari actual és el propietari legítim de l'ítem associat.
     */
    #[Route('/media/document/{uuid}', name: 'app_media_document', methods: ['GET'])]
    public function serveDocument(
        string $uuid,
        \App\Repository\ItemDocumentRepository $documentRepository,
        SecureDocumentStorageService $storageService
    ): Response {
        $document = $documentRepository->findOneBy(['uuid' => $uuid]);

        if (!$document) {
            throw new NotFoundHttpException('El document sol·licitat no s\'ha trobat.');
        }

        $item = $document->getItem();

        // Verificació d'autorització mitjançant ItemVoter
        $this->denyAccessUnlessGranted(ItemVoter::VIEW, $item);

        try {
            $stream = $storageService->readDocumentStream($document);
        } catch (\Throwable $e) {
            throw new NotFoundHttpException('El fitxer sol·licitat no s\'ha trobat al magatzem de custòdia.');
        }

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $document->getOriginalFilename()
        );

        $response->headers->set('Content-Type', $document->getMimeType());
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Length', (string) $document->getFileSizeBytes());
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Registrals-SHA256', $document->getSha256Hash());

        return $response;
    }
}
