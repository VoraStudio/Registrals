<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\PaymentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PlanController extends AbstractController
{
    #[Route('/app/perfil/pla', name: 'app_plan', methods: ['GET'])]
    #[Route('/app/plan', name: 'app_plan_legacy', methods: ['GET'])]
    public function index(
        PlanRepository $planRepository,
        \App\Repository\ItemRepository $itemRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $currentPlan = $user->getPlan() ?? $planRepository->findByCode('free');
        if ($currentPlan === null) {
            throw $this->createNotFoundException('Pla de sistema no trobat.');
        }

        // Càlcul de consums actius i en custòdia en fred
        $activeItemsCount = $itemRepository->countActiveByOwner($user);
        $frozenCount = $itemRepository->countFrozenByOwner($user);

        $maxActive = $currentPlan->getMaxActiveItems();
        $itemsPct = $maxActive > 0 ? (int) round(($activeItemsCount / $maxActive) * 100) : 0;
        if ($itemsPct > 100) {
            $itemsPct = 100;
        }

        $aiUsed = $user->getAiUsageCount();
        $maxAi = $currentPlan->getAiAssistanceQuota();
        $aiPct = $maxAi > 0 ? (int) round(($aiUsed / $maxAi) * 100) : 0;
        if ($aiPct > 100) {
            $aiPct = 100;
        }

        // Recuperar tots els plans per a la comparativa d'upgrade (actius i en preparació)
        $allPlans = $planRepository->findBy([], ['priceCents' => 'ASC']);

        return $this->render('plan/index.html.twig', [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'activeItemsCount' => $activeItemsCount,
            'frozenCount' => $frozenCount,
            'maxActive' => $maxActive,
            'itemsPct' => $itemsPct,
            'aiUsed' => $aiUsed,
            'maxAi' => $maxAi,
            'aiPct' => $aiPct,
            'allPlans' => $allPlans,
        ]);
    }

    #[Route('/app/perfil/facturacio', name: 'app_plan_billing', methods: ['GET'])]
    #[Route('/app/plan/billing', name: 'app_plan_billing_legacy', methods: ['GET'])]
    public function billing(
        PlanRepository $planRepository,
        PaymentTransactionRepository $transactionRepository,
        ?Request $request = null,
        ?PaymentManager $paymentManager = null,
        ?PaymentGatewayInterface $paymentGateway = null,
        ?InvoiceRepository $invoiceRepository = null,
        ?InvoiceService $invoiceService = null
    ): Response {
        $request ??= new Request();

        /** @var User $user */
        $user = $this->getUser();
        if ($paymentManager !== null && $paymentGateway !== null) {
            $paymentManager->syncPendingTransactionsForUser($user, $paymentGateway);
        }

        $currentPlan = $user->getPlan() ?? $planRepository->findByCode('free');
        if ($currentPlan === null) {
            throw $this->createNotFoundException('Pla de sistema no trobat.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        if (!in_array($limit, [10, 25, 50], true)) {
            $limit = 10;
        }

        $totalTransactions = $transactionRepository->countByUser($user);
        $totalPages = max(1, (int) ceil($totalTransactions / $limit));
        if ($page > $totalPages && $totalTransactions > 0) {
            $page = $totalPages;
        }

        $transactions = $transactionRepository->findPaginatedByUser($user, $page, $limit);
        $freePlan = $planRepository->findByCode('free');
        $plusPlan = $planRepository->findByCode('plus');

        // Mapeig de factures per a cada transacció i auto-generació si no en té
        $invoicesByTxId = [];
        $rectificationsByOriginalId = [];
        if ($invoiceRepository !== null) {
            $userInvoices = $invoiceRepository->findByUserOrdered($user);
            foreach ($userInvoices as $inv) {
                if ($inv->getPaymentTransaction() !== null) {
                    $invoicesByTxId[$inv->getPaymentTransaction()->getId()] = $inv;
                }
                if ($inv->getOriginalInvoice() !== null) {
                    $rectificationsByOriginalId[$inv->getOriginalInvoice()->getId()] = $inv;
                }
            }
        }

        if ($invoiceService !== null) {
            foreach ($transactions as $tx) {
                if ($tx->getStatus()->isSuccess() && !isset($invoicesByTxId[$tx->getId()])) {
                    try {
                        $newInv = $invoiceService->createInvoiceFromPaymentTransaction($tx);
                        $invoicesByTxId[$tx->getId()] = $newInv;
                    } catch (\Throwable) {
                        // Silenciós per no blocar la renderització
                    }
                }
            }
        }

        return $this->render('plan/billing.html.twig', [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'freePlan' => $freePlan,
            'plusPlan' => $plusPlan,
            'maxFreeItems' => $freePlan?->getMaxActiveItems() ?? 20,
            'maxPlusItems' => $plusPlan?->getMaxActiveItems() ?? 100,
            'transactions' => $transactions,
            'invoicesByTxId' => $invoicesByTxId,
            'rectificationsByOriginalId' => $rectificationsByOriginalId,
            'currentPage' => $page,
            'currentLimit' => $limit,
            'totalItems' => $totalTransactions,
            'totalPages' => $totalPages,
        ]);
    }
}
