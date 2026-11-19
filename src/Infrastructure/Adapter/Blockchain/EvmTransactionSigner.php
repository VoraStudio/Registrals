<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Blockchain;

use InvalidArgumentException;
use kornrunner\Keccak;
use phpseclib4\Crypt\EC\Curves\secp256k1;
use phpseclib4\Math\BigInteger;
use Web3p\RLP\RLP;

/**
 * Signador criptogràfic EVM (secp256k1 + Keccak-256 + RLP) en PHP pur per a transaccions EIP-1559 (Tipus 2).
 */
final class EvmTransactionSigner
{
    private secp256k1 $curve;
    private RLP $rlp;

    public function __construct()
    {
        $this->curve = new secp256k1();
        $this->rlp = new RLP();
    }

    /**
     * Genera una nova Hot Wallet EVM aleatòria (adreça pública i clau privada en hexadecimal).
     *
     * @return array{address: string, privateKey: string}
     */
    public function generateWallet(): array
    {
        $n = $this->curve->getOrder();

        do {
            $privHex = bin2hex(random_bytes(32));
            $d = new BigInteger($privHex, 16);
        } while ($d->equals(new BigInteger(0)) || $d->compare($n) >= 0);

        return [
            'address' => $this->deriveAddress($privHex),
            'privateKey' => '0x' . $privHex,
        ];
    }

    /**
     * Deriva l'adreça EVM (0x + 40 caràcters hex) a partir d'una clau privada secp256k1.
     */
    public function deriveAddress(string $privateKeyHex): string
    {
        $privClean = $this->normalizePrivateKey($privateKeyHex);
        $d = new BigInteger($privClean, 16);
        $q = $this->curve->multiplyPoint($this->curve->getBasePoint(), $d);

        $xHex = str_pad($q[0]->toBigInteger()->toHex(), 64, '0', STR_PAD_LEFT);
        $yHex = str_pad($q[1]->toBigInteger()->toHex(), 64, '0', STR_PAD_LEFT);

        return '0x' . substr(Keccak::hash(hex2bin($xHex . $yHex), 256), 24);
    }

    /**
     * Construeix i signa una transacció EIP-1559 (Tipus 2) retornant el payload raw llest per eth_sendRawTransaction.
     */
    public function signEip1559Transaction(
        string $privateKeyHex,
        int $chainId,
        int $nonce,
        string $maxPriorityFeePerGasWeiHex,
        string $maxFeePerGasWeiHex,
        int $gasLimit,
        string $toAddress,
        string $valueWeiHex = '0x0',
        string $dataHex = '0x'
    ): string {
        $privClean = $this->normalizePrivateKey($privateKeyHex);

        $fields = [
            $this->intToRlpHex($chainId),
            $this->intToRlpHex($nonce),
            $this->hexToRlpQuantity($maxPriorityFeePerGasWeiHex),
            $this->hexToRlpQuantity($maxFeePerGasWeiHex),
            $this->intToRlpHex($gasLimit),
            $this->normalizeAddress($toAddress),
            $this->hexToRlpQuantity($valueWeiHex),
            $this->normalizeData($dataHex),
            [], // accessList buida
        ];

        $unsignedRlp = $this->rlp->encode($fields);
        $signingPayload = hex2bin('02' . $unsignedRlp);
        $txDigestHex = Keccak::hash($signingPayload, 256);

        [$yParity, $rHex, $sHex] = $this->signDigest($privClean, $txDigestHex);

        $signedFields = array_merge($fields, [
            $this->intToRlpHex($yParity),
            $this->hexToRlpQuantity('0x' . $rHex),
            $this->hexToRlpQuantity('0x' . $sHex),
        ]);

        return '0x02' . $this->rlp->encode($signedFields);
    }

    /**
     * Signa un digest de 32 bytes amb secp256k1, aplicant EIP-2 (low-s) i calculant yParity (0 o 1).
     *
     * @return array{0: int, 1: string, 2: string} [yParity, rHex, sHex]
     */
    private function signDigest(string $privClean, string $digestHex): array
    {
        $n = $this->curve->getOrder();
        $halfN = $n->divide(new BigInteger(2))[0];
        $zero = new BigInteger(0);
        $d = new BigInteger($privClean, 16);
        $z = new BigInteger($digestHex, 16);
        $g = $this->curve->getBasePoint();

        while (true) {
            $k = new BigInteger(bin2hex(random_bytes(32)), 16);
            [, $k] = $k->divide($n);
            if ($k->equals($zero)) {
                continue;
            }

            $rPoint = $this->curve->multiplyPoint($g, $k);
            $rx = $rPoint[0]->toBigInteger();
            [, $r] = $rx->divide($n);
            if ($r->equals($zero) || $rx->compare($n) >= 0) {
                continue;
            }

            $kInv = $k->modInverse($n);
            [, $s] = $kInv->multiply($z->add($r->multiply($d)))->divide($n);
            if ($s->equals($zero)) {
                continue;
            }

            $yParity = $rPoint[1]->toBigInteger()->isOdd() ? 1 : 0;
            if ($s->compare($halfN) > 0) {
                $s = $n->subtract($s);
                $yParity ^= 1;
            }

            return [$yParity, $r->toHex(), $s->toHex()];
        }
    }

    private function normalizePrivateKey(string $privateKeyHex): string
    {
        $clean = strtolower(trim($privateKeyHex));
        if (str_starts_with($clean, '0x')) {
            $clean = substr($clean, 2);
        }
        if (strlen($clean) !== 64 || !ctype_xdigit($clean)) {
            throw new InvalidArgumentException('Clau privada EVM invàlida: calen 64 caràcters hexadecimals.');
        }

        return $clean;
    }

    private function normalizeAddress(string $address): string
    {
        $clean = strtolower(trim($address));
        if (!str_starts_with($clean, '0x')) {
            $clean = '0x' . $clean;
        }
        if (strlen($clean) !== 42 || !ctype_xdigit(substr($clean, 2))) {
            throw new InvalidArgumentException('Adreça EVM invàlida: ' . $address);
        }

        return $clean;
    }

    private function normalizeData(string $dataHex): string
    {
        $clean = strtolower(trim($dataHex));
        if ($clean === '' || $clean === '0x') {
            return '';
        }
        if (!str_starts_with($clean, '0x')) {
            $clean = '0x' . $clean;
        }

        return $clean;
    }

    private function intToRlpHex(int $value): string
    {
        if ($value === 0) {
            return '';
        }
        $hex = dechex($value);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return '0x' . $hex;
    }

    private function hexToRlpQuantity(string $hexValue): string
    {
        $clean = strtolower(trim($hexValue));
        if (str_starts_with($clean, '0x')) {
            $clean = substr($clean, 2);
        }
        $clean = ltrim($clean, '0');
        if ($clean === '') {
            return '';
        }
        if (strlen($clean) % 2 !== 0) {
            $clean = '0' . $clean;
        }

        return '0x' . $clean;
    }
}

