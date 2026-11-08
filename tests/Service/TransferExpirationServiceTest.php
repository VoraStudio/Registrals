<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\User;
use App\Enum\ItemStatus;
use App\Enum\TransferStatus;
use App\Repository\ItemTransferRepository;
use App\Service\Item\ItemLifecycleService;
use App\Service\Transfer\TransferExpirationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TransferExpirationServiceTest extends TestCase
{
    private ItemTransferRepository $transferRepository;
    private ItemLifecycleService $lifecycleService;
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private UserActivityLogger $activityLogger;
    private LoggerInterface $logger;
    private TransferExpirationService $service;

    protected function setUp(): void
    {
        $this->transferRepository = $this->createMock(ItemTransferRepository::class);
        $this->lifecycleService = $this->createMock(ItemLifecycleService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->activityLogger = $this->createMock(UserActivityLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new TransferExpirationService(
            $this->transferRepository,
            $this->lifecycleService,
            $this->entityManager,
            $this->mailer,
            $this->urlGenerator,
            $this->activityLogger,
            $this->logger
        );
    }

    public function testExpireTransfersWhenNoneFoundReturnsZero(): void
    {
        $this->transferRepository->expects($this->once())
            ->method('findExpiredPendingTransfers')
            ->with(null)
            ->willReturn([]);

        $count = $this->service->expireTransfers();

        $this->assertSame(0, $count);
    }

    public function testExpireTransfersTransitionsItemAndSendsEmailAndLogsAudit(): void
    {
        $sender = new User();
        $sender->setEmail('sender@registrals.cat');
        $sender->setFirstName('Carles');
        $sender->setLastName('Test');

        $category = new Category('Art', 'art');

        $item = new Item($sender, 'REG-2026-TEST01', 'Quadre Antic', $category);
        $item->setStatus(ItemStatus::TRANSFER_PENDING);

        $transfer = new ItemTransfer(
            item: $item,
            sender: $sender,
            recipientEmail: 'destinatari@registrals.cat',
            transferFeeCents: 290,
            durationHours: 72
        );

        $this->assertSame(TransferStatus::PENDING, $transfer->getStatus());

        $this->transferRepository->expects($this->once())
            ->method('findExpiredPendingTransfers')
            ->with($sender)
            ->willReturn([$transfer]);

        $this->lifecycleService->expects($this->once())
            ->method('expireTransfer')
            ->with($item);

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://registrals.cat/app/items/' . $item->getCui());

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(RawMessage::class));

        $this->activityLogger->expects($this->once())
            ->method('log')
            ->with(
                $sender,
                UserActivityAction::TRANSFER_EXPIRED,
                $this->stringContains($item->getCui())
            );

        $this->entityManager->expects($this->once())
            ->method('flush');

        $count = $this->service->expireTransfers($sender);

        $this->assertSame(1, $count);
        $this->assertSame(TransferStatus::EXPIRED, $transfer->getStatus());
        $this->assertNotNull($transfer->getCompletedAt());
    }
}
