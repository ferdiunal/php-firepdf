<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf;

use Ferdiunal\FirePdf\DTOs\PageMarkdown;
use Ferdiunal\FirePdf\DTOs\PageRegionTexts;
use Ferdiunal\FirePdf\DTOs\PagesExtractionResult;
use Ferdiunal\FirePdf\DTOs\PdfClassification;
use Ferdiunal\FirePdf\DTOs\PdfResult;
use Ferdiunal\FirePdf\DTOs\TextItem;
use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FFI\Inspector;
use Ferdiunal\FirePdf\Support\ArrayValue;

class FirePdf
{
    private Inspector $inspector;

    public function __construct(?string $libPath = null)
    {
        $this->inspector = new Inspector($libPath);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function requireDataAssoc(array $decoded, string $operation): array
    {
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new ProcessingException('InvalidResponse', "Expected object payload for {$operation}");
        }

        return ArrayValue::assocFromValue($data);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function requireDataAssocList(array $decoded, string $operation): array
    {
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new ProcessingException('InvalidResponse', "Expected list payload for {$operation}");
        }

        $result = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $result[] = ArrayValue::assocFromValue($item);
        }

        return $result;
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function processPdf(string $path, ?array $pages = null): PdfResult
    {
        $decoded = $this->inspector->processPdf($path, $pages);

        return PdfResult::fromArray($this->requireDataAssoc($decoded, 'processPdf'));
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function processPdfBytes(string $bytes, ?array $pages = null): PdfResult
    {
        $decoded = $this->inspector->processPdfBytes($bytes, $pages);

        return PdfResult::fromArray($this->requireDataAssoc($decoded, 'processPdfBytes'));
    }

    public function detectPdf(string $path): PdfResult
    {
        $decoded = $this->inspector->detectPdf($path);

        return PdfResult::fromArray($this->requireDataAssoc($decoded, 'detectPdf'));
    }

    public function detectPdfBytes(string $bytes): PdfResult
    {
        $decoded = $this->inspector->detectPdfBytes($bytes);

        return PdfResult::fromArray($this->requireDataAssoc($decoded, 'detectPdfBytes'));
    }

    public function classifyPdf(string $path): PdfClassification
    {
        $decoded = $this->inspector->classifyPdf($path);

        return PdfClassification::fromArray($this->requireDataAssoc($decoded, 'classifyPdf'));
    }

    public function classifyPdfBytes(string $bytes): PdfClassification
    {
        $decoded = $this->inspector->classifyPdfBytes($bytes);

        return PdfClassification::fromArray($this->requireDataAssoc($decoded, 'classifyPdfBytes'));
    }

    public function extractText(string $path): string
    {
        return $this->inspector->extractText($path);
    }

    public function extractTextBytes(string $bytes): string
    {
        return $this->inspector->extractTextBytes($bytes);
    }

    /**
     * @param  list<int>|null  $pages
     * @return list<TextItem>
     */
    public function extractTextWithPositions(string $path, ?array $pages = null): array
    {
        $decoded = $this->inspector->extractTextWithPositions($path, $pages);
        $items = $this->requireDataAssocList($decoded, 'extractTextWithPositions');

        return array_map(
            static fn (array $item): TextItem => TextItem::fromArray($item),
            $items
        );
    }

    /**
     * @param  list<int>|null  $pages
     * @return list<TextItem>
     */
    public function extractTextWithPositionsBytes(string $bytes, ?array $pages = null): array
    {
        $decoded = $this->inspector->extractTextWithPositionsBytes($bytes, $pages);
        $items = $this->requireDataAssocList($decoded, 'extractTextWithPositionsBytes');

        return array_map(
            static fn (array $item): TextItem => TextItem::fromArray($item),
            $items
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTextInRegions(string $path, array $pageRegions): array
    {
        $decoded = $this->inspector->extractTextInRegions($path, $pageRegions);
        $pages = $this->requireDataAssocList($decoded, 'extractTextInRegions');

        return array_map(
            static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
            $pages
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTextInRegionsBytes(string $bytes, array $pageRegions): array
    {
        $decoded = $this->inspector->extractTextInRegionsBytes($bytes, $pageRegions);
        $pages = $this->requireDataAssocList($decoded, 'extractTextInRegionsBytes');

        return array_map(
            static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
            $pages
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTablesInRegions(string $path, array $pageRegions): array
    {
        $decoded = $this->inspector->extractTablesInRegions($path, $pageRegions);
        $pages = $this->requireDataAssocList($decoded, 'extractTablesInRegions');

        return array_map(
            static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
            $pages
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTablesInRegionsBytes(string $bytes, array $pageRegions): array
    {
        $decoded = $this->inspector->extractTablesInRegionsBytes($bytes, $pageRegions);
        $pages = $this->requireDataAssocList($decoded, 'extractTablesInRegionsBytes');

        return array_map(
            static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
            $pages
        );
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function extractPagesMarkdown(string $path, ?array $pages = null): PagesExtractionResult
    {
        $decoded = $this->inspector->extractPagesMarkdown($path, $pages);

        return PagesExtractionResult::fromArray($this->requireDataAssoc($decoded, 'extractPagesMarkdown'));
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function extractPagesMarkdownBytes(string $bytes, ?array $pages = null): PagesExtractionResult
    {
        $decoded = $this->inspector->extractPagesMarkdownBytes($bytes, $pages);

        return PagesExtractionResult::fromArray($this->requireDataAssoc($decoded, 'extractPagesMarkdownBytes'));
    }
}
