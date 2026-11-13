<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class InvoiceController extends AbstractController
{
    /**
     * Descarrega o visualitza el PDF oficial de la factura amb VeriFactu i desglossament fiscal.
     */
    #[Route('/app/factures/{uuid}/descarregar', name: 'app_invoice_download', methods: ['GET'])]
    public function download(
        string $uuid,
        InvoiceRepository $invoiceRepository,
        InvoiceService $invoiceService
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $invoice = $invoiceRepository->findOneBy(['uuid' => $uuid]);
        if ($invoice === null) {
            throw new NotFoundHttpException('La factura sol·licitada no existeix.');
        }

        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
        if ($invoice->getUser()?->getId() !== $currentUser->getId() && !$isAdmin) {
            throw new AccessDeniedHttpException('No tens permís per accedir a aquesta factura.');
        }

        $pdfBinary = $invoiceService->renderInvoicePdf($invoice);

        $safeFilename = sprintf('factura-%s.pdf', preg_replace('/[^A-Za-z0-9_-]/', '-', $invoice->getInvoiceNumber()));

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $safeFilename),
            'Content-Length' => strlen($pdfBinary),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}

