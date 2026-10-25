<?php

declare(strict_types=1);

namespace App\Service\Validation;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validador d'integritat forense i anti-frau documental per a fotos de béns i factures.
 * Executa filtres d'alta velocitat en mil·lisegons abans de qualsevol crida a la IA.
 */
class MediaIntegrityValidator
{
    /**
     * Firmes i indicadors coneguts de generadors d'imatges d'intel·ligència artificial (C2PA i plataformes de difusió).
     * No utilitzar cadenes genèriques que provoquin falsos positius en càmeres Android (com 'Google Media Processing' estàndard).
     */
    private const AI_GENERATOR_SIGNATURES = [
        'Google Media Processing Services (Generative)' => 'Google Imagen / Generative AI',
        'trainedAlgorithmicMedia' => 'Manifest IPTC de contingut sintètic algorítmic',
        'compositeWithTrainedAlgorithmicMedia' => 'Manifest IPTC de contingut sintètic compost',
        'SynthID' => 'Segell digital Google SynthID per a IA Generativa',
        'DALL·E' => 'OpenAI DALL·E',
        'DALL-E' => 'OpenAI DALL·E',
        'Midjourney' => 'Midjourney AI',
        'Stable Diffusion' => 'Stable Diffusion',
        'Automatic1111' => 'Stable Diffusion WebUI',
        'ComfyUI' => 'ComfyUI Generative Workflow',
        'InvokeAI' => 'InvokeAI Generative Studio',
        'Adobe Firefly' => 'Adobe Firefly Generative AI',
        'c2pa:action="c2pa.created"' => 'Manifest C2PA d\'imatge creada sintèticament',
        'c2pa.action": "c2pa.created"' => 'Manifest C2PA d\'imatge creada sintèticament',
    ];

