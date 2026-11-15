<?php

declare(strict_types=1);

namespace App\Tests\Service\Plan;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemDocument;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Service\Plan\DocumentQuotaPolicy;
use PHPUnit\Framework\TestCase;

final class DocumentQuotaPolicyTest extends TestCase
{
    public function testFreePlanFreezesExtraPhotosAndEvidencesWhileKeepingPrimaryAndOldest(): void
    {
        $freePlan = (new Plan('free', 'Pla Free'))
            ->setMaxPhotosPerItem(1)
            ->setMaxEvidencesPerItem(1);

        $owner = (new User())->setPlan($freePlan);
        $item = new Item($owner, 'REG-2026-TEST01', 'Test Item', new Category('Tech', 'tech'));

        $p1 = $this->makeDoc($item, 1, DocumentType::PHOTO_DETAIL, '-10 minutes');
        $p2 = $this->makeDoc($item, 2, DocumentType::PHOTO_PRIMARY, '-5 minutes');
        $p3 = $this->makeDoc($item, 3, DocumentType::PHOTO_DETAIL, '-1 minute');

        $e1 = $this->makeDoc($item, 10, DocumentType::INVOICE_RECEIPT, '-20 minutes');
        $e2 = $this->makeDoc($item, 11, DocumentType::CERTIFICATE_AUTHENTICITY, '-10 minutes');
        $e3 = $this->makeDoc($item, 12, DocumentType::LEGAL_DECLARATION, '-5 minutes');

        foreach ([$p1, $p2, $p3, $e1, $e2, $e3] as $doc) {
            $item->addDocument($doc);
        }

        $policy = new DocumentQuotaPolicy();
        $partition = $policy->partitionDocuments($item, $freePlan);

        $this->assertCount(1, $partition['activePhotos']);
        $this->assertSame($p2, $partition['activePhotos'][0]);
        $this->assertSame(2, $partition['frozenPhotosCount']);

        $this->assertCount(1, $partition['activeEvidences']);
        $this->assertSame($e1, $partition['activeEvidences'][0]);
        $this->assertSame(2, $partition['frozenEvidencesCount']);

        $this->assertTrue($policy->isDocumentAccessibleByUser($p2, $owner));
        $this->assertFalse($policy->isDocumentAccessibleByUser($p1, $owner));
        $this->assertFalse($policy->isDocumentAccessibleByUser($p3, $owner));

        $this->assertTrue($policy->isDocumentAccessibleByUser($e1, $owner));
        $this->assertFalse($policy->isDocumentAccessibleByUser($e2, $owner));
        $this->assertFalse($policy->isDocumentAccessibleByUser($e3, $owner));
    }

    public function testUpgradingBackToPlusUnlocksAllPreviouslyFrozenDocuments(): void
    {
        $plusPlan = (new Plan('plus', 'Pla Plus'))
            ->setMaxPhotosPerItem(5)
            ->setMaxEvidencesPerItem(5);

        $owner = (new User())->setPlan($plusPlan);
        $item = new Item($owner, 'REG-2026-TEST01', 'Test Item', new Category('Tech', 'tech'));

        $p1 = $this->makeDoc($item, 1, DocumentType::PHOTO_PRIMARY, '-10 minutes');
        $p2 = $this->makeDoc($item, 2, DocumentType::PHOTO_DETAIL, '-5 minutes');
        $e1 = $this->makeDoc($item, 10, DocumentType::INVOICE_RECEIPT, '-20 minutes');
        $e2 = $this->makeDoc($item, 11, DocumentType::CERTIFICATE_AUTHENTICITY, '-10 minutes');

        foreach ([$p1, $p2, $e1, $e2] as $doc) {
            $item->addDocument($doc);
        }

        $policy = new DocumentQuotaPolicy();
        $partition = $policy->partitionDocuments($item, $plusPlan);

        $this->assertSame(0, $partition['frozenPhotosCount']);
        $this->assertSame(0, $partition['frozenEvidencesCount']);
        $this->assertTrue($policy->isDocumentAccessibleByUser($p2, $owner));
        $this->assertTrue($policy->isDocumentAccessibleByUser($e2, $owner));
    }

    public function testAdminCanAlwaysAccessFrozenDocuments(): void
    {
        $freePlan = (new Plan('free', 'Pla Free'))
            ->setMaxPhotosPerItem(1)
            ->setMaxEvidencesPerItem(1);

        $owner = (new User())->setPlan($freePlan);
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $item = new Item($owner, 'REG-2026-TEST01', 'Test Item', new Category('Tech', 'tech'));

        $p1 = $this->makeDoc($item, 1, DocumentType::PHOTO_PRIMARY, '-10 minutes');
        $p2 = $this->makeDoc($item, 2, DocumentType::PHOTO_DETAIL, '-5 minutes');
        $item->addDocument($p1);
        $item->addDocument($p2);

        $policy = new DocumentQuotaPolicy();

        $this->assertFalse($policy->isDocumentAccessibleByUser($p2, $owner));
        $this->assertTrue($policy->isDocumentAccessibleByUser($p2, $admin));
    }

    private function makeDoc(Item $item, int $id, DocumentType $type, string $offset): ItemDocument
    {
        $doc = new ItemDocument(
            $item,
            $type,
            sprintf('items/doc-%d.bin', $id),
            sprintf('doc-%d.bin', $id),
            'application/octet-stream',
            1024,
            str_repeat('a', 64)
        );

        $idRef = new \ReflectionProperty(ItemDocument::class, 'id');
        $idRef->setValue($doc, $id);

        $createdAtRef = new \ReflectionProperty(ItemDocument::class, 'createdAt');
        $createdAtRef->setValue($doc, new \DateTimeImmutable($offset));

        return $doc;
    }
}
