<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class PdfResult
{
    /**
     * @param  string  $pdfType  'TextBased', 'Scanned', 'ImageBased', or 'Mixed'.
     * @param  ?string  $markdown  Markdown output or null.
     * @param  int  $pageCount  Total page count.
     * @param  int  $processingTimeMs  Processing time in milliseconds.
     * @param  list<int>  $pagesNeedingOcr  1-indexed page numbers that need OCR.
     * @param  ?string  $title  Title from PDF metadata.
     * @param  float  $confidence  Detection confidence score (0.0–1.0).
     * @param  bool  $isComplexLayout  True if any page has tables or columns.
     * @param  list<int>  $pagesWithTables  1-indexed pages where tables were detected.
     * @param  list<int>  $pagesWithColumns  1-indexed pages where columns were detected.
     * @param  bool  $hasEncodingIssues  True when broken font encodings are detected.
     */
    public function __construct(
        public string $pdfType,
        public ?string $markdown,
        public int $pageCount,
        public int $processingTimeMs,
        public array $pagesNeedingOcr,
        public ?string $title,
        public float $confidence,
        public bool $isComplexLayout,
        public array $pagesWithTables,
        public array $pagesWithColumns,
        public bool $hasEncodingIssues,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $layout = ArrayValue::assoc($data, 'layout');

        $pagesWithTables = array_key_exists('pages_with_tables', $data)
            ? ArrayValue::intList($data, 'pages_with_tables')
            : ArrayValue::intList($layout, 'pages_with_tables');

        $pagesWithColumns = array_key_exists('pages_with_columns', $data)
            ? ArrayValue::intList($data, 'pages_with_columns')
            : ArrayValue::intList($layout, 'pages_with_columns');

        $isComplexLayout = array_key_exists('is_complex_layout', $data)
            ? ArrayValue::bool($data, 'is_complex_layout')
            : ArrayValue::bool($layout, 'is_complex');

        return new self(
            pdfType: ArrayValue::string($data, 'pdf_type'),
            markdown: ArrayValue::nullableString($data, 'markdown'),
            pageCount: ArrayValue::int($data, 'page_count'),
            processingTimeMs: ArrayValue::int($data, 'processing_time_ms'),
            pagesNeedingOcr: ArrayValue::intList($data, 'pages_needing_ocr'),
            title: ArrayValue::nullableString($data, 'title'),
            confidence: ArrayValue::float($data, 'confidence'),
            isComplexLayout: $isComplexLayout,
            pagesWithTables: $pagesWithTables,
            pagesWithColumns: $pagesWithColumns,
            hasEncodingIssues: ArrayValue::bool($data, 'has_encoding_issues'),
        );
    }
}
