<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/factures')]
#[IsGranted('ROLE_ADMIN')]
class AdminInvoiceController extends AbstractController
{
    #[Route('', name: 'app_admin_invoices', methods: ['GET'])]
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepository
    ): Response {
        $year = (int) $request->query->get('year', (string) date('Y'));
        $quarter = (int) $request->query->get('quarter', '0');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);
        if (!in_array($limit, [10, 25, 50, 100], true)) {
            $limit = 25;
        }

        // Totals financers agregats per a tot el període filtrat (sense biaix de paginació)
        $totals = $invoiceRepository->getFinancialTotalsForPeriod($year, $quarter);
        $totalItems = $totals['count'];
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        if ($page > $totalPages && $totalItems > 0) {
            $page = $totalPages;
        }

        $invoices = $invoiceRepository->findPaginatedForPeriod($year, $quarter, $page, $limit);

        return $this->render('admin/invoice/index.html.twig', [
            'invoices' => $invoices,
            'selectedYear' => $year,
            'selectedQuarter' => $quarter,
            'currentPage' => $page,
            'currentLimit' => $limit,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'kpis' => [
                'count' => $totals['count'],
                'totalFormatted' => number_format($totals['totalCents'] / 100, 2, ',', '.') . ' €',
                'subtotalFormatted' => number_format($totals['subtotalCents'] / 100, 2, ',', '.') . ' €',
                'vatFormatted' => number_format($totals['vatCents'] / 100, 2, ',', '.') . ' €',
            ],
        ]);
    }

    /**
     * Exporta el llibre de factures emeses en format CSV estàndard (compatible amb A3, Sage, Odoo i Excel).
     */
    #[Route('/exportar', name: 'app_admin_invoices_export_csv', methods: ['GET'])]
    public function exportCsv(
        Request $request,
        InvoiceRepository $invoiceRepository
    ): Response {
        $year = (int) $request->query->get('year', (string) date('Y'));
        $quarter = (int) $request->query->get('quarter', '0');

        $invoices = $invoiceRepository->findAllForPeriod($year, $quarter);

        if ($quarter >= 1 && $quarter <= 4) {
            $filename = sprintf('llibre-factures-registrals-%d-T%d.csv', $year, $quarter);
        } else {
            $filename = sprintf('llibre-factures-registrals-%d.csv', $year);
        }

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new \RuntimeException('No s\'ha pogut obrir el flux temporal per a l\'exportació.');
        }

        // Capçaleres CSV amb la nomenclatura estàndard d'Hisenda / SII / VeriFactu
        fputcsv($output, [
            "Data d'Emissio",
            'Numero Factura',
            'Serie',
            'Tipus Factura',
            'Client / Rao Social',
            'NIF / CIF',
            'Codi Pais',
            'Codi Postal',
            'Poblacio',
            'Base Imposable (EUR)',
            'Tipus IVA (%)',
            'Quota IVA (EUR)',
            'Tipus IRPF (%)',
            'Quota IRPF (EUR)',
            'Total Factura (EUR)',
            'Stripe Payment Intent ID',
            'Estat Factura',
            'Hash VeriFactu SHA-256',
            'Hash Factura Anterior'
        ], ';');

        foreach ($invoices as $inv) {
            $client = $inv->getCustomerSnapshot();
            fputcsv($output, [
                $inv->getIssuedAt()->format('d/m/Y'),
                $inv->getInvoiceNumber(),
                $inv->getSeries(),
                $inv->getInvoiceType()->value,
                $client['legal_name'] ?? 'Consumidor Final',
                $client['tax_id'] ?? '',
                $client['country_code'] ?? 'ES',
                $client['postal_code'] ?? '',
                $client['city'] ?? '',
                number_format($inv->getSubtotalCents() / 100, 2, ',', ''),
                $inv->getVatRate(),
                number_format($inv->getVatAmountCents() / 100, 2, ',', ''),
                $inv->getIrpfRate(),
                number_format($inv->getIrpfAmountCents() / 100, 2, ',', ''),
                number_format($inv->getTotalCents() / 100, 2, ',', ''),
                $inv->getStripePaymentIntentId() ?? '',
                $inv->getStatus()->label(),
                $inv->getCurrentHash(),
                $inv->getPreviousInvoiceHash() ?? 'GENESIS'
            ], ';');
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // Afegir UTF-8 BOM (\xEF\xBB\xBF) perquè Excel per a Windows interpreti caràcters especials
        $csvWithBom = "\xEF\xBB\xBF" . (string) $csvContent;

        return new Response($csvWithBom, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Descàrrega directa del document PDF des de la cabina d'administració.
     */
    #[Route('/{uuid}/pdf', name: 'app_admin_invoice_pdf', methods: ['GET'])]
    public function downloadPdf(
        string $uuid,
        InvoiceRepository $invoiceRepository,
        InvoiceService $invoiceService
    ): Response {
        $invoice = $invoiceRepository->findOneBy(['uuid' => $uuid]);
        if ($invoice === null) {
            throw new NotFoundHttpException('La factura sol·licitada no existeix.');
        }

        $pdfBinary = $invoiceService->renderInvoicePdf($invoice);
        $safeFilename = sprintf('factura-%s.pdf', preg_replace('/[^A-Za-z0-9_-]/', '-', $invoice->getInvoiceNumber()));

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $safeFilename),
            'Content-Length' => strlen($pdfBinary),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}

