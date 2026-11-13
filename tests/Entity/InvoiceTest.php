<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\BillingConfig;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    public function testInvoiceCreationAndVeriFactuHashChaining(): void
    {
        $billingConfig = new BillingConfig(
            legalName: 'Albert (Registrals)',
            taxId: '40323344J',
            address: 'Carrer Gran 10',
            postalCode: '08001',
            city: 'Barcelona',
            province: 'Barcelona',
            billingEmail: 'facturacio@registrals.cat'
        );
        $issuerSnapshot = $billingConfig->toIssuerSnapshot();

        $user = (new User())
            ->setEmail('client@test.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado')
            ->setTaxId('12345678Z')
            ->setBillingAddress('Passeig de Gràcia 50')
            ->setBillingPostalCode('08007')
            ->setBillingCity('Barcelona')
            ->setBillingProvince('Barcelona');

        $customerSnapshot = [
            'legal_name' => $user->getEffectiveBillingName(),
            'tax_id' => $user->getTaxId(),
            'address' => $user->getBillingAddress(),
            'postal_code' => $user->getBillingPostalCode(),
            'city' => $user->getBillingCity(),
            'province' => $user->getBillingProvince(),
            'country_code' => $user->getCountryCode(),
            'email' => $user->getEmail(),
        ];

        $lineItems = [
            [
                'concept' => 'Subscripció Anual Pla Plus (1 any)',
                'quantity' => 1,
                'unitPriceCents' => 2065,
                'vatRate' => '21.00',
                'vatAmountCents' => 434,
                'totalCents' => 2499,
            ],
        ];

        $invoice1 = new Invoice(
            invoiceNumber: 'REG-2026-0001',
            series: 'REG-',
            year: 2026,
            sequentialNumber: 1,
            invoiceType: InvoiceType::F1_COMPLETE,
            subtotalCents: 2065,
            vatAmountCents: 434,
            totalCents: 2499,
            issuerSnapshot: $issuerSnapshot,
            customerSnapshot: $customerSnapshot,
            lineItems: $lineItems,
            previousInvoiceHash: null,
            vatRate: '21.00'
        );

        $this->assertNotNull($invoice1->getUuid());
        $this->assertSame('REG-2026-0001', $invoice1->getInvoiceNumber());
        $this->assertSame(InvoiceType::F1_COMPLETE, $invoice1->getInvoiceType());
        $this->assertSame(InvoiceStatus::PAID, $invoice1->getStatus());
        $this->assertSame(2065, $invoice1->getSubtotalCents());
        $this->assertSame(434, $invoice1->getVatAmountCents());
        $this->assertSame(2499, $invoice1->getTotalCents());
        $this->assertSame('24,99 €', $invoice1->getTotalFormatted());
        $this->assertSame('20,65 €', $invoice1->getSubtotalFormatted());
        $this->assertSame('4,34 €', $invoice1->getVatAmountFormatted());
        $this->assertNotEmpty($invoice1->getCurrentHash());

        // VeriFactu hash chaining for second invoice
        $invoice2 = new Invoice(
            invoiceNumber: 'REG-2026-0002',
            series: 'REG-',
            year: 2026,
            sequentialNumber: 2,
            invoiceType: InvoiceType::F1_COMPLETE,
            subtotalCents: 2065,
            vatAmountCents: 434,
            totalCents: 2499,
            issuerSnapshot: $issuerSnapshot,
            customerSnapshot: $customerSnapshot,
            lineItems: $lineItems,
            previousInvoiceHash: $invoice1->getCurrentHash(),
            vatRate: '21.00'
        );

        $this->assertSame($invoice1->getCurrentHash(), $invoice2->getPreviousInvoiceHash());
        $this->assertNotSame($invoice1->getCurrentHash(), $invoice2->getCurrentHash());
    }

    public function testRectificationInvoiceLinksToOriginal(): void
    {
        $issuerSnapshot = ['legal_name' => 'Albert', 'tax_id' => '40323344J'];
        $customerSnapshot = ['legal_name' => 'Client', 'tax_id' => null];
        $lineItems = [['concept' => 'Emissió de Certificat Solt', 'totalCents' => 590]];

        $original = new Invoice(
            invoiceNumber: 'FS-2026-0001',
            series: 'FS-',
            year: 2026,
            sequentialNumber: 1,
            invoiceType: InvoiceType::F2_SIMPLIFIED,
            subtotalCents: 488,
            vatAmountCents: 102,
            totalCents: 590,
            issuerSnapshot: $issuerSnapshot,
            customerSnapshot: $customerSnapshot,
            lineItems: $lineItems
        );

        $rectification = new Invoice(
            invoiceNumber: 'R-2026-0001',
            series: 'R-',
            year: 2026,
            sequentialNumber: 1,
            invoiceType: InvoiceType::R1_RECTIFICATION,
            subtotalCents: -488,
            vatAmountCents: -102,
            totalCents: -590,
            issuerSnapshot: $issuerSnapshot,
            customerSnapshot: $customerSnapshot,
            lineItems: [['concept' => 'Abonament total per anul·lació de comanda FS-2026-0001', 'totalCents' => -590]],
            previousInvoiceHash: $original->getCurrentHash()
        );
        $rectification->setOriginalInvoice($original);
        $rectification->setStatus(InvoiceStatus::REFUNDED);

        $this->assertTrue($rectification->getInvoiceType()->isRectification());
        $this->assertSame($original, $rectification->getOriginalInvoice());
        $this->assertSame('-5,90 €', $rectification->getTotalFormatted());
    }
}

