<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\BlockchainActionType;

/**
 * Missatge asíncron per a l'ancoratge i notarització d'un bé o operació a Polygon Layer 2.
 */
final readonly class AnchorBlockchainMessage
{
    public function __construct(
        public int $itemId,
        public BlockchainActionType $actionType = BlockchainActionType::ITEM_REGISTRATION,
        public ?string $customHash = null,
        public ?int $certificateId = null
    ) {
    }
}
