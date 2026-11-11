<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\LegacyContact;
use App\Entity\PaymentTransaction;
use App\Entity\Plan;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Repository\SuccessionClaimRepository;
use App\Repository\UserRepository;
use App\Service\Certificate\CertificateIssuerService;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use App\Service\Payment\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PaymentManagerTest extends TestCase
{
    public function testHandleSubscriptionCheckoutLogsActivity(): void
    {
        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $userRepo = $this->createMock(UserRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $certIssuer = $this->createMock(CertificateIssuerService::class);
        $certRepo = $this->createMock(CertificateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = new NullLogger();
        $activityLogger = $this->createMock(UserActivityLogger::class);

        $user = new User();
        $user->setEmail('subscriber@registrals.cat');
        $user->setFirstName('Subscriber');
        $user->setLastName('Test');

        $plan = new Plan('Plus', 'plus', 100, 5, 5, 100, false);
        $planRepo->method('findByCode')->with('plus')->willReturn($plan);
        $userRepo->method('find')->with(42)->willReturn($user);
        $transactionRepo->method('findByStripeEventId')->willReturn(null);

        $activityLogger->expects($this->once())
            ->method('log')
            ->with(
                $user,
                UserActivityAction::SUBSCRIPTION_STARTED,
                $this->stringContains('Subscripció al Pla PLUS activada'),
                $this->callback(fn($details) => ($details['amount'] ?? 0) === 4900)
            );

        $manager = new PaymentManager(
            $transactionRepo,
            $userRepo,
            $planRepo,
            $itemRepo,
            $transferRepo,
            $certIssuer,
            $certRepo,
            $em,
            $logger,
            $activityLogger
        );

        $event = [
            'id' => 'evt_sub_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_sub_123',
                    'amount_total' => 4900,
                    'subscription' => 'sub_stripe_123',
                    'metadata' => [
                        'type' => 'subscription',
                        'plan_code' => 'plus',
                        'user_id' => '42',
                    ],
                ],
            ],
        ];

        $result = $manager->processWebhook($event);
        $this->assertTrue($result);
        $this->assertSame('active', $user->getSubscriptionStatus());
        $this->assertSame('sub_stripe_123', $user->getStripeSubscriptionId());
    }

    public function testHandleCertificateCheckoutLogsActivity(): void
    {
        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $userRepo = $this->createMock(UserRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $certIssuer = $this->createMock(CertificateIssuerService::class);
        $certRepo = $this->createMock(CertificateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = new NullLogger();
        $activityLogger = $this->createMock(UserActivityLogger::class);

        $user = new User();
        $user->setEmail('certbuyer@registrals.cat');
        $user->setFirstName('Cert');
        $user->setLastName('Buyer');

        $category = new \App\Entity\Category('Rellotges', 'rellotges');
        $item = new Item($user, 'REG-777-TEST', 'Rellotge Rolex', $category);

        $userRepo->method('find')->with(10)->willReturn($user);
        $itemRepo->method('find')->with(5)->willReturn($item);
        $transactionRepo->method('findByStripeEventId')->willReturn(null);

        $certIssuer->expects($this->once())
            ->method('issueCertificate')
            ->with($item, $user, CertificateMode::OFFICIAL, true);

        $activityLogger->expects($this->once())
            ->method('log')
            ->with(
                $user,
                UserActivityAction::PAYMENT_COMPLETED,
                $this->stringContains('REG-777-TEST'),
                $this->callback(fn($details) => ($details['amount'] ?? 0) === 590)
            );

        $manager = new PaymentManager(
            $transactionRepo,
            $userRepo,
            $planRepo,
            $itemRepo,
            $transferRepo,
            $certIssuer,
            $certRepo,
            $em,
            $logger,
            $activityLogger
        );

        $event = [
            'id' => 'evt_cert_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_cert_123',
                    'amount_total' => 590,
                    'metadata' => [
                        'type' => 'certificate',
                        'item_id' => '5',
                        'user_id' => '10',
                    ],
                ],
            ],
        ];

        $result = $manager->processWebhook($event);
        $this->assertTrue($result);
    }

    public function testCheckoutCompletedActivatesSuccessionClaim72HourWindow(): void
    {
        $owner = (new User())->setEmail('anna@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = new LegacyContact($owner, 'Maria Soler', 'maria@exemple.cat');
        $cert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000999',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT.pdf',
            pdfSha256: str_repeat('a', 64),
            itemFingerprintAtIssue: str_repeat('b', 64),
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );
        $claim = new SuccessionClaim(
            'LEG-2026-000500',
            $cert,
            $owner,
            'Maria Soler',
            'maria@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000500/def.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000500/tit.pdf',
            str_repeat('2', 64),
        );

        $transaction = new PaymentTransaction($owner, PaymentType::SUCCESSION_CLAIM, 1900);
        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $transactionRepo->method('findByStripeEventId')->willReturn(null);
        $transactionRepo->method('findByStripeSessionId')->with('cs_claim_500')->willReturn($transaction);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(10)->willReturn($owner);

        $claimRepo = $this->createMock(SuccessionClaimRepository::class);
        $claimRepo->method('find')->with(77)->willReturn($claim);

        $workflow = $this->createMock(SuccessionClaimWorkflowService::class);
        $workflow->expects($this->once())->method('onOpeningFeePaid')->with($claim, 1900, 'pi_claim_500');

        $manager = new PaymentManager(
            $transactionRepo,
            $userRepo,
            $this->createMock(PlanRepository::class),
            $this->createMock(ItemRepository::class),
            $this->createMock(ItemTransferRepository::class),
            $this->createMock(CertificateIssuerService::class),
            $this->createMock(CertificateRepository::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            null,
            null,
            $claimRepo,
            $workflow,
        );

        $result = $manager->processWebhook([
            'id' => 'evt_claim_500',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_claim_500',
                    'amount_total' => 1900,
                    'payment_intent' => 'pi_claim_500',
                    'metadata' => [
                        'type' => 'succession_claim',
                        'claim_id' => '77',
                        'user_id' => '10',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result);
        $this->assertSame(PaymentStatus::PAID, $transaction->getStatus());
    }
}
