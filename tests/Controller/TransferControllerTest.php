<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\TransferController;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\AcquisitionType;
use App\Enum\ItemStatus;
use App\Enum\TransferStatus;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Service\Item\ItemLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class TransferControllerTest extends TestCase
{
    private function createItem(User $owner, string $cui = 'CAT-TEST-001', ItemStatus $status = ItemStatus::ACTIVE): Item
    {
        $category = new Category('Test Category', 'test-cat');
        $item = new Item(
            owner: $owner,
            cui: $cui,
            name: 'Relotge Vintage Test',
            category: $category
        );
        $item->setStatus($status);
        $item->setAcquisitionType(AcquisitionType::PURCHASE);

        $reflection = new \ReflectionClass(Item::class);
        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($item, 42);

        return $item;
    }

    private function createUser(string $email, string $firstName = 'Usuari', string $lastName = 'Test'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        $reflection = new \ReflectionClass(User::class);
        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($user, crc32($email));

        $plan = new Plan('free', 'Pla Free', 0);
        $user->setPlan($plan);

        return $user;
    }

    private function setupControllerContainer(
        TransferController $controller,
        ?User $user = null,
        bool $csrfValid = true,
        ?Environment $twig = null,
        ?Request $request = null
    ): void {
        $container = $this->createMock(ContainerInterface::class);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        if ($user !== null) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->method('getToken')->willReturn($token);
        } else {
            $tokenStorage->method('getToken')->willReturn(null);
        }

        $csrfManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn($csrfValid);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(function (string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH) {
            return '/' . $name . '?' . http_build_query($parameters);
        });

        $session = new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $targetRequest = $request ?? new Request();
        $targetRequest->setSession($session);
        $requestStack->push($targetRequest);

        $container->method('has')->willReturnCallback(function (string $id) use ($twig) {
            return in_array($id, [
                'security.authorization_checker',
                'security.token_storage',
                'security.csrf.token_manager',
                'router',
                'request_stack',
                'twig',
            ], true) && ($id !== 'twig' || $twig !== null);
        });

        $container->method('get')->willReturnCallback(function (string $id) use ($authChecker, $tokenStorage, $csrfManager, $urlGenerator, $requestStack, $twig) {
            return match ($id) {
                'security.authorization_checker' => $authChecker,
                'security.token_storage' => $tokenStorage,
                'security.csrf.token_manager' => $csrfManager,
                'router' => $urlGenerator,
                'request_stack' => $requestStack,
                'twig' => $twig,
                default => null,
            };
        });

        $controller->setContainer($container);
    }

    public function testIndexRendersTransfersList(): void
    {
        $user = $this->createUser('carles.test@registrals.cat');
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->expects($this->once())
            ->method('findPendingReceivedByEmail')
            ->with('carles.test@registrals.cat')
            ->willReturn([]);
        $transferRepo->expects($this->once())
            ->method('findPendingSentByUser')
            ->with($user)
            ->willReturn([]);

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('transfer/index.html.twig', $this->callback(function (array $params) {
                return isset($params['pendingReceived']) && isset($params['pendingSent']);
            }))
            ->willReturn('<html>Transfers Index</html>');

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, $user, true, $twig);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<html>Transfers Index</html>', $response->getContent());
    }

    public function testClaimNotFoundReturnsExpiredTemplate(): void
    {
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->method('findOneBy')->with(['token' => 'invalid-token'])->willReturn(null);

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('transfer/expired.html.twig', $this->callback(function (array $params) {
                return $params['reason'] === 'invalid_token' && $params['transfer'] === null;
            }))
            ->willReturn('<html>Expired</html>');

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, null, true, $twig);

        $response = $controller->claim('invalid-token');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<html>Expired</html>', $response->getContent());
    }

    public function testClaimPendingReturnsClaimTemplate(): void
    {
        $sender = $this->createUser('sender@test.cat', 'Sender User');
        $item = $this->createItem($sender, 'CAT-CLAIM-001');

        $transfer = new ItemTransfer(
            item: $item,
            sender: $sender,
            recipientEmail: 'receiver@test.cat'
        );

        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->method('findOneBy')->with(['token' => $transfer->getToken()])->willReturn($transfer);

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('transfer/claim.html.twig', $this->callback(function (array $params) use ($transfer, $item, $sender) {
                return $params['transfer'] === $transfer
                    && $params['item'] === $item
                    && $params['sender'] === $sender;
            }))
            ->willReturn('<html>Claim Page</html>');

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, null, true, $twig);

        $response = $controller->claim($transfer->getToken());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<html>Claim Page</html>', $response->getContent());
    }

    public function testInitiateTransferSuccess(): void
    {
        $sender = $this->createUser('sender@test.cat');
        $item = $this->createItem($sender, 'CAT-INITIATE-001');

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneBy')->with(['cui' => 'CAT-INITIATE-001'])->willReturn($item);

        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('initiateTransfer')
            ->with($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ItemTransfer::class));
        $em->expects($this->once())
            ->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send');

        $request = new Request([], [
            '_token' => 'valid-csrf-token',
            'recipient_email' => 'destinatari@test.cat',
            'security_code' => '998877',
        ]);

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, $sender, true, null, $request);

        $response = $controller->initiate('CAT-INITIATE-001', $request);

        $this->assertTrue($response->isRedirection());
    }

    public function testCancelTransferSuccess(): void
    {
        $sender = $this->createUser('sender@test.cat');
        $item = $this->createItem($sender, 'CAT-CANCEL-001', ItemStatus::TRANSFER_PENDING);

        $transfer = new ItemTransfer(
            item: $item,
            sender: $sender,
            recipientEmail: 'receiver@test.cat'
        );
        $item->addTransfer($transfer);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneBy')->with(['cui' => 'CAT-CANCEL-001'])->willReturn($item);

        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('cancelTransfer')
            ->with($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('flush');

        $mailer = $this->createMock(MailerInterface::class);

        $request = new Request([], ['_token' => 'valid-csrf-token']);

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, $sender, true, null, $request);

        $response = $controller->cancel('CAT-CANCEL-001', $request);

        $this->assertTrue($response->isRedirection());
        $this->assertSame(TransferStatus::CANCELLED, $transfer->getStatus());
    }

    public function testAcceptTransferSuccess(): void
    {
        $sender = $this->createUser('sender@test.cat');
        $receiver = $this->createUser('receiver@test.cat');

        $item = $this->createItem($sender, 'CAT-ACCEPT-001', ItemStatus::TRANSFER_PENDING);

        $transfer = new ItemTransfer(
            item: $item,
            sender: $sender,
            recipientEmail: 'receiver@test.cat',
            securityCode: '123456'
        );
        $item->addTransfer($transfer);

        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->method('findOneBy')->with(['token' => $transfer->getToken()])->willReturn($transfer);

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('completeTransfer')
            ->with($item, $receiver);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('flush');

        $mailer = $this->createMock(MailerInterface::class);

        $request = new Request([], [
            '_token' => 'valid-csrf-token',
            'security_code' => '123456',
        ]);

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, $receiver, true, null, $request);

        $response = $controller->accept($transfer->getToken(), $request);

        $this->assertTrue($response->isRedirection());
        $this->assertSame(TransferStatus::ACCEPTED, $transfer->getStatus());
        $this->assertSame($receiver, $transfer->getRecipient());
    }

    public function testRejectTransferSuccess(): void
    {
        $sender = $this->createUser('sender@test.cat');
        $item = $this->createItem($sender, 'CAT-REJECT-001', ItemStatus::TRANSFER_PENDING);

        $transfer = new ItemTransfer(
            item: $item,
            sender: $sender,
            recipientEmail: 'receiver@test.cat'
        );
        $item->addTransfer($transfer);

        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->method('findOneBy')->with(['token' => $transfer->getToken()])->willReturn($transfer);

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('rejectTransfer')
            ->with($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('flush');

        $mailer = $this->createMock(MailerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('transfer/expired.html.twig', $this->callback(function (array $params) use ($transfer) {
                return $params['reason'] === 'just_rejected' && $params['transfer'] === $transfer;
            }))
            ->willReturn('<html>Rejected</html>');

        $request = new Request([], [
            '_token' => 'valid-csrf-token',
            'reason' => 'No és el meu rellotge',
        ]);

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, null, true, $twig, $request);

        $response = $controller->reject($transfer->getToken(), $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(TransferStatus::REJECTED, $transfer->getStatus());
        $this->assertSame('No és el meu rellotge', $transfer->getRejectionReason());
    }

    public function testIndexRendersPendingAndHistoryTransfers(): void
    {
        $user = $this->createUser('user@test.cat');
        $item = $this->createItem($user, 'CAT-HIST-001', ItemStatus::ACTIVE);

        $transfer = new ItemTransfer(
            item: $item,
            sender: $user,
            recipientEmail: 'receiver@test.cat'
        );

        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->method('findPendingReceivedByEmail')->with('user@test.cat')->willReturn([]);
        $transferRepo->method('findPendingSentByUser')->with($user)->willReturn([]);
        $transferRepo->method('findHistoryReceivedForUser')->with($user)->willReturn([]);
        $transferRepo->method('findHistorySentForUser')->with($user)->willReturn([$transfer]);

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('transfer/index.html.twig', $this->callback(function (array $params) use ($transfer) {
                return isset($params['pendingReceived'], $params['pendingSent'], $params['historyReceived'], $params['historySent'])
                    && $params['historySent'] === [$transfer];
            }))
            ->willReturn('<html>Transfers Index</html>');

        $controller = new TransferController($itemRepo, $transferRepo, $lifecycle, $em, $mailer);
        $this->setupControllerContainer($controller, $user, true, $twig);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Transfers Index', (string) $response->getContent());
    }
}
