<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\BillingConfig;
use App\Entity\Invoice;
use App\Entity\PaymentTransaction;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Repository\BillingConfigRepository;
use App\Repository\InvoiceRepository;
use App\Service\Certificate\PdfRendererService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Servei d'emissió autònoma de factures fiscals de Registrals.
 * Governa la generació seqüencial F1/F2/R1, desglossament d'IVA, snapshots immutables,
 * encadenament criptogràfic VeriFactu i renderitzat PDF vectorial.
 */
class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly BillingConfigRepository $billingConfigRepository,
        private readonly PdfRendererService $pdfRenderer,
        private readonly Environment $twig,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?KernelInterface $kernel = null
    ) {
    }

    /**
     * Emet una factura oficial a partir d'una transacció de pagament completada a Stripe.
     * Si ja existeix una factura per a aquesta transacció, retorna la factura existent (idempotència).
     */
    public function createInvoiceFromPaymentTransaction(
        PaymentTransaction $transaction,
        ?InvoiceType $forcedType = null
    ): Invoice {
        $existing = $this->invoiceRepository->findByPaymentTransaction($transaction);
        if ($existing !== null) {
            return $existing;
        }

        $config = $this->getOrCreateActiveBillingConfig();
        $user = $transaction->getUser();

        // Determinar tipus de factura (F1 Completa si té NIF i adreça fiscal, F2 Simplificada si no)
        $invoiceType = $forcedType ?? ($user->hasCompleteBillingProfile() ? InvoiceType::F1_COMPLETE : InvoiceType::F2_SIMPLIFIED);
        $year = (int) $transaction->getCreatedAt()->format('Y');

        $series = match ($invoiceType) {
            InvoiceType::F1_COMPLETE => $config->getSeriesComplete(),
            InvoiceType::F2_SIMPLIFIED => $config->getSeriesSimplified(),
            InvoiceType::R1_RECTIFICATION, InvoiceType::R4_RECTIFICATION => $config->getSeriesRectification(),
        };

        // Obtenir número correlatiu atòmic
        $seqNumber = match ($invoiceType) {
            InvoiceType::F1_COMPLETE => $config->getNextNumberComplete(),
            InvoiceType::F2_SIMPLIFIED => $config->getNextNumberSimplified(),
            InvoiceType::R1_RECTIFICATION, InvoiceType::R4_RECTIFICATION => $config->getNextNumberRectification(),
        };

        // Incrementar el següent número a la configuració
        match ($invoiceType) {
            InvoiceType::F1_COMPLETE => $config->setNextNumberComplete($seqNumber + 1),
            InvoiceType::F2_SIMPLIFIED => $config->setNextNumberSimplified($seqNumber + 1),
            InvoiceType::R1_RECTIFICATION, InvoiceType::R4_RECTIFICATION => $config->setNextNumberRectification($seqNumber + 1),
        };
        $config->setUpdatedAt(new \DateTimeImmutable());

        $invoiceNumber = sprintf('%s%d-%04d', $series, $year, $seqNumber);

        // Càlcul de base imposable i quota d'IVA (21%)
        $totalCents = $transaction->getAmountCents();
        $vatRateNum = (float) $config->getDefaultVatRate();
        $subtotalCents = (int) round($totalCents / (1 + ($vatRateNum / 100)));
        $vatAmountCents = $totalCents - $subtotalCents;

        $irpfRateNum = (float) $config->getDefaultIrpfRate();
        $irpfAmountCents = 0;
        if ($irpfRateNum > 0) {
            $irpfAmountCents = (int) round($subtotalCents * ($irpfRateNum / 100));
        }

        // Instantània de l'emissor i del client
        $issuerSnapshot = $config->toIssuerSnapshot();
        $customerSnapshot = $this->buildCustomerSnapshot($user);

        // Línies de detall de la factura
        $lineItems = [
            [
                'concept' => $transaction->getType()->label(),
                'quantity' => 1,
                'unit_price_cents' => $subtotalCents,
                'vat_rate' => $config->getDefaultVatRate(),
                'vat_amount_cents' => $vatAmountCents,
                'total_cents' => $totalCents,
            ],
        ];

        // Encadenament criptogràfic VeriFactu amb l'últim hash registrat a la plataforma
        $previousHash = $this->invoiceRepository->findLatestGlobalHash();

        $invoice = new Invoice(
            invoiceNumber: $invoiceNumber,
            series: $series,
            year: $year,
            sequentialNumber: $seqNumber,
            invoiceType: $invoiceType,
            subtotalCents: $subtotalCents,
            vatAmountCents: $vatAmountCents,
            totalCents: $totalCents,
            issuerSnapshot: $issuerSnapshot,
            customerSnapshot: $customerSnapshot,
            lineItems: $lineItems,
            previousInvoiceHash: $previousHash,
            vatRate: $config->getDefaultVatRate(),
            irpfRate: $config->getDefaultIrpfRate(),
            irpfAmountCents: $irpfAmountCents,
            currency: $transaction->getCurrency()
        );

        $invoice->setUser($user);
        $invoice->setPaymentTransaction($transaction);
        $invoice->setStatus(InvoiceStatus::PAID);
        $invoice->setPaidAt(new \DateTimeImmutable());
        $invoice->setStripePaymentIntentId($transaction->getStripePaymentIntentId());

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $this->logger?->info('Factura oficial emesa amb èxit', [
            'invoice_number' => $invoiceNumber,
            'type' => $invoiceType->value,
            'total' => $invoice->getTotalFormatted(),
            'user' => $user->getEmail(),
        ]);

        return $invoice;
    }

    /**
     * Emet una factura rectificativa (R1) que abona una factura existent prèvia.
     */
    public function createRectificationInvoice(Invoice $originalInvoice, string $reason): Invoice
    {
        $config = $this->getOrCreateActiveBillingConfig();
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $series = $config->getSeriesRectification();
        $seqNumber = $config->getNextNumberRectification();
        $config->setNextNumberRectification($seqNumber + 1);
        $config->setUpdatedAt(new \DateTimeImmutable());

        $invoiceNumber = sprintf('%s%d-%04d', $series, $year, $seqNumber);

        $totalCents = -$originalInvoice->getTotalCents();
        $subtotalCents = -$originalInvoice->getSubtotalCents();
        $vatAmountCents = -$originalInvoice->getVatAmountCents();

        $lineItems = [
            [
                'concept' => sprintf('Abonament / Rectificació de factura %s: %s', $originalInvoice->getInvoiceNumber(), $reason),
                'quantity' => 1,
                'unit_price_cents' => $subtotalCents,
                'vat_rate' => $originalInvoice->getVatRate(),
                'vat_amount_cents' => $vatAmountCents,
                'total_cents' => $totalCents,
            ],
        ];

        $previousHash = $this->invoiceRepository->findLatestGlobalHash();

        $rectification = new Invoice(
            invoiceNumber: $invoiceNumber,
            series: $series,
            year: $year,
            sequentialNumber: $seqNumber,
            invoiceType: InvoiceType::R1_RECTIFICATION,
            subtotalCents: $subtotalCents,
            vatAmountCents: $vatAmountCents,
            totalCents: $totalCents,
            issuerSnapshot: $config->toIssuerSnapshot(),
            customerSnapshot: $originalInvoice->getCustomerSnapshot(),
            lineItems: $lineItems,
            previousInvoiceHash: $previousHash,
            vatRate: $originalInvoice->getVatRate()
        );

        $rectification->setUser($originalInvoice->getUser());
        $rectification->setOriginalInvoice($originalInvoice);
        $rectification->setStatus(InvoiceStatus::REFUNDED);
        $rectification->setPaidAt(new \DateTimeImmutable());
        $rectification->setNotes($reason);

        $this->entityManager->persist($rectification);
        $this->entityManager->flush();

        return $rectification;
    }

    /**
     * Renderitza la factura en document PDF vectorial binari amb el motor Chromium headless.
     */
    public function renderInvoicePdf(Invoice $invoice): string
    {
        $html = $this->twig->render('invoice/pdf_template.html.twig', [
            'invoice' => $invoice,
            'issuer' => $invoice->getIssuerSnapshot(),
            'customer' => $invoice->getCustomerSnapshot(),
            'lineItems' => $invoice->getLineItems(),
            'logoDataUri' => $this->getLogoDataUri(),
        ]);

        return $this->pdfRenderer->renderHtmlToPdf($html);
    }

    /**
     * Obté l'isotip oficial de Registrals en format Data URI (base64 SVG).
     */
    private function getLogoDataUri(): ?string
    {
        if ($this->kernel === null) {
            return null;
        }

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
     * Retorna la configuració activa vigent o en crea una per defecte si no n'hi ha cap.
     */
    public function getOrCreateActiveBillingConfig(): BillingConfig
    {
        $config = $this->billingConfigRepository->findActiveConfig();
        if ($config !== null) {
            return $config;
        }

        $config = new BillingConfig(
            legalName: 'Albert (Registrals)',
            taxId: '00000000T',
            address: 'Carrer de la Pau 1',
            postalCode: '08001',
            city: 'Barcelona',
            province: 'Barcelona',
            billingEmail: 'facturacio@registrals.cat',
            countryCode: 'ES'
        );
        $config->setFooterLegalText("Règim general de l'IVA.");

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerSnapshot(User $user): array
    {
        return [
            'legal_name' => $user->getEffectiveBillingName(),
            'tax_id' => $user->getTaxId(),
            'address' => $user->getBillingAddress(),
            'postal_code' => $user->getBillingPostalCode(),
            'city' => $user->getBillingCity(),
            'province' => $user->getBillingProvince(),
            'country_code' => $user->getCountryCode(),
            'email' => $user->getEmail(),
        ];
    }
}

