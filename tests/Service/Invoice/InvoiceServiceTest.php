<?php

declare(strict_types=1);

namespace App\Tests\Service\Invoice;

use App\Entity\BillingConfig;
use App\Entity\Invoice;
use App\Entity\PaymentTransaction;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Repository\BillingConfigRepository;
use App\Repository\InvoiceRepository;
use App\Service\Certificate\PdfRendererService;
use App\Service\Invoice\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Twig\Environment;

class InvoiceServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private InvoiceRepository $invoiceRepository;
    private BillingConfigRepository $billingConfigRepository;
    private PdfRendererService $pdfRenderer;
    private Environment $twig;
    private InvoiceService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepository::class);
        $this->billingConfigRepository = $this->createMock(BillingConfigRepository::class);
        $this->pdfRenderer = $this->createMock(PdfRendererService::class);
        $this->twig = $this->createMock(Environment::class);

        $this->service = new InvoiceService(
            $this->entityManager,
            $this->invoiceRepository,
            $this->billingConfigRepository,
            $this->pdfRenderer,
            $this->twig,
            new NullLogger()
        );
    }

    public function testCreateInvoiceF1ForCompleteBillingProfile(): void
    {
        $config = new BillingConfig(
            legalName: 'Albert (Registrals)',
            taxId: '40323344J',
            address: 'Carrer Major 1',
            postalCode: '08001',
            city: 'Barcelona',
            province: 'Barcelona',
            billingEmail: 'facturacio@registrals.cat'
        );
        $config->setNextNumberComplete(1);

        $this->billingConfigRepository->method('findActiveConfig')->willReturn($config);
        $this->invoiceRepository->method('findByPaymentTransaction')->willReturn(null);
        $this->invoiceRepository->method('findLatestGlobalHash')->willReturn(null);

        $user = (new User())
            ->setEmail('client@test.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado')
            ->setTaxId('12345678Z')
            ->setBillingAddress('Passeig de Gràcia 50')
            ->setBillingPostalCode('08007')
            ->setBillingCity('Barcelona')
            ->setBillingProvince('Barcelona')
            ->setCountryCode('ES');

        $transaction = new PaymentTransaction($user, PaymentType::SUBSCRIPTION, 2499);
        $transaction->setStatus(PaymentStatus::PAID);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $invoice = $this->service->createInvoiceFromPaymentTransaction($transaction);

        $this->assertSame('REG-2026-0001', $invoice->getInvoiceNumber());
        $this->assertSame(InvoiceType::F1_COMPLETE, $invoice->getInvoiceType());
        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        $this->assertSame(2065, $invoice->getSubtotalCents());
        $this->assertSame(434, $invoice->getVatAmountCents());
        $this->assertSame(2499, $invoice->getTotalCents());
        $this->assertSame(2, $config->getNextNumberComplete());
    }

    public function testCreateInvoiceF2ForIncompleteBillingProfile(): void
    {
        $config = new BillingConfig(
            legalName: 'Albert (Registrals)',
            taxId: '40323344J',
            address: 'Carrer Major 1',
            postalCode: '08001',
            city: 'Barcelona',
            province: 'Barcelona',
            billingEmail: 'facturacio@registrals.cat'
        );
        $config->setNextNumberSimplified(1);

        $this->billingConfigRepository->method('findActiveConfig')->willReturn($config);
        $this->invoiceRepository->method('findByPaymentTransaction')->willReturn(null);

        $user = (new User())
            ->setEmail('particular@test.cat')
            ->setFirstName('Joan')
            ->setLastName('Sense NIF');

        $transaction = new PaymentTransaction($user, PaymentType::CERTIFICATE, 590);
        $transaction->setStatus(PaymentStatus::PAID);

        $invoice = $this->service->createInvoiceFromPaymentTransaction($transaction);

        $this->assertSame('FS-2026-0001', $invoice->getInvoiceNumber());
        $this->assertSame(InvoiceType::F2_SIMPLIFIED, $invoice->getInvoiceType());
        $this->assertSame(488, $invoice->getSubtotalCents());
        $this->assertSame(102, $invoice->getVatAmountCents());
        $this->assertSame(590, $invoice->getTotalCents());
        $this->assertSame(2, $config->getNextNumberSimplified());
    }

    public function testIdempotencyReturnsExistingInvoice(): void
    {
        $user = (new User())->setEmail('test@registrals.cat');
        $transaction = new PaymentTransaction($user, PaymentType::SUBSCRIPTION, 2499);

        $existingInvoice = new Invoice(
            invoiceNumber: 'REG-2026-0099',
            series: 'REG-',
            year: 2026,
            sequentialNumber: 99,
            invoiceType: InvoiceType::F1_COMPLETE,
            subtotalCents: 2065,
            vatAmountCents: 434,
            totalCents: 2499,
            issuerSnapshot: [],
            customerSnapshot: [],
            lineItems: []
        );

        $this->invoiceRepository->expects($this->once())
            ->method('findByPaymentTransaction')
            ->with($transaction)
            ->willReturn($existingInvoice);

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->createInvoiceFromPaymentTransaction($transaction);
        $this->assertSame($existingInvoice, $result);
    }
}

