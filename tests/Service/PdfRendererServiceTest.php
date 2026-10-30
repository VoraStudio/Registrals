<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Certificate\PdfRendererService;
use PHPUnit\Framework\TestCase;

class PdfRendererServiceTest extends TestCase
{
    public function testRenderHtmlToPdfReturnsValidPdfHeader(): void
    {
        $renderer = new PdfRendererService();

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Test</title></head><body><h1>Certificat Registrals</h1><p>Test de renderitzat vectorial.</p></body></html>';

        try {
            $pdf = $renderer->renderHtmlToPdf($html);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Chromium / Edge no està disponible en aquest entorn: ' . $e->getMessage());
        }

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
    }
}
