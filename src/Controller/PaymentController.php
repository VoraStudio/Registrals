<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\User;
use App\Enum\PaymentType;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Service\Payment\PaymentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly PaymentManager $paymentManager,
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly PlanRepository $planRepository,
        private readonly ItemRepository $itemRepository,
        private readonly ItemTransferRepository $transferRepository
    ) {
    }

    #[Route('/app/plan/{code}/checkout', name: 'app_payment_plan_checkout', methods: ['POST'])]
    public function checkoutPlan(string $code, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('checkout_plan_' . $code, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_plan');
        }

        $plan = $this->planRepository->findByCode($code);
        if ($plan === null || strtolower($code) === 'free') {
            $this->addFlash('error', 'El pla seleccionat no admet contractació de pagament.');
            return $this->redirectToRoute('app_plan');
        }

        if (!$plan->isActive() || strtolower($code) === 'unlimited') {
            $this->addFlash('warning', 'El Pla Unlimited estarà disponible properament per a grans patrimonis. Pots activar el Pla Plus.');
            return $this->redirectToRoute('app_plan');
        }

        $successUrl = $this->generateUrl('app_payment_success', [
            'type' => 'subscription',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $cancelUrl = $this->generateUrl('app_plan', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $checkout = $this->paymentGateway->createSubscriptionCheckout($user, $plan, $successUrl, $cancelUrl);

            // Registra la transacció pendent
            $this->paymentManager->createPendingTransaction(
                $user,
                PaymentType::SUBSCRIPTION,
                $plan->getPriceCents(),
                $checkout['sessionId'],
                ['plan_code' => $plan->getCode()]
            );

            // Si estem en mode mock i ve amb ?mock=1 a la URL
            if (str_contains($checkout['url'], 'mock=1')) {
                // Simulem l'esdeveniment webhook localment
                $this->paymentManager->processWebhook([
                    'id' => 'evt_mock_' . bin2hex(random_bytes(8)),
                    'type' => 'checkout.session.completed',
                    'data' => [
                        'object' => [
                            'id' => $checkout['sessionId'],
                            'client_reference_id' => (string) $user->getId(),
                            'amount_total' => $plan->getPriceCents(),
                            'metadata' => [
                                'type' => 'subscription',
                                'user_id' => (string) $user->getId(),
                                'plan_code' => $plan->getCode(),
                            ],
                        ],
                    ],
                ]);
            }

            return $this->redirect($checkout['url']);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error en iniciar la sessió de pagament: ' . $e->getMessage());
            return $this->redirectToRoute('app_plan');
        }
    }

    #[Route('/app/items/{cui}/certificate-checkout', name: 'app_payment_certificate_checkout', methods: ['POST'])]
    public function checkoutCertificate(string $cui, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('checkout_cert_' . $cui, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $item = $this->itemRepository->findOneByCui($cui);
        if ($item === null || $item->getOwner()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Bé no trobat o accés denegat.');
        }

        if ($item->getStatus() !== \App\Enum\ItemStatus::ACTIVE) {
            $this->addFlash('warning', 'No es pot emetre ni contractar un certificat per a un bé en traspàs o no actiu.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $amountCents = 590; // 5,90 €
        $successUrl = $this->generateUrl('app_payment_success', [
            'type' => 'certificate',
            'cui' => $cui,
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $cancelUrl = $this->generateUrl('app_item_show', ['cui' => $cui], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $checkout = $this->paymentGateway->createCertificateCheckout($user, $item, $amountCents, $successUrl, $cancelUrl);

            $this->paymentManager->createPendingTransaction(
                $user,
                PaymentType::CERTIFICATE,
                $amountCents,
                $checkout['sessionId'],
                ['item_id' => $item->getId(), 'cui' => $item->getCui()]
            );

            if (str_contains($checkout['url'], 'mock=1')) {
                $this->paymentManager->processWebhook([
                    'id' => 'evt_mock_' . bin2hex(random_bytes(8)),
                    'type' => 'checkout.session.completed',
                    'data' => [
                        'object' => [
                            'id' => $checkout['sessionId'],
                            'client_reference_id' => (string) $user->getId(),
                            'amount_total' => $amountCents,
                            'metadata' => [
                                'type' => 'certificate',
                                'user_id' => (string) $user->getId(),
                                'item_id' => (string) $item->getId(),
                            ],
                        ],
                    ],
                ]);
            }

            return $this->redirect($checkout['url']);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error en generar el pagament del certificat: ' . $e->getMessage());
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }
    }

    #[Route('/app/transfers/{token}/checkout', name: 'app_payment_transfer_checkout', methods: ['POST'])]
    public function checkoutTransfer(string $token, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('checkout_txf_' . $token, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }

        $transfer = $this->transferRepository->findByToken($token);
        if ($transfer === null) {
            throw $this->createNotFoundException('Traspàs no trobat.');
        }

        $amountCents = 290; // 2,90 €
        $successUrl = $this->generateUrl('app_payment_success', [
            'type' => 'transfer',
            'token' => $token,
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $cancelUrl = $this->generateUrl('app_transfer_claim', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $checkout = $this->paymentGateway->createTransferCheckout($user, $transfer, $amountCents, $successUrl, $cancelUrl);

            $this->paymentManager->createPendingTransaction(
                $user,
                PaymentType::TRANSFER,
                $amountCents,
                $checkout['sessionId'],
                ['transfer_id' => $transfer->getId(), 'token' => $token]
            );

            if (str_contains($checkout['url'], 'mock=1')) {
                $this->paymentManager->processWebhook([
                    'id' => 'evt_mock_' . bin2hex(random_bytes(8)),
                    'type' => 'checkout.session.completed',
                    'data' => [
                        'object' => [
                            'id' => $checkout['sessionId'],
                            'client_reference_id' => (string) $user->getId(),
                            'amount_total' => $amountCents,
                            'metadata' => [
                                'type' => 'transfer',
                                'user_id' => (string) $user->getId(),
                                'transfer_id' => (string) $transfer->getId(),
                            ],
                        ],
                    ],
                ]);
            }

            return $this->redirect($checkout['url']);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error en processar la taxa de traspàs: ' . $e->getMessage());
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }
    }

    #[Route('/app/payment/portal', name: 'app_payment_portal', methods: ['GET'])]
    public function customerPortal(?Request $request = null): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $returnParam = (string) ($request?->query->get('return') ?? 'app_plan_billing');
        $validRoutes = ['app_plan_billing', 'app_plan', 'app_profile'];
        $targetRoute = in_array($returnParam, $validRoutes, true) ? $returnParam : 'app_plan_billing';
        $returnUrl = $this->generateUrl($targetRoute, [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $portalUrl = $this->paymentGateway->createCustomerPortalSession($user, $returnUrl);
            return $this->redirect($portalUrl);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No s\'ha pogut accedir al portal de client: ' . $e->getMessage());
            return $this->redirectToRoute($targetRoute);
        }
    }

    #[Route('/app/perfil/facturacio/cancel-subscripcio', name: 'app_payment_cancel_subscription', methods: ['POST'])]
    public function cancelSubscription(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('cancel_subscription', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_plan_billing');
        }

        if ($user->getPlan()?->getCode() === 'free' && $user->getSubscriptionStatus() !== 'active') {
            $this->addFlash('info', 'El teu compte ja està assignat al Pla Free.');
            return $this->redirectToRoute('app_plan_billing');
        }

        // Cancel·la a Stripe i rebaixa el compte a Pla Free aplicant el període de gràcia
        $this->paymentManager->cancelUserSubscription($user, $this->paymentGateway);

        if ($user->isInDowngradeGracePeriod()) {
            $maxFree = $user->getPlan()?->getMaxActiveItems() ?? 20;
            $this->addFlash('warning', sprintf(
                'La teva subscripció s\'ha cancel·lat. Com que superes el límit de béns actius del Pla Free (%d), disposes de 7 dies de període de gràcia per triar quins béns vols mantenir operatius.',
                $maxFree
            ));
            return $this->redirectToRoute('app_downgrade_adequacy');
        }

        $this->addFlash('success', 'La teva subscripció s\'ha cancel·lat correctament i el teu compte ha passat al Pla Free.');
        return $this->redirectToRoute('app_plan_billing');
    }

    #[Route('/app/payment/success', name: 'app_payment_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $type = $request->query->get('type', 'subscription');
        $sessionId = (string) $request->query->get('session_id', '');
        $cui = $request->query->get('cui');
        $token = $request->query->get('token');

        $transaction = null;
        if ($sessionId !== '') {
            $transaction = $this->transactionRepository->findByStripeSessionId($sessionId);
        }

        return $this->render('payment/success.html.twig', [
            'type' => $type,
            'sessionId' => $sessionId,
            'cui' => $cui,
            'token' => $token,
            'transaction' => $transaction,
        ]);
    }

    #[Route('/app/payment/status/{sessionId}', name: 'app_payment_status', methods: ['GET'])]
    public function status(string $sessionId): JsonResponse
    {
        $transaction = $this->transactionRepository->findByStripeSessionId($sessionId);
        if ($transaction === null) {
            return new JsonResponse(['status' => 'pending', 'paid' => false]);
        }

        return new JsonResponse([
            'status' => $transaction->getStatus()->value,
            'paid' => $transaction->getStatus()->isSuccess(),
        ]);
    }
}
