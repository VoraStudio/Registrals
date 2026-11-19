<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Blockchain;

use App\DTO\BlockchainReceiptDto;
use App\Service\ItemFingerprintCalculator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Adaptador d'infraestructura per a la notarització a Polygon PoS (Layer 2) mitjançant JSON-RPC.
 */
class PolygonAnchorClient implements BlockchainAnchorInterface
{
    private const ZERO_ADDRESS = '0x0000000000000000000000000000000000000000';
    private const MIN_PRIORITY_FEE_WEI = 30_000_000_000; // 30 Gwei (mínim recomanat Polygon PoS / Amoy)

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $rpcUrl,
        private readonly string $contractAddress,
        private readonly string $walletPrivateKey = '',
        private readonly string $network = 'polygon-pos',
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ItemFingerprintCalculator $calculator = null,
        private readonly ?EvmTransactionSigner $signer = null
    ) {
    }

    public function calculateItemFingerprint(string $cui, string $ownerUuid, array $documentHashes, string $timestamp): string
    {
        $calc = $this->calculator ?? new ItemFingerprintCalculator();

        return $calc->calculateRaw($cui, $ownerUuid, $documentHashes, $timestamp);
    }

    public function anchorHash(string $cui, string $sha256Hash): BlockchainReceiptDto
    {
        if (trim($this->walletPrivateKey) !== '') {
            return $this->anchorRealTransaction($cui, $sha256Hash);
        }

        return $this->anchorSimulatedTransaction($cui, $sha256Hash);
    }

    private function anchorRealTransaction(string $cui, string $sha256Hash): BlockchainReceiptDto
    {
        $signer = $this->signer ?? new EvmTransactionSigner();
        $fromAddress = $signer->deriveAddress($this->walletPrivateKey);
        $toAddress = ($this->contractAddress !== '' && strtolower($this->contractAddress) !== self::ZERO_ADDRESS)
            ? $this->contractAddress
            : $fromAddress;

        $calldataHex = '0x' . bin2hex(sprintf('REGISTRALS|%s|%s', $cui, $sha256Hash));

        $chainIdHex = (string) $this->rpcCall('eth_chainId');
        $chainId = (int) hexdec($chainIdHex);

        $nonceHex = (string) $this->rpcCall('eth_getTransactionCount', [$fromAddress, 'pending']);
        $nonce = (int) hexdec($nonceHex);

        [$priorityFeeHex, $maxFeeHex] = $this->resolveEip1559Fees();
        $gasLimit = $this->estimateGas($fromAddress, $toAddress, $calldataHex);

        $rawTx = $signer->signEip1559Transaction(
            privateKeyHex: $this->walletPrivateKey,
            chainId: $chainId,
            nonce: $nonce,
            maxPriorityFeePerGasWeiHex: $priorityFeeHex,
            maxFeePerGasWeiHex: $maxFeeHex,
            gasLimit: $gasLimit,
            toAddress: $toAddress,
            valueWeiHex: '0x0',
            dataHex: $calldataHex
        );

        $txHash = (string) $this->rpcCall('eth_sendRawTransaction', [$rawTx]);

        [$blockNumber, $gasUsed] = $this->awaitReceipt($txHash, $gasLimit);

        $this->logger?->info('Ancoratge real completat a Polygon L2', [
            'cui' => $cui,
            'from' => $fromAddress,
            'chain_id' => $chainId,
            'tx_hash' => $txHash,
            'block_number' => $blockNumber,
            'gas_used' => $gasUsed,
            'anchored_hash' => $sha256Hash,
            'network' => $this->network,
        ]);

        return new BlockchainReceiptDto(
            txHash: $txHash,
            blockNumber: $blockNumber,
            anchoredHash: $sha256Hash,
            contractAddress: $toAddress,
            network: $this->network,
            anchoredAt: new \DateTimeImmutable(),
            isSuccess: true,
            errorMessage: null,
            gasUsed: $gasUsed
        );
    }

    private function anchorSimulatedTransaction(string $cui, string $sha256Hash): BlockchainReceiptDto
    {
        $currentBlock = 0;

        try {
            $blockHex = $this->rpcCall('eth_blockNumber');
            if (is_string($blockHex)) {
                $currentBlock = (int) hexdec($blockHex);
            }
        } catch (Throwable $e) {
            $this->logger?->warning('No s\'ha pogut obtenir el bloc actual de Polygon RPC: ' . $e->getMessage(), [
                'rpc_url' => $this->rpcUrl,
                'cui' => $cui,
            ]);
            $currentBlock = time();
        }

        $txHash = '0x' . hash('sha256', sprintf('%s:%s:%s:%d', $cui, $sha256Hash, microtime(true), $currentBlock));

        $this->logger?->info('Ancoratge simulat (sense clau privada de Hot Wallet) a Polygon L2', [
            'cui' => $cui,
            'tx_hash' => $txHash,
            'block_number' => $currentBlock,
            'anchored_hash' => $sha256Hash,
            'network' => $this->network,
        ]);

        return new BlockchainReceiptDto(
            txHash: $txHash,
            blockNumber: $currentBlock,
            anchoredHash: $sha256Hash,
            contractAddress: $this->contractAddress !== '' ? $this->contractAddress : self::ZERO_ADDRESS,
            network: $this->network,
            anchoredAt: new \DateTimeImmutable(),
            isSuccess: true,
            errorMessage: null,
            gasUsed: 21000
        );
    }

    /**
     * @return array{0: string, 1: string} [maxPriorityFeePerGasHex, maxFeePerGasHex]
     */
    private function resolveEip1559Fees(): array
    {
        $priorityWei = self::MIN_PRIORITY_FEE_WEI;
        try {
            $rpcPriority = $this->rpcCall('eth_maxPriorityFeePerGas');
            if (is_string($rpcPriority)) {
                $priorityWei = max($priorityWei, (int) hexdec($rpcPriority));
            }
        } catch (Throwable) {
        }

        $baseFeeWei = 30_000_000_000;
        try {
            $latestBlock = $this->rpcCall('eth_getBlockByNumber', ['latest', false]);
            if (is_array($latestBlock) && isset($latestBlock['baseFeePerGas']) && is_string($latestBlock['baseFeePerGas'])) {
                $baseFeeWei = (int) hexdec($latestBlock['baseFeePerGas']);
            }
        } catch (Throwable) {
        }

        $maxFeeWei = ($baseFeeWei * 2) + $priorityWei;

        return ['0x' . dechex($priorityWei), '0x' . dechex($maxFeeWei)];
    }

    private function estimateGas(string $fromAddress, string $toAddress, string $calldataHex): int
    {
        try {
            $gasHex = $this->rpcCall('eth_estimateGas', [[
                'from' => $fromAddress,
                'to' => $toAddress,
                'value' => '0x0',
                'data' => $calldataHex,
            ]]);
            if (is_string($gasHex)) {
                return (int) ceil(((int) hexdec($gasHex)) * 1.15);
            }
        } catch (Throwable) {
        }

        return 35000;
    }

    /**
     * @return array{0: int, 1: int} [blockNumber, gasUsed]
     */
    private function awaitReceipt(string $txHash, int $fallbackGas): array
    {
        for ($i = 0; $i < 6; $i++) {
            try {
                $receipt = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
                if (is_array($receipt) && isset($receipt['blockNumber'])) {
                    $status = isset($receipt['status']) ? (int) hexdec((string) $receipt['status']) : 1;
                    if ($status === 0) {
                        throw new RuntimeException('Transacció revertida a Polygon: ' . $txHash);
                    }

                    return [
                        (int) hexdec((string) $receipt['blockNumber']),
                        isset($receipt['gasUsed']) ? (int) hexdec((string) $receipt['gasUsed']) : $fallbackGas,
                    ];
                }
            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable) {
            }

            sleep(2);
        }

        $blockHex = $this->rpcCall('eth_blockNumber');

        return [(int) hexdec((string) $blockHex), $fallbackGas];
    }

    /**
     * @param array<int, mixed> $params
     */
    private function rpcCall(string $method, array $params = []): mixed
    {
        $response = $this->httpClient->request('POST', $this->rpcUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ],
            'timeout' => 10,
        ]);

        $data = $response->toArray(false);
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];
            throw new RuntimeException(sprintf('Error JSON-RPC Polygon (%s): %s', $method, (string) $msg));
        }

        if (!array_key_exists('result', $data)) {
            throw new RuntimeException(sprintf('Resposta JSON-RPC invàlida per %s', $method));
        }

        return $data['result'];
    }
}
