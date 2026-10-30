<?php

declare(strict_types=1);

namespace App\Service\Certificate;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Motor de renderitzat PDF vectorial basat en Chromium headless (Edge / Chrome / Chromium).
 * Produeix PDFs d'alta precisió tipogràfica (Marcellus / Raleway) i maquetació notarial.
 */
class PdfRendererService
{
    /**
     * @var list<string>
     */
    private const CANDIDATE_PATHS = [
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
    ];

    private ?string $resolvedBinary = null;

    public function __construct(
        private readonly ?string $configuredBinary = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Renderitza una cadena HTML completa com a document PDF vectorial binari.
     *
     * @param string $html Contingut HTML amb estils encastats
     * @return string Contingut binari del document PDF
     */
    public function renderHtmlToPdf(string $html): string
    {
        $binary = $this->resolveBinary();

        $tempHtml = tempnam(sys_get_temp_dir(), 'reg_cert_') . '.html';
        $tempPdf = tempnam(sys_get_temp_dir(), 'reg_cert_') . '.pdf';

        try {
            file_put_contents($tempHtml, $html);

            $process = new Process([
                $binary,
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--no-pdf-header-footer',
                '--run-all-compositor-stages-before-draw',
                '--print-to-pdf=' . $tempPdf,
                $tempHtml,
            ]);

            $process->setTimeout(30.0);
            $process->run();

            if (!$process->isSuccessful() || !file_exists($tempPdf) || filesize($tempPdf) === 0) {
                $errorMsg = sprintf(
                    'Error en renderitzar el PDF amb Chromium [%s]: %s',
                    $binary,
                    $process->getErrorOutput()
                );
                $this->logger?->error($errorMsg);

                throw new RuntimeException($errorMsg);
            }

            $pdfContent = (string) file_get_contents($tempPdf);

            $this->logger?->info('PDF de certificat generat amb èxit', [
                'bytes' => strlen($pdfContent),
                'engine' => $binary,
            ]);

            return $pdfContent;
        } finally {
            if (file_exists($tempHtml)) {
                @unlink($tempHtml);
            }
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }
        }
    }

    /**
     * Resol la ruta al binari de Chromium/Edge al sistema operatiu.
     */
    public function resolveBinary(): string
    {
        if ($this->resolvedBinary !== null) {
            return $this->resolvedBinary;
        }

        if ($this->configuredBinary && file_exists($this->configuredBinary)) {
            return $this->resolvedBinary = $this->configuredBinary;
        }

        foreach (self::CANDIDATE_PATHS as $path) {
            if (file_exists($path)) {
                return $this->resolvedBinary = $path;
            }
        }

        throw new RuntimeException(
            'No s\'ha trobat cap binari de Chromium/Edge per al renderitzat PDF. ' .
            'Instal·la Chromium o defineix CHROMIUM_BINARY_PATH a les variables d\'entorn.'
        );
    }
}
