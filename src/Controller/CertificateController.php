<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Repository\CertificateRepository;
use App\Service\Certificate\CertificateIssuerService;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/items/{id}/certificates')]
#[IsGranted('ROLE_USER')]
class CertificateController extends AbstractController
{
    public function __construct(
        private readonly CertificateIssuerService $certificateIssuer,
        private readonly CertificateRepository $certificateRepository,
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly ?UserActivityLogger $activityLogger = null
    ) {
    }

    #[Route('/issue', name: 'app_item_certificate_issue', methods: ['POST'])]
    public function issue(Item $item, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ITEM_CERTIFY', $item);

        /** @var User $user */
        $user = $this->getUser();

        // Validació CSRF
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('issue_certificate_' . $item->getId(), $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        $rawMode = (string) $request->request->get('mode', CertificateMode::DRAFT->value);
        $mode = CertificateMode::tryFrom($rawMode) ?? CertificateMode::DRAFT;

        try {
            $certificate = $this->certificateIssuer->issueCertificate($item, $user, $mode);

            // Registre a la base d'auditoria forense (registrals_audit)
            $this->activityLogger?->log(
                user: $user,
                action: UserActivityAction::CERTIFICATE_ISSUED,
                description: sprintf('Emissió de certificat %s (%s) per al bé %s', $certificate->getCertificateNumber(), $mode->label(), $item->getCui()),
                details: [
                    'certificate_number' => $certificate->getCertificateNumber(),
                    'cui' => $item->getCui(),
                    'mode' => $mode->value,
                    'is_official' => $mode->isOfficial(),
                    'uuid' => $certificate->getUuid()->toRfc4122(),
                ]
            );

            $downloadUrl = $this->generateUrl('app_item_certificate_download', [
                'id' => $item->getId(),
                'certId' => $certificate->getId(),
            ]);

            $request->getSession()->set('auto_open_certificate_url', $downloadUrl);

            if ($mode->isOfficial()) {
                $this->addFlash('success', sprintf(
                    'S\'ha emès amb èxit el Certificat Oficial Notarial %s amb segellat de temps eIDAS.',
                    $certificate->getCertificateNumber()
                ));
            } else {
                $this->addFlash('success', sprintf(
                    'Esborrany Informatiu %s llest per a visualització.',
                    $certificate->getCertificateNumber()
                ));
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error en l\'emissió del certificat: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
    }

    #[Route('/{certId}/download', name: 'app_item_certificate_download', methods: ['GET'])]
    public function download(Item $item, int $certId): Response
    {
        $this->denyAccessUnlessGranted('ITEM_VIEW', $item);

        $certificate = $this->certificateRepository->find($certId);
        if (!$certificate instanceof Certificate || $certificate->getItem()->getId() !== $item->getId()) {
            throw $this->createNotFoundException('El certificat sol·licitat no existeix o no pertany a aquest bé.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
        if (!$isAdmin && $certificate->getIssuedBy()->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Per motius de privacitat i protecció de dades (RGPD), només l\'emissor titular pot descarregar aquest certificat.');
        }

        $storagePath = $certificate->getPdfFilePath();
        if (!$this->itemDocumentStorage->fileExists($storagePath)) {
            throw $this->createNotFoundException('El document PDF no s\'ha trobat al repositori protegit de custòdia.');
        }

        $pdfBinary = $this->itemDocumentStorage->read($storagePath);
        $filename = sprintf('%s_%s.pdf', $item->getCui(), $certificate->getCertificateNumber());

        return new Response($pdfBinary, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Content-Length' => strlen($pdfBinary),
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }
}
