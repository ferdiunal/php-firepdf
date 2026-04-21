<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class PagesExtractionResult
{
    /**
     * @param  list<PageMarkdown>  $pages  Per-page markdown results.
     * @param  list<int>  $pagesWithTables  1-indexed pages where tables were detected.
     * @param  list<int>  $pagesWithColumns  1-indexed pages where columns were detected.
     * @param  list<int>  $pagesNeedingOcr  1-indexed pages that need OCR.
     * @param  bool  $isComplex  True if any page has tables or columns.
     */
    public function __construct(
        public array $pages,
        public array $pagesWithTables,
        public array $pagesWithColumns,
        public array $pagesNeedingOcr,
        public bool $isComplex,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $pagePayloads */
        $pagePayloads = ArrayValue::assocList($data, 'pages');

        /** @var list<PageMarkdown> $pages */
        $pages = array_map(
            static fn (array $pageData): PageMarkdown => PageMarkdown::fromArray($pageData),
            $pagePayloads
        );

        return new self(
            pages: $pages,
            pagesWithTables: ArrayValue::intList($data, 'pages_with_tables'),
            pagesWithColumns: ArrayValue::intList($data, 'pages_with_columns'),
            pagesNeedingOcr: ArrayValue::intList($data, 'pages_needing_ocr'),
            isComplex: ArrayValue::bool($data, 'is_complex'),
        );
    }
}
