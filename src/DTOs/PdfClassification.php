<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class PdfClassification
{
    /**
     * @param  string  $pdfType  'TextBased', 'Scanned', 'ImageBased', or 'Mixed'.
     * @param  int  $pageCount  Total page count.
     * @param  list<int>  $pagesNeedingOcr  0-indexed page numbers that need OCR.
     * @param  float  $confidence  Detection confidence score (0.0–1.0).
     */
    public function __construct(
        public string $pdfType,
        public int $pageCount,
        public array $pagesNeedingOcr,
        public float $confidence,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pdfType: ArrayValue::string($data, 'pdf_type'),
            pageCount: ArrayValue::int($data, 'page_count'),
            pagesNeedingOcr: ArrayValue::intList($data, 'pages_needing_ocr'),
            confidence: ArrayValue::float($data, 'confidence'),
        );
    }
}
