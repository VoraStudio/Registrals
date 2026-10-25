<?php

declare(strict_types=1);

namespace App\Service\Item;

use App\Entity\User;
use App\Service\Validation\MediaIntegrityValidator;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Servei del Pont QR per a PC (Mobile Handoff).
 * Gestiona sessions temporals (10 minuts) per sincronitzar captures fotogràfiques
 * des de la càmera del telèfon mòbil directament cap al formulari d'alta d'escriptori.
 */
class MobileHandoffService
{
    private string $storageDir;

    public function __construct(
        KernelInterface $kernel,
        private readonly MediaIntegrityValidator $integrityValidator,
    ) {
        $this->storageDir = $kernel->getProjectDir() . '/var/handoff';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Crea una sessió de captura mòbil temporal i genera el codi QR notarial.
     *
     * @param string $target 'photo' per a la fotografia principal o 'invoice' per al comprovant fiscal
     * @return array{token: string, url: string, qrDataUri: string, expiresAt: int, target: string}
     */
    public function createSession(User $user, string $baseUrl, string $target = 'photo'): array
    {
        $this->purgeExpiredSessions();

        $token = bin2hex(random_bytes(16));
        $now = time();
        $expiresAt = $now + 600; // 10 minuts de vigència

        $mobileUrl = rtrim($baseUrl, '/') . '/handoff/' . $token;

        // Generació del codi QR corporatiu amb colors Notarials (Tinta Ink #1D2F4B sobre fons net)
        $qrCode = new QrCode(
            data: $mobileUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(29, 47, 75), // #1D2F4B Ink
            backgroundColor: new Color(253, 253, 253), // #FDFDFD Whitef
        );

        $writer = new SvgWriter();
        $qrResult = $writer->write($qrCode);
        $qrDataUri = $qrResult->getDataUri();

        $sessionData = [
            'token' => $token,
            'userId' => $user->getId(),
            'target' => in_array($target, ['photo', 'invoice'], true) ? $target : 'photo',
            'createdAt' => $now,
            'expiresAt' => $expiresAt,
            'status' => 'pending',
            'photoFilename' => null,
            'photoMimeType' => null,
            'photoOriginalName' => null,
        ];

        file_put_contents($this->getSessionFilePath($token), json_encode($sessionData, JSON_THROW_ON_ERROR));

        return [
            'token' => $token,
            'url' => $mobileUrl,
            'qrDataUri' => $qrDataUri,
            'expiresAt' => $expiresAt,
            'target' => $sessionData['target'],
        ];
    }

    /**
     * Retorna les dades d'una sessió vigent o null si ha expirat o no existeix.
     */
    public function getSession(string $token): ?array
    {
        $path = $this->getSessionFilePath($token);
        if (!file_exists($path)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['expiresAt'] ?? 0) < time()) {
                $this->cleanupSession($token);
                return null;
            }
            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Rep i valida la fotografia pujada des del telèfon mòbil.
     * Executa el filtre anti-frau C2PA segons si és foto d'objecte o comprovant fiscal.
     *
     * @return array{success: bool, error?: string, code?: string}
     */
    public function saveUploadedPhoto(string $token, UploadedFile $file): array
    {
        $session = $this->getSession($token);
        if ($session === null) {
            return [
                'success' => false,
                'error' => 'Aquesta sessió de captura ha expirat o ja no és vàlida.',
                'code' => 'SESSION_EXPIRED',
            ];
        }

        $target = $session['target'] ?? 'photo';

        // Guàrdia d'integritat segons el tipus de document
        $integrity = $target === 'invoice'
            ? $this->integrityValidator->validateProofDocument($file)
            : $this->integrityValidator->validateItemPhoto($file);

        if (!$integrity->isValid) {
            return [
                'success' => false,
                'error' => $integrity->rejectionReason ?? 'El fitxer ha estat rebutjat per seguretat.',
                'code' => $integrity->isAiSynthetic ? 'AI_SYNTHETIC_REJECTED' : 'INTEGRITY_FAILED',
            ];
        }

        $ext = $file->guessExtension() ?? $file->getClientOriginalExtension() ?? 'jpg';
        $photoFilename = sprintf('%s_%s.%s', $token, $target, $ext);
        $targetPath = $this->storageDir . '/' . $photoFilename;

        $file->move($this->storageDir, $photoFilename);

        $session['status'] = 'completed';
        $session['photoFilename'] = $photoFilename;
        $session['photoMimeType'] = mime_content_type($targetPath) ?: 'image/jpeg';
        $session['photoOriginalName'] = $file->getClientOriginalName();

        file_put_contents($this->getSessionFilePath($token), json_encode($session, JSON_THROW_ON_ERROR));

        return ['success' => true];
    }

    /**
     * Retorna la ruta física de la fotografia temporal capturada.
     */
    public function getPhotoPath(string $token): ?string
    {
        $session = $this->getSession($token);
        if (!$session || empty($session['photoFilename'])) {
            return null;
        }

        $path = $this->storageDir . '/' . $session['photoFilename'];
        return file_exists($path) ? $path : null;
    }

    /**
     * Neteja els fitxers d'una sessió completada o cancel·lada.
     */
    public function cleanupSession(string $token): void
    {
        $sessionPath = $this->getSessionFilePath($token);
        if (file_exists($sessionPath)) {
            try {
                $data = json_decode((string) file_get_contents($sessionPath), true, 512, JSON_THROW_ON_ERROR);
                if (!empty($data['photoFilename'])) {
                    $photoPath = $this->storageDir . '/' . $data['photoFilename'];
                    if (file_exists($photoPath)) {
                        @unlink($photoPath);
                    }
                }
            } catch (\Throwable) {
            }
            @unlink($sessionPath);
        }
    }

    private function getSessionFilePath(string $token): string
    {
        return $this->storageDir . '/' . preg_replace('/[^a-zA-Z0-9]/', '', $token) . '.json';
    }

    /**
     * Purga sessions caducades de més de 15 minuts.
     */
    private function purgeExpiredSessions(): void
    {
        $files = glob($this->storageDir . '/*.json') ?: [];
        $now = time();
        foreach ($files as $file) {
            try {
                $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data) && ($data['expiresAt'] ?? 0) < ($now - 300)) {
                    $token = $data['token'] ?? null;
                    if ($token) {
                        $this->cleanupSession($token);
                    } else {
                        @unlink($file);
                    }
                }
            } catch (\Throwable) {
                @unlink($file);
            }
        }
    }
}
