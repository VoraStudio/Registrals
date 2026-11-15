<?php

declare(strict_types=1);

namespace App\Service\Plan;

use App\Entity\Item;
use App\Entity\ItemDocument;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\DocumentType;

/**
 * Resolves which photos and evidence documents of an item are actively accessible
 * under the current plan quota versus held in frozen custody after a downgrade.
 */
final class DocumentQuotaPolicy
{
    /**
     * @return array{
     *     activePhotos: list<ItemDocument>,
     *     frozenPhotosCount: int,
     *     activeEvidences: list<ItemDocument>,
     *     frozenEvidencesCount: int,
     *     frozenDocumentIds: array<int, true>
     * }
     */
    public function partitionDocuments(Item $item, ?Plan $plan): array
    {
        $photos = array_values($item->getPhotos()->toArray());
        usort($photos, static function (ItemDocument $a, ItemDocument $b): int {
            $aPrimary = $a->getType() === DocumentType::PHOTO_PRIMARY ? 0 : 1;
            $bPrimary = $b->getType() === DocumentType::PHOTO_PRIMARY ? 0 : 1;
            if ($aPrimary !== $bPrimary) {
                return $aPrimary <=> $bPrimary;
            }

            $timeCmp = $a->getCreatedAt() <=> $b->getCreatedAt();
            if ($timeCmp !== 0) {
                return $timeCmp;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        $evidences = array_values($item->getEvidenceDocuments()->toArray());
        usort($evidences, static function (ItemDocument $a, ItemDocument $b): int {
            $timeCmp = $a->getCreatedAt() <=> $b->getCreatedAt();
            if ($timeCmp !== 0) {
                return $timeCmp;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        $maxPhotos = $plan?->getMaxPhotosPerItem() ?? 1;
        $maxEvidences = $plan?->getMaxEvidencesPerItem() ?? 1;

        [$activePhotos, $frozenPhotos] = $this->sliceByLimit($photos, $maxPhotos);
        [$activeEvidences, $frozenEvidences] = $this->sliceByLimit($evidences, $maxEvidences);

        $frozenDocumentIds = [];
        foreach (array_merge($frozenPhotos, $frozenEvidences) as $frozenDoc) {
            $docId = $frozenDoc->getId();
            if ($docId !== null) {
                $frozenDocumentIds[$docId] = true;
            }
        }

        return [
            'activePhotos' => $activePhotos,
            'frozenPhotosCount' => count($frozenPhotos),
            'activeEvidences' => $activeEvidences,
            'frozenEvidencesCount' => count($frozenEvidences),
            'frozenDocumentIds' => $frozenDocumentIds,
        ];
    }

    public function isDocumentAccessibleByUser(ItemDocument $document, User $user): bool
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_GESTOR', $roles, true)) {
            return true;
        }

        $docId = $document->getId();
        if ($docId === null) {
            return true;
        }

        $item = $document->getItem();
        $partition = $this->partitionDocuments($item, $item->getOwner()->getPlan());

        return !isset($partition['frozenDocumentIds'][$docId]);
    }

    /**
     * @param list<ItemDocument> $documents
     * @return array{0: list<ItemDocument>, 1: list<ItemDocument>}
     */
    private function sliceByLimit(array $documents, int $limit): array
    {
        if ($limit <= 0 || $limit >= 9999 || count($documents) <= $limit) {
            return [$documents, []];
        }

        return [
            array_slice($documents, 0, $limit),
            array_slice($documents, $limit),
        ];
    }
}