    /**
     * Valida una fotografia física del bé.
     * Rebutja contingut generat per IA i extreu metadades de càmera EXIF.
     */
    public function validateItemPhoto(UploadedFile|string $file, ?string $filename = null): MediaValidationResult
    {
        $content = $file instanceof UploadedFile ? (file_get_contents($file->getRealPath()) ?: '') : $file;
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : ($filename ?? 'imatge');

        if (trim($content) === '') {
            return MediaValidationResult::rejected('El fitxer de fotografia és buit o inaccessible.');
        }

        // 1. Guàrdia C2PA i Signatures d'IA (Execució < 1 ms)
        $aiCheck = $this->detectAiSignatures($content);
        if ($aiCheck !== null) {
            return MediaValidationResult::rejectedAi(
                sprintf('Aquesta imatge conté registres C2PA o metadades generades per intel·ligència artificial (%s). Per a la custòdia notarial, cal aportar una fotografia real de l\'objecte.', $aiCheck),
                $aiCheck
            );
        }

        // 2. Extracció de metadades EXIF i resolució
        $exif = $this->extractExifData($file);
        $dimensions = $this->extractDimensions($content);

        $warnings = [];

        // 3. Comprovació de resolució pura de difusió (ex: 1024x1024 sense EXIF)
        if ($dimensions !== null) {
            $w = $dimensions['width'];
            $h = $dimensions['height'];

            // Les imatges generades per IA acostumen a ser quadrades pures (1024x1024, 512x512)
            if ($w === $h && in_array($w, [512, 768, 1024, 1536, 2048], true) && empty($exif['make'])) {
                $warnings[] = 'La fotografia té una resolució quadrada típica de model de difusió i no conté paràmetres òptics de càmera.';
            }
        }

        return MediaValidationResult::valid([
            'exif' => $exif,
            'dimensions' => $dimensions,
            'filename' => $name,
            'verified_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $warnings);
    }

    /**
     * Valida una prova documental (factura, tiquet, rebut).
     * No exigeix EXIF de càmera (permet PDFs i escanejos), però bloqueja signatures d'IA.
     */
    public function validateProofDocument(UploadedFile|string $file, ?string $filename = null): MediaValidationResult
    {
        $content = $file instanceof UploadedFile ? (file_get_contents($file->getRealPath()) ?: '') : $file;
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : ($filename ?? 'document');

        if (trim($content) === '') {
            return MediaValidationResult::rejected('El document adjuntat és buit o inaccessible.');
        }

        // 1. Guàrdia C2PA i Signatures d'IA a la factura/tiquet
        $aiCheck = $this->detectAiSignatures($content);
        if ($aiCheck !== null) {
            return MediaValidationResult::rejectedAi(
                sprintf('Aquest document o tiquet conté marques de generació sintètica per IA (%s). Només s\'admeten comprovants de compra originals i legítims.', $aiCheck),
                $aiCheck
            );
        }

        return MediaValidationResult::valid([
            'filename' => $name,
            'verified_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Detecta presència de manifests C2PA o signatures conegudes de models de difusió.
     */
    public function detectAiSignatures(string $binaryContent): ?string
    {
        // Llegim els primers 2 MB i els darrers 256 KB (on solen anar les metadades XMP/JUMBF/C2PA)
        $len = strlen($binaryContent);
        $header = substr($binaryContent, 0, min($len, 2097152));
        $footer = $len > 262144 ? substr($binaryContent, -262144) : '';

        $haystack = $header . $footer;

        foreach (self::AI_GENERATOR_SIGNATURES as $needle => $label) {
            if (stripos($haystack, $needle) !== false) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Valida la congruència matemàtica d'un tiquet o factura (Subtotal + IVA = Total).
     *
     * @return array{is_valid: bool, diff_cents: int, explanation: string}
     */
    public function validateInvoiceArithmetic(?float $subtotal, ?float $tax, ?float $total): array
    {
        if ($subtotal === null || $total === null) {
            return [
                'is_valid' => true,
                'diff_cents' => 0,
                'explanation' => 'No es disposa de desglossament complet d\'imports per contrastar.',
            ];
        }

        $calcTotal = $subtotal + ($tax ?? 0.0);
        $diff = abs($calcTotal - $total);
        $diffCents = (int) round($diff * 100);

        // Marge de tolerància de 3 cèntims per arrodoniments d'IVA comercial
        $isValid = $diffCents <= 3;

        return [
            'is_valid' => $isValid,
            'diff_cents' => $diffCents,
            'explanation' => $isValid
                ? 'Els imports del tiquet quadren matemàticament.'
                : sprintf('Discrepància aritmètica: Subtotal (%.2f €) + Impostos (%.2f €) no sumen el Total (%.2f €). Diferència: %.2f €.', $subtotal, $tax ?? 0.0, $total, $diff),
        ];
    }

    /**
     * Valida el dígit de control d'un NIF / NIE / CIF espanyol.
     */
    public function validateSpanishTaxId(string $taxId): bool
    {
        $code = strtoupper(trim(str_replace(['-', ' ', '.'], '', $taxId)));
        if (strlen($code) !== 9) {
            return false;
        }

        // 1. DNI estàndard (8 números + 1 lletra)
        if (preg_match('/^[0-9]{8}[A-Z]$/', $code)) {
            $num = (int) substr($code, 0, 8);
            $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
            return $code[8] === $letters[$num % 23];
        }

        // 2. NIE estrangers (X, Y, Z + 7 números + 1 lletra)
        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $code)) {
            $first = str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], $code[0]);
            $num = (int) ($first . substr($code, 1, 7));
            $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
            return $code[8] === $letters[$num % 23];
        }

        // 3. CIF persones jurídiques (1 lletra + 7 números + 1 dígit/lletra de control)
        if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $code)) {
            $sumEven = (int) $code[2] + (int) $code[4] + (int) $code[6];
            $sumOdd = 0;
            for ($i = 1; $i <= 7; $i += 2) {
                $double = (int) $code[$i] * 2;
                $sumOdd += $double > 9 ? ($double - 9) : $double;
            }
            $totalSum = $sumEven + $sumOdd;
            $controlDigit = (10 - ($totalSum % 10)) % 10;
            $controlLetters = 'JABCDEFGHI';

            $firstLetter = $code[0];
            $control = $code[8];

            if (in_array($firstLetter, ['P', 'Q', 'S', 'K', 'W'], true)) {
                return $control === $controlLetters[$controlDigit];
            }

            if (in_array($firstLetter, ['A', 'B', 'E', 'H'], true)) {
                return $control === (string) $controlDigit;
            }

            return $control === (string) $controlDigit || $control === $controlLetters[$controlDigit];
        }

        return false;
    }

    /**
     * Extreu dades EXIF segures sense trencar si el fitxer no en conté.
     *
     * @return array<string, mixed>
     */
    private function extractExifData(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : null;

        if (!$path || !file_exists($path) || !function_exists('exif_read_data')) {
            return [];
        }

        $mime = mime_content_type($path);
        if ($mime !== 'image/jpeg' && $mime !== 'image/tiff') {
            return [];
        }

        $exif = @exif_read_data($path, 'ANY_TAG', true, false);
        if ($exif === false || !is_array($exif)) {
            return [];
        }

        $ifd0 = $exif['IFD0'] ?? [];
        $sub = $exif['EXIF'] ?? [];

        return array_filter([
            'make' => $ifd0['Make'] ?? null,
            'model' => $ifd0['Model'] ?? null,
            'exposure_time' => $sub['ExposureTime'] ?? null,
            'f_number' => $sub['FNumber'] ?? null,
            'iso' => $sub['ISOSpeedRatings'] ?? null,
            'datetime_original' => $sub['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? null,
            'software' => $ifd0['Software'] ?? null,
        ]);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function extractDimensions(string $content): ?array
    {
        if (!function_exists('getimagesizefromstring')) {
            return null;
        }

        $size = @getimagesizefromstring($content);
        if ($size === false || !isset($size[0], $size[1])) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }
}
