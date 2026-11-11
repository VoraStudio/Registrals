<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Security\Voter\ItemVoter;
use App\Service\Legacy\LegacyCertificateIssuerService;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/app/certificats')]
#[IsGranted('ROLE_USER')]
class UserCertificateController extends AbstractController
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly \App\Service\Certificate\CertificateIssuerService $certificateIssuer,
        private readonly ?\App\Service\Certificate\CertificateQrGeneratorService $qrGenerator = null,
        private readonly ?ItemRepository $itemRepository = null,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LegacyCertificateIssuerService $legacyCertificateIssuer = null,
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
            'user' => $user,
            'certificates' => $certificates,
            'metrics' => $metrics,
            'qrCodes' => $qrCodes,
            'legacyEligibility' => $this->legacyCertificateIssuer?->evaluateEmissionEligibility($user),
            'currentSearch' => $search,
            'currentMode' => $mode ?: 'all',
            'currentStatus' => $status ?: 'all',
            'modes' => CertificateMode::cases(),
            'statuses' => CertificateStatus::cases(),
        ]);
    }

    #[Route('/llegat/emetre', name: 'app_certificate_legacy_issue', methods: ['POST'])]
    public function issueLegacy(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_certificate_issue', $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Si us plau, torna-ho a provar.');

            return $this->redirectToRoute('app_certificates_index');
        }

        if ($this->legacyCertificateIssuer === null) {
            $this->addFlash('error', 'El servei d\'emissió de certificats d\'Inventari Global de Llegat no està disponible.');

            return $this->redirectToRoute('app_certificates_index');
        }

        try {
            $certificate = $this->legacyCertificateIssuer->issueLegacyInventoryCertificate(
                $user,
                clientIp: $request->getClientIp()
            );

            $this->addFlash('success', sprintf(
                'Certificat d\'Inventari Global de Llegat %s preparat (%d béns custodiats i doble codi QR actiu).',
                $certificate->getCertificateNumber(),
                count($certificate->getIncludedItemCuisSnapshot() ?? [])
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_certificates_index');
    }

    #[Route('/{uuid}/descarregar', name: 'app_certificate_direct_download', methods: ['GET'])]
    public function download(Certificate|string $uuid): Response
    {
        $certificate = $uuid instanceof Certificate
            ? $uuid
            : (Uuid::isValid((string) $uuid)
                ? $this->certificateRepository->findByUuid((string) $uuid)
                : (ctype_digit((string) $uuid) ? $this->certificateRepository->find((int) $uuid) : null));

        if (!$certificate instanceof Certificate) {
            throw $this->createNotFoundException('El certificat sol·licitat no s\'ha trobat.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);

        $isIssuer = $certificate->getIssuedBy()->getId() === $currentUser->getId();
        $isOwner = $certificate->getItem()?->getOwner()->getId() === $currentUser->getId();

        if (!$isAdmin && !$isIssuer && !$isOwner) {
            throw $this->createAccessDeniedException(
                'Per motius de privacitat i protecció de dades (RGPD), només l\'emissor o el titular legítim del bé poden descarregar aquest certificat.'
            );
        }

        // Si és un esborrany, regenerem el PDF per reflectir millores de plantilla i fotografia
        if ($certificate->getMode()->isDraft() && !$certificate->isLegacyInventory()) {
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
        $filenamePrefix = $certificate->getItem()?->getCui() ?? 'LEGAT';
        $filename = sprintf('%s_%s.pdf', $filenamePrefix, $certificate->getCertificateNumber());

        return new Response($pdfBinary, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Content-Length' => strlen($pdfBinary),
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }

    /**
     * Emet certificats digitals (esborrany o oficial) per a un lot de béns seleccionats.
     */
    #[Route('/batch/issue', name: 'app_certificate_batch_issue', methods: ['POST'])]
    public function batchIssue(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('batch_certificate_issue', $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_dashboard');
        }

        $cuis = array_values(array_unique(array_filter(array_map(
            static fn (mixed $cui): string => trim((string) $cui),
            (array) $request->request->all('cuis')
        ))));

        if (empty($cuis) || $this->itemRepository === null) {
            $this->addFlash('warning', 'No s\'ha seleccionat cap bé per emetre certificats.');
            return $this->redirectToRoute('app_dashboard');
        }

        $rawMode = (string) $request->request->get('mode', CertificateMode::DRAFT->value);
        $mode = CertificateMode::tryFrom($rawMode) ?? CertificateMode::DRAFT;

        $issuedCerts = [];
        $skippedCount = 0;
        $quotaError = null;

        foreach ($cuis as $cui) {
            $item = $this->itemRepository->findOneBy(['cui' => $cui]);
            if (!$item || !$this->isGranted(ItemVoter::CERTIFY, $item)) {
                ++$skippedCount;
                continue;
            }

            try {
                $certificate = $this->certificateIssuer->issueCertificate($item, $user, $mode);
                $issuedCerts[] = $certificate;

                $this->activityLogger?->log(
                    user: $user,
                    action: UserActivityAction::CERTIFICATE_ISSUED,
                    description: sprintf('Emissió per lot de certificat %s (%s) per al bé %s', $certificate->getCertificateNumber(), $mode->label(), $item->getCui()),
                    details: [
                        'certificate_number' => $certificate->getCertificateNumber(),
                        'cui' => $item->getCui(),
                        'mode' => $mode->value,
                        'is_official' => $mode->isOfficial(),
                        'uuid' => $certificate->getUuid()->toRfc4122(),
                        'batch_size' => count($cuis),
                    ]
                );
            } catch (\RuntimeException $e) {
                $quotaError = $e->getMessage();
                break;
            } catch (\Throwable $e) {
                ++$skippedCount;
            }
        }

        if (empty($issuedCerts)) {
            $this->addFlash('error', $quotaError ?? 'No s\'ha pogut emetre cap certificat per als béns seleccionats (verifica que estiguin actius i sense traspàs pendent).');
            return $this->redirectToRoute('app_dashboard');
        }

        $msg = sprintf(
            'S\'han emès o recuperat %d certificat(s) en modalitat %s.',
            count($issuedCerts),
            $mode->label()
        );
        if ($skippedCount > 0) {
            $msg .= sprintf(' (%d bé(ns) omesos per no estar actius).', $skippedCount);
        }
        if ($quotaError !== null) {
            $msg .= ' ' . $quotaError;
        }

        $this->addFlash('success', $msg);

        return $this->redirectToRoute('app_certificates_index');
    }
}
