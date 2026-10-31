<?php

declare(strict_types=1);

namespace App\Service\Certificate;

use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use App\Infrastructure\Adapter\Signature\SignaturitTimestampInterface;
use App\Repository\CertificateRepository;
use App\Service\ItemFingerprintCalculator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Servei d'emissió, maquetació i custòdia de certificats (Esborrany vs. Oficial).
 */
class CertificateIssuerService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PdfRendererService $pdfRenderer,
        private readonly CertificateQrGeneratorService $qrGenerator,
        private readonly ItemFingerprintCalculator $fingerprintCalculator,
        private readonly CertificateRepository $certificateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly KernelInterface $kernel,
        private readonly ?SignaturitTimestampInterface $signaturitClient = null,
        private readonly string $defaultBaseUrl = 'https://registrals.cat',
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Emet un nou certificat per a un bé en la modalitat sol·licitada.
     *
     * @param Item $item El bé a certificar
     * @param User $issuer L'usuari titular que sol·licita l'emissió
     * @param CertificateMode $mode Mode DRAFT (gratuït) o OFFICIAL (segellat)
     * @return Certificate L'entitat persistida amb el PDF custodiat
     */
    public function issueCertificate(Item $item, User $issuer, CertificateMode $mode): Certificate
    {
        // 1. Validació de quotes i permisos per a la modalitat Oficial
        if ($mode->isOfficial()) {
            $plan = $issuer->getPlan();
            $isAdmin = in_array('ROLE_ADMIN', $issuer->getRoles(), true);

            if (!$isAdmin && $plan !== null && !$plan->isAllowOfficialCertificates()) {
                throw new RuntimeException(
                    'El teu pla actual no permet l\'emissió de certificats oficials. Passa al Pla Plus o Unlimited o contracta un certificat solt.'
                );
            }

            if (!$isAdmin && $plan !== null) {
                $quota = $plan->getMonthlyCertificatesQuota();
                if ($quota !== -1 && $issuer->getCertificatesIssuedCount() >= $quota) {
                    throw new RuntimeException(
                        sprintf('Has assolit el límit mensual de %d certificats oficials del teu pla.', $quota)
                    );
                }
            }
        }

        // 2. Càlcul del fingerprint mestre del bé en aquest moment exacte
        $itemFingerprint = $this->fingerprintCalculator->calculateForItem($item);

        // 2.1. Comprovació d'idempotència: si ja existeix un certificat vigent emès per aquest usuari amb aquest fingerprint i modalitat, el retornem
        $existing = $this->certificateRepository->findOneBy([
            'item' => $item,
            'issuedBy' => $issuer,
            'mode' => $mode,
            'itemFingerprintAtIssue' => $itemFingerprint,
            'status' => CertificateStatus::VALID,
        ]);

        if ($existing instanceof Certificate) {
            $this->logger?->info('Reutilitzat certificat vigent existent per al mateix fingerprint', [
                'certificate_number' => $existing->getCertificateNumber(),
                'mode' => $mode->value,
                'cui' => $item->getCui(),
            ]);

            return $existing;
        }

        // 3. Generació del número oficial correlatiu (ex: CERT-2026-000001)
        $certificateNumber = $this->certificateRepository->getNextCertificateNumber();
        $certificateUuid = Uuid::v7();

        // 4. Generació del codi QR de validació pública
        $verificationUrl = rtrim($this->defaultBaseUrl, '/') . '/verify/' . $certificateUuid->toRfc4122();
        $qrDataUri = $this->qrGenerator->generateDataUri($verificationUrl);

        // Assets visuals notarials: Logo oficial i miniatura del bé
        $logoDataUri = $this->getLogoDataUri();
        $mainPhotoDataUri = $this->getMainPhotoDataUri($item);

        // 5. Càlcul provisional de context per a la plantilla Twig
        $dummyCertificate = new Certificate(
            item: $item,
            issuedBy: $issuer,
            certificateNumber: $certificateNumber,
            mode: $mode,
            pdfFilePath: '',
            pdfSha256: 'calculant...',
            itemFingerprintAtIssue: $itemFingerprint,
            status: CertificateStatus::VALID
        );

        // Renderitzat de l'HTML a través de Twig amb la Guia d'Estils
        $html = $this->twig->render('certificate/pdf_template.html.twig', [
            'item' => $item,
            'certificate' => $dummyCertificate,
            'isDraft' => $mode->isDraft(),
            'qrDataUri' => $qrDataUri,
            'logoDataUri' => $logoDataUri,
            'mainPhotoDataUri' => $mainPhotoDataUri,
            'latestReceipt' => $item->getLatestBlockchainReceipt(),
        ]);

        // 6. Conversió a PDF vectorial via Chromium headless
        $pdfBinary = $this->pdfRenderer->renderHtmlToPdf($html);
        $pdfSha256 = hash('sha256', $pdfBinary);

        // 7. Emmagatzematge custodiat privat a Flysystem
        $year = (new \DateTimeImmutable())->format('Y');
        $storagePath = sprintf('certificates/%s/%s_%s.pdf', $year, $item->getCui(), $certificateNumber);

        $this->itemDocumentStorage->write($storagePath, $pdfBinary);

        // 8. Creació i persistència de l'entitat Certificate
        $certificate = new Certificate(
            item: $item,
            issuedBy: $issuer,
            certificateNumber: $certificateNumber,
            mode: $mode,
            pdfFilePath: $storagePath,
            pdfSha256: $pdfSha256,
            itemFingerprintAtIssue: $itemFingerprint,
            status: CertificateStatus::VALID,
            uuid: $certificateUuid
        );

        // Segellat de temps qualificat eIDAS per a la modalitat Oficial
        if ($mode->isOfficial() && $this->signaturitClient !== null) {
            $eidasDto = $this->signaturitClient->timestampHash($pdfSha256);
            $certificate->setEidasSignaturitId($eidasDto->tokenId);
            $certificate->setEidasTimestamp($eidasDto->timestamp);
            $issuer->setCertificatesIssuedCount($issuer->getCertificatesIssuedCount() + 1);
        }

        $item->addCertificate($certificate);

        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        $this->logger?->info('Certificat emès i custodiat amb èxit', [
            'certificate_number' => $certificateNumber,
            'mode' => $mode->value,
            'cui' => $item->getCui(),
            'pdf_sha256' => $pdfSha256,
            'path' => $storagePath,
        ]);

        return $certificate;
    }

    /**
     * Regenera el document PDF d'un certificat existent (útil per a esborranys en canvis de plantilla).
     */
    public function regeneratePdf(Certificate $certificate): void
    {
        $item = $certificate->getItem();
        $verificationUrl = rtrim($this->defaultBaseUrl, '/') . '/verify/' . $certificate->getUuid()->toRfc4122();
        $qrDataUri = $this->qrGenerator->generateDataUri($verificationUrl);

        $logoDataUri = $this->getLogoDataUri();
        $mainPhotoDataUri = $this->getMainPhotoDataUri($item);

        $html = $this->twig->render('certificate/pdf_template.html.twig', [
            'item' => $item,
            'certificate' => $certificate,
            'isDraft' => $certificate->getMode()->isDraft(),
            'qrDataUri' => $qrDataUri,
            'logoDataUri' => $logoDataUri,
            'mainPhotoDataUri' => $mainPhotoDataUri,
            'latestReceipt' => $item->getLatestBlockchainReceipt(),
        ]);

        $pdfBinary = $this->pdfRenderer->renderHtmlToPdf($html);
        $pdfSha256 = hash('sha256', $pdfBinary);

        $storagePath = $certificate->getPdfFilePath();
        if (empty($storagePath)) {
            $year = $certificate->getCreatedAt()->format('Y');
            $storagePath = sprintf('certificates/%s/%s_%s.pdf', $year, $item->getCui(), $certificate->getCertificateNumber());
            $certificate->setPdfFilePath($storagePath);
        }

        $this->itemDocumentStorage->write($storagePath, $pdfBinary);
        $certificate->setPdfSha256($pdfSha256);

        $this->entityManager->flush();
    }

    /**
     * Obté l'isotip oficial de Registrals en format Data URI (base64 SVG).
     */
    private function getLogoDataUri(): ?string
    {
        $logoPath = $this->kernel->getProjectDir() . '/web/img/logos/Isotip/1-transparent.svg';
        if (!file_exists($logoPath)) {
            return null;
        }

        $svgContent = file_get_contents($logoPath);
        if ($svgContent === false) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svgContent);
    }

    /**
     * Obté la miniatura de la foto principal del bé en format Data URI des de la custòdia Flysystem.
     */
    private function getMainPhotoDataUri(Item $item): ?string
    {
        $mainPhoto = $item->getMainPhoto();
        if ($mainPhoto === null) {
            return null;
        }

        $path = $mainPhoto->getFilePath();
        if (!$this->itemDocumentStorage->fileExists($path)) {
            return null;
        }

        $binary = $this->itemDocumentStorage->read($path);
        $mime = $mainPhoto->getMimeType() ?: 'image/jpeg';

        return sprintf('data:%s;base64,%s', $mime, base64_encode($binary));
    }
}
