<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BlockchainActionType;
use App\Repository\BlockchainReceiptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlockchainReceiptRepository::class)]
#[ORM\Table(name: 'blockchain_receipts')]
#[ORM\Index(columns: ['item_id', 'action_type'], name: 'idx_receipt_item_action')]
#[ORM\Index(columns: ['tx_hash'], name: 'idx_receipt_tx')]
#[ORM\Index(columns: ['anchored_hash'], name: 'idx_receipt_anchored_hash')]
class BlockchainReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'blockchainReceipts')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $certificateId = null;

    #[ORM\Column(type: 'string', length: 30, enumType: BlockchainActionType::class)]
    private BlockchainActionType $actionType;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'polygon-pos'])]
    private string $network = 'polygon-pos';

    #[ORM\Column(type: 'string', length: 42)]
    private string $contractAddress;

    #[ORM\Column(type: 'string', length: 66)]
    private string $txHash;

    #[ORM\Column(type: 'bigint')]
    private int $blockNumber;

    #[ORM\Column(type: 'string', length: 64)]
    private string $anchoredHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $anchoredAt;

    public function __construct(
        Item $item,
        BlockchainActionType $actionType,
        string $contractAddress,
        string $txHash,
        int $blockNumber,
        string $anchoredHash,
        string $network = 'polygon-pos',
        ?int $certificateId = null
    ) {
        $this->item = $item;
        $this->actionType = $actionType;
        $this->contractAddress = $contractAddress;
        $this->txHash = $txHash;
        $this->blockNumber = $blockNumber;
        $this->anchoredHash = $anchoredHash;
        $this->network = $network;
        $this->certificateId = $certificateId;
        $this->anchoredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function setItem(Item $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getCertificateId(): ?int
    {
        return $this->certificateId;
    }

    public function setCertificateId(?int $certificateId): static
    {
        $this->certificateId = $certificateId;

        return $this;
    }

    public function getActionType(): BlockchainActionType
    {
        return $this->actionType;
    }

    public function setActionType(BlockchainActionType $actionType): static
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    public function setNetwork(string $network): static
    {
        $this->network = $network;

        return $this;
    }

    public function getContractAddress(): string
    {
        return $this->contractAddress;
    }

    public function setContractAddress(string $contractAddress): static
    {
        $this->contractAddress = $contractAddress;

        return $this;
    }

    public function getTxHash(): string
    {
        return $this->txHash;
    }

    public function setTxHash(string $txHash): static
    {
        $this->txHash = $txHash;

        return $this;
    }

    public function getBlockNumber(): int
    {
        return $this->blockNumber;
    }

    public function setBlockNumber(int $blockNumber): static
    {
        $this->blockNumber = $blockNumber;

        return $this;
    }

    public function getAnchoredHash(): string
    {
        return $this->anchoredHash;
    }

    public function setAnchoredHash(string $anchoredHash): static
    {
        $this->anchoredHash = $anchoredHash;

        return $this;
    }

    public function getAnchoredAt(): \DateTimeImmutable
    {
        return $this->anchoredAt;
    }

    public function getExplorerUrl(): string
    {
        if (str_contains(strtolower($this->network), 'amoy')) {
            return sprintf('https://amoy.polygonscan.com/tx/%s', $this->txHash);
        }

        return sprintf('https://polygonscan.com/tx/%s', $this->txHash);
    }

    public function getShortTxHash(): string
    {
        if (strlen($this->txHash) <= 14) {
            return $this->txHash;
        }

        return substr($this->txHash, 0, 8) . '...' . substr($this->txHash, -6);
    }

    public function getShortAnchoredHash(): string
    {
        if (strlen($this->anchoredHash) <= 14) {
            return $this->anchoredHash;
        }

        return substr($this->anchoredHash, 0, 8) . '...' . substr($this->anchoredHash, -6);
    }
}
