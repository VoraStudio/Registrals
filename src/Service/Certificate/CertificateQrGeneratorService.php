<?php

declare(strict_types=1);

namespace App\Service\Certificate;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Servei generador de codis QR en format Data URI per a encastar directament als certificats PDF i pantalles.
 */
class CertificateQrGeneratorService
{
    private PngWriter $writer;

    public function __construct()
    {
        $this->writer = new PngWriter();
    }

    /**
     * Genera el codi QR com a cadena Data URI base64 ("data:image/png;base64,...")
     *
     * @param string $url URL de resolució pública (ex: https://registrals.cat/verify/{uuid})
     * @param int $size Mida en píxels (per defecte 220)
     */
    public function generateDataUri(string $url, int $size = 220): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 6,
            foregroundColor: new Color(29, 47, 75), // Color Ink corporatiu (#1D2F4B)
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $this->writer->write($qrCode);

        return $result->getDataUri();
    }
}
