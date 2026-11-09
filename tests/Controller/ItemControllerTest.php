<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Audit\Service\UserActivityLogger;
use App\Controller\ItemController;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemDocument;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\ItemStatus;
use App\Repository\ItemRepository;
use App\Security\Voter\ItemVoter;
use App\Service\Item\ItemLifecycleService;
use App\Service\Storage\SecureDocumentStorageService;
use App\Service\Validation\MediaIntegrityValidator;
use App\Service\Validation\MediaValidationResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ItemControllerTest extends TestCase
{
    private function createUser(string $email, string $planCode = 'free', int $maxPhotos = 1, int $maxEvidences = 1): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');

        $reflection = new \ReflectionClass(User::class);
        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($user, crc32($email));

        $plan = new Plan($planCode, ucfirst($planCode), 0);
        $plan->setMaxPhotosPerItem($maxPhotos);
        $plan->setMaxEvidencesPerItem($maxEvidences);
        $user->setPlan($plan);

        return $user;
    }

    private function createItem(User $owner, string $cui = 'REG-2026-TEST01', ItemStatus $status = ItemStatus::ACTIVE): Item
    {
        $cat = new Category('Art', 'art');
        $item = new Item($owner, $cui, 'Quadre de prova', $cat);
        $item->setStatus($status);

        $reflection = new \ReflectionClass(Item::class);
        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($item, 101);

        return $item;
    }

    private function setupControllerContainer(
        ItemController $controller,
        User $user,
        bool $csrfValid = true,
        ?Request $request = null
    ): void {
        $container = $this->createMock(ContainerInterface::class);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $tokenStorage->method('getToken')->willReturn($token);

        $csrfManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn($csrfValid);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(function (string $name, array $params = []) {
            return '/' . $name . '?' . http_build_query($params);
        });

        $session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();
        $targetRequest = $request ?? new Request();
        $targetRequest->setSession($session);
        $requestStack->push($targetRequest);

        $container->method('has')->willReturnCallback(function (string $id) {
            return in_array($id, [
                'security.authorization_checker',
                'security.token_storage',
                'security.csrf.token_manager',
                'router',
                'request_stack',
            ], true);
        });

        $container->method('get')->willReturnCallback(function (string $id) use ($authChecker, $tokenStorage, $csrfManager, $urlGenerator, $requestStack) {
            return match ($id) {
                'security.authorization_checker' => $authChecker,
                'security.token_storage' => $tokenStorage,
                'security.csrf.token_manager' => $csrfManager,
                'router' => $urlGenerator,
                'request_stack' => $requestStack,
                default => null,
            };
        });

        $controller->setContainer($container);
    }

    public function testAddDocumentEnforcesPhotoQuotaOnFreePlan(): void
    {
        $user = $this->createUser('carles@test.cat', 'free', maxPhotos: 1, maxEvidences: 1);
        $item = $this->createItem($user);

        // Simulem que el bé ja té 1 foto existent
        $doc = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/photo.jpg', 'photo.jpg', 'image/jpeg', 1024, 'hash1');
        $item->addDocument($doc);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $validator = $this->createMock(MediaIntegrityValidator::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(UserActivityLogger::class);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_photo_');
        file_put_contents($tempFile, 'DUMMY_IMAGE_DATA');
        $uploadedFile = new UploadedFile($tempFile, 'nova_foto.jpg', 'image/jpeg', null, true);

        $request = new Request(
            request: ['_token' => 'valid_token', 'kind' => 'photo'],
            files: ['file' => $uploadedFile]
        );

        $response = $controller->addDocument(
            $item->getCui(),
            $request,
            $itemRepo,
            $storage,
            $validator,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        // Comprovem que no s'ha cridat storeUploadedDocument
        $this->assertCount(1, $item->getPhotos());
        @unlink($tempFile);
    }

    public function testAddDocumentAllowsMultiplePhotosOnPlusPlan(): void
    {
        $user = $this->createUser('carles@test.cat', 'plus', maxPhotos: 5, maxEvidences: 5);
        $item = $this->createItem($user);

        // 1 foto existent
        $doc1 = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/photo1.jpg', 'photo1.jpg', 'image/jpeg', 1024, 'hash1');
        $item->addDocument($doc1);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_photo_');
        file_put_contents($tempFile, 'DUMMY_IMAGE_DATA');
        $uploadedFile = new UploadedFile($tempFile, 'nova_foto_detall.jpg', 'image/jpeg', null, true);

        $validator = $this->createMock(MediaIntegrityValidator::class);
        $validator->method('validateItemPhoto')->willReturn(MediaValidationResult::valid(['camera' => 'Nikon']));

        $doc2 = new ItemDocument($item, DocumentType::PHOTO_DETAIL, 'path/photo2.webp', 'nova_foto_detall.jpg', 'image/webp', 2048, 'hash2');

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $storage->expects($this->once())
            ->method('storeUploadedDocument')
            ->with($item, $uploadedFile, DocumentType::PHOTO_DETAIL, ['camera' => 'Nikon'])
            ->willReturn($doc2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())->method('log');

        $request = new Request(
            request: ['_token' => 'valid_token', 'kind' => 'photo'],
            files: ['file' => $uploadedFile]
        );

        $response = $controller->addDocument(
            $item->getCui(),
            $request,
            $itemRepo,
            $storage,
            $validator,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        @unlink($tempFile);
    }

    public function testAddDocumentEnforcesEvidenceQuota(): void
    {
        $user = $this->createUser('carles@test.cat', 'free', maxPhotos: 1, maxEvidences: 1);
        $item = $this->createItem($user);

        // Ja té 1 prova
        $doc = new ItemDocument($item, DocumentType::INVOICE_RECEIPT, 'path/invoice.pdf', 'invoice.pdf', 'application/pdf', 5000, 'hash_inv');
        $item->addDocument($doc);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $validator = $this->createMock(MediaIntegrityValidator::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(UserActivityLogger::class);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tempFile, '%PDF-1.4');
        $uploadedFile = new UploadedFile($tempFile, 'second_invoice.pdf', 'application/pdf', null, true);

        $request = new Request(
            request: ['_token' => 'valid_token', 'kind' => 'evidence', 'evidence_type' => 'invoice_receipt'],
            files: ['file' => $uploadedFile]
        );

        $response = $controller->addDocument(
            $item->getCui(),
            $request,
            $itemRepo,
            $storage,
            $validator,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertCount(1, $item->getEvidenceDocuments());
        @unlink($tempFile);
    }

    public function testMakePrimaryPromotesDetailPhotoAndDemotesOldPrimary(): void
    {
        $user = $this->createUser('carles@test.cat', 'plus', maxPhotos: 5, maxEvidences: 5);
        $item = $this->createItem($user);

        $photo1 = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/photo1.jpg', 'photo1.jpg', 'image/jpeg', 1024, 'hash1');
        $photo2 = new ItemDocument($item, DocumentType::PHOTO_DETAIL, 'path/photo2.jpg', 'photo2.jpg', 'image/jpeg', 2048, 'hash2');
        $item->addDocument($photo1);
        $item->addDocument($photo2);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())->method('log');

        $request = new Request(request: ['_token' => 'valid_token']);

        $response = $controller->makePrimary(
            $item->getCui(),
            $photo2->getUuid()->toRfc4122(),
            $request,
            $itemRepo,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame(DocumentType::PHOTO_PRIMARY, $photo2->getType());
        $this->assertSame(DocumentType::PHOTO_DETAIL, $photo1->getType());
    }

    public function testDeleteDocumentEnforcesSinglePhotoInvariant(): void
    {
        $user = $this->createUser('carles@test.cat', 'free', maxPhotos: 1, maxEvidences: 1);
        $item = $this->createItem($user);

        // Només 1 foto
        $photo1 = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/photo1.jpg', 'photo1.jpg', 'image/jpeg', 1024, 'hash1');
        $item->addDocument($photo1);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $storage->expects($this->never())->method('deleteDocumentFile');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);

        $request = new Request(request: ['_token' => 'valid_token']);

        $response = $controller->deleteDocument(
            $item->getCui(),
            $photo1->getUuid()->toRfc4122(),
            $request,
            $itemRepo,
            $storage,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertCount(1, $item->getPhotos());
    }

    public function testDeleteDocumentAllowsDeletingSecondaryPhoto(): void
    {
        $user = $this->createUser('carles@test.cat', 'plus', maxPhotos: 5, maxEvidences: 5);
        $item = $this->createItem($user);

        $photo1 = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/photo1.jpg', 'photo1.jpg', 'image/jpeg', 1024, 'hash1');
        $photo2 = new ItemDocument($item, DocumentType::PHOTO_DETAIL, 'path/photo2.jpg', 'photo2.jpg', 'image/jpeg', 2048, 'hash2');
        $item->addDocument($photo1);
        $item->addDocument($photo2);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $storage->expects($this->once())->method('deleteDocumentFile')->with($photo2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())->method('log');

        $request = new Request(request: ['_token' => 'valid_token']);

        $response = $controller->deleteDocument(
            $item->getCui(),
            $photo2->getUuid()->toRfc4122(),
            $request,
            $itemRepo,
            $storage,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertCount(1, $item->getPhotos());
        $this->assertSame($photo1, $item->getMainPhoto());
    }

    public function testReplaceDocumentReplacesExistingPhoto(): void
    {
        $user = $this->createUser('carles@test.cat', 'plus', maxPhotos: 5, maxEvidences: 5);
        $item = $this->createItem($user);

        $oldPhoto = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/old_photo.jpg', 'old_photo.jpg', 'image/jpeg', 1024, 'hash_old');
        $item->addDocument($oldPhoto);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with($item->getCui())->willReturn($item);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_replace_');
        file_put_contents($tempFile, 'NEW_PHOTO_BINARY');
        $uploadedFile = new UploadedFile($tempFile, 'nova_foto_reemplacada.jpg', 'image/jpeg', null, true);

        $validator = $this->createMock(MediaIntegrityValidator::class);
        $validator->method('validateItemPhoto')->willReturn(MediaValidationResult::valid());

        $newPhoto = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/new_photo.webp', 'nova_foto_reemplacada.jpg', 'image/webp', 2048, 'hash_new');

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $storage->expects($this->once())->method('deleteDocumentFile')->with($oldPhoto);
        $storage->expects($this->once())->method('storeUploadedDocument')->willReturn($newPhoto);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())->method('log');

        $request = new Request(
            request: ['_token' => 'valid_token'],
            files: ['file' => $uploadedFile]
        );

        $response = $controller->replaceDocument(
            $item->getCui(),
            $oldPhoto->getUuid()->toRfc4122(),
            $request,
            $itemRepo,
            $storage,
            $validator,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        @unlink($tempFile);
    }

    public function testDeactivatePermanentlyDeletesUnprotectedItem(): void
    {
        $user = $this->createUser('carles@test.cat', 'free');
        $item = $this->createItem($user, 'REG-2026-UNPROTECTED');

        $doc = new ItemDocument($item, DocumentType::PHOTO_PRIMARY, 'path/photo.jpg', 'photo.jpg', 'image/jpeg', 1024, 'hash');
        $item->addDocument($doc);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with('REG-2026-UNPROTECTED')->willReturn($item);

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $storage->expects($this->once())->method('deleteDocumentFile')->with($doc);

        $lifecycle = $this->createMock(ItemLifecycleService::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($item);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())->method('log');

        $request = new Request(request: [
            '_token' => 'valid_token',
            'action_type' => 'delete',
        ]);

        $response = $controller->deactivate(
            'REG-2026-UNPROTECTED',
            $request,
            $itemRepo,
            $storage,
            $lifecycle,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testDeactivateArchivesLegallyProtectedItemEvenIfDeleteRequested(): void
    {
        $user = $this->createUser('carles@test.cat', 'plus');
        $item = $this->createItem($user, 'REG-2026-PROTECTED');

        // Creem un certificat oficial que protegeix el bé
        $cert = new \App\Entity\Certificate(
            $item,
            $user,
            'CERT-2026-TEST',
            \App\Enum\CertificateMode::OFFICIAL,
            'certs/test.pdf',
            'hash_pdf',
            'fingerprint_sha256'
        );
        $item->addCertificate($cert);

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with('REG-2026-PROTECTED')->willReturn($item);

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $storage->expects($this->never())->method('deleteDocumentFile');

        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $lifecycle->expects($this->once())->method('archive')->with($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');

        $logger = $this->createMock(UserActivityLogger::class);

        $request = new Request(request: [
            '_token' => 'valid_token',
            'action_type' => 'delete', // L'usuari demana esborrar, però el backend força arxiu per protecció jurídica
        ]);

        $response = $controller->deactivate(
            'REG-2026-PROTECTED',
            $request,
            $itemRepo,
            $storage,
            $lifecycle,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testBatchDeactivateArchivesMultipleItems(): void
    {
        $user = $this->createUser('carles@test.cat', 'plus');
        $item1 = $this->createItem($user, 'REG-2026-BATCH01');
        $item2 = $this->createItem($user, 'REG-2026-BATCH02');

        $controller = new ItemController();
        $this->setupControllerContainer($controller, $user, true);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->willReturnCallback(function (string $cui) use ($item1, $item2) {
            return $cui === 'REG-2026-BATCH01' ? $item1 : ($cui === 'REG-2026-BATCH02' ? $item2 : null);
        });

        $storage = $this->createMock(SecureDocumentStorageService::class);
        $lifecycle = $this->createMock(ItemLifecycleService::class);
        $lifecycle->expects($this->exactly(2))->method('archive');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);

        $request = new Request(request: [
            '_token' => 'valid_token',
            'action_type' => 'archive',
            'cuis' => ['REG-2026-BATCH01', 'REG-2026-BATCH02'],
        ]);

        $response = $controller->batchDeactivate(
            $request,
            $itemRepo,
            $storage,
            $lifecycle,
            $em,
            $logger
        );

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }
}

