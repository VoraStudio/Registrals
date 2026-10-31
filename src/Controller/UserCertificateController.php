<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Certificate;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use App\Repository\CertificateRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/certificats')]
#[IsGranted('ROLE_USER')]
class UserCertificateController extends AbstractController
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly \App\Service\Certificate\CertificateIssuerService $certificateIssuer,
        private readonly ?\App\Service\Certificate\CertificateQrGeneratorService $qrGenerator = null
    ) {
    }

    #[Route('', name: 'app_certificates_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $search = $request->query->get('q');
        $mode = $request->query->get('mode');
        $status = $request->query->get('status');

        $certificates = $this->certificateRepository->findForUser(
            $user,
            $search,
            $mode,
            $status
        );

        $metrics = $this->certificateRepository->countMetricsForUser($user);

        $qrCodes = [];
        if ($this->qrGenerator) {
            foreach ($certificates as $cert) {
                try {
                    $verificationUrl = $this->generateUrl(
                        'app_verify_certificate',
                        ['uuid' => $cert->getUuid()->toRfc4122()],
                        \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                    );
                    $qrCodes[$cert->getId()] = $this->qrGenerator->generateDataUri($verificationUrl, 100);
                } catch (\Throwable) {
                    $qrCodes[$cert->getId()] = null;
                }
            }
        }

        return $this->render('certificate/index.html.twig', [
            'certificates' => $certificates,
            'metrics' => $metrics,
            'qrCodes' => $qrCodes,
            'currentSearch' => $search,
            'currentMode' => $mode ?: 'all',
            'currentStatus' => $status ?: 'all',
            'modes' => CertificateMode::cases(),
            'statuses' => CertificateStatus::cases(),
        ]);
    }

    #[Route('/{id}/descarregar', name: 'app_certificate_direct_download', methods: ['GET'])]
    public function download(Certificate $certificate): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);

        $isIssuer = $certificate->getIssuedBy()->getId() === $currentUser->getId();
        $isOwner = $certificate->getItem()->getOwner()->getId() === $currentUser->getId();

        if (!$isAdmin && !$isIssuer && !$isOwner) {
            throw $this->createAccessDeniedException(
                'Per motius de privacitat i protecció de dades (RGPD), només l\'emissor o el titular legítim del bé poden descarregar aquest certificat.'
            );
        }

        // Si és un esborrany, regenerem el PDF per reflectir millores de plantilla i fotografia
        if ($certificate->getMode()->isDraft()) {
            try {
                $this->certificateIssuer->regeneratePdf($certificate);
            } catch (\Throwable) {
                // Fallback: se serveix el PDF existent si la regeneració no és possible
            }
        }

        $storagePath = $certificate->getPdfFilePath();
        if (!$this->itemDocumentStorage->fileExists($storagePath)) {
            throw $this->createNotFoundException('El document PDF del certificat no s\'ha trobat a la custòdia protegida.');
        }

        $pdfBinary = $this->itemDocumentStorage->read($storagePath);
        $filename = sprintf('%s_%s.pdf', $certificate->getItem()->getCui(), $certificate->getCertificateNumber());

        return new Response($pdfBinary, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Content-Length' => strlen($pdfBinary),
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }
}
