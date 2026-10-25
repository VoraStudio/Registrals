<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object que encapsula les dades estructurades deduïdes per l'assistent d'IA SGLang (Qwen VL).
 */
final readonly class AiVisionExtractionDto
{
    /**
     * @param array<string, mixed>|null $rawResponse
     */
    public function __construct(
        public ?string $name = null,
        public ?string $categorySlug = null,
        public ?string $subcategorySlug = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $serialNumber = null,
        public ?float $declaredValueEuros = null,
        public ?string $acquisitionDate = null, // Format YYYY-MM-DD
        public ?string $sellerName = null,
        public ?string $taxId = null,           // NIF/CIF del comerç
        public ?float $subtotalEuros = null,    // Base imposable
        public ?float $taxAmountEuros = null,   // Import d'IVA
        public ?float $taxRatePercent = null,   // Percentatge d'IVA (ex: 21)
        public ?bool $isArithmeticValid = null,
        public ?string $mathExplanation = null,
        public ?bool $taxIdValid = null,
        public float $confidence = 0.0,         // 0.0 a 1.0
        public ?string $notes = null,
        public ?array $rawResponse = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?array $rawResponse = null): self
    {
        $declaredValue = null;
        if (isset($data['declared_value_euros']) && is_numeric($data['declared_value_euros'])) {
            $declaredValue = (float) $data['declared_value_euros'];
        } elseif (isset($data['declaredValueEuros']) && is_numeric($data['declaredValueEuros'])) {
            $declaredValue = (float) $data['declaredValueEuros'];
        }

        $subtotal = null;
        if (isset($data['subtotal_euros']) && is_numeric($data['subtotal_euros'])) {
            $subtotal = (float) $data['subtotal_euros'];
        }

        $taxAmount = null;
        if (isset($data['tax_amount_euros']) && is_numeric($data['tax_amount_euros'])) {
            $taxAmount = (float) $data['tax_amount_euros'];
        }

        $taxRate = null;
        if (isset($data['tax_rate_percent']) && is_numeric($data['tax_rate_percent'])) {
            $taxRate = (float) $data['tax_rate_percent'];
        }

        $confidence = 0.85;
        if (isset($data['confidence']) && is_numeric($data['confidence'])) {
            $confidence = (float) $data['confidence'];
        }

        return new self(
            name: $data['name'] ?? null,
            categorySlug: $data['category_slug'] ?? $data['categorySlug'] ?? null,
            subcategorySlug: $data['subcategory_slug'] ?? $data['subcategorySlug'] ?? null,
            brand: $data['brand'] ?? null,
            model: $data['model'] ?? null,
            serialNumber: $data['serial_number'] ?? $data['serialNumber'] ?? null,
            declaredValueEuros: $declaredValue,
            acquisitionDate: $data['acquisition_date'] ?? $data['acquisitionDate'] ?? null,
            sellerName: $data['seller_name'] ?? $data['sellerName'] ?? null,
            taxId: $data['tax_id'] ?? $data['taxId'] ?? null,
            subtotalEuros: $subtotal,
            taxAmountEuros: $taxAmount,
            taxRatePercent: $taxRate,
            isArithmeticValid: isset($data['is_arithmetic_valid']) ? (bool) $data['is_arithmetic_valid'] : null,
            mathExplanation: $data['math_explanation'] ?? null,
            taxIdValid: isset($data['tax_id_valid']) ? (bool) $data['tax_id_valid'] : null,
            confidence: $confidence,
            notes: $data['notes'] ?? null,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Retorna una matriu associativa apta per ser retornada en respostes JSON al frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'categorySlug' => $this->categorySlug,
            'subcategorySlug' => $this->subcategorySlug,
            'brand' => $this->brand,
            'model' => $this->model,
            'serialNumber' => $this->serialNumber,
            'declaredValueEuros' => $this->declaredValueEuros,
            'acquisitionDate' => $this->acquisitionDate,
            'sellerName' => $this->sellerName,
            'taxId' => $this->taxId,
            'subtotalEuros' => $this->subtotalEuros,
            'taxAmountEuros' => $this->taxAmountEuros,
            'taxRatePercent' => $this->taxRatePercent,
            'isArithmeticValid' => $this->isArithmeticValid,
            'mathExplanation' => $this->mathExplanation,
            'taxIdValid' => $this->taxIdValid,
            'confidence' => $this->confidence,
            'notes' => $this->notes,
        ];
    }
}
