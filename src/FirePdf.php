<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf;

use Ferdiunal\FirePdf\DTOs\PageRegionTexts;
use Ferdiunal\FirePdf\DTOs\PagesExtractionResult;
use Ferdiunal\FirePdf\DTOs\PdfClassification;
use Ferdiunal\FirePdf\DTOs\PdfResult;
use Ferdiunal\FirePdf\DTOs\TextItem;
use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FFI\Inspector;
use Ferdiunal\FirePdf\Runtime\FirePdfRuntimeOptions;
use Ferdiunal\FirePdf\Runtime\FirePdfRuntimeSnapshot;
use Ferdiunal\FirePdf\Support\ArrayValue;

class FirePdf
{
    private Inspector $inspector;

    private FirePdfRuntimeOptions $runtimeOptions;

    private int $opCount = 0;

    private float $lastDurationMs = 0.0;

    private float $totalDurationMs = 0.0;

    private float $maxDurationMs = 0.0;

    private int $currentMemoryBytes = 0;

    private int $peakMemoryBytes = 0;

    private bool $recycleRecommended = false;

    private ?string $recycleReason = null;

    public function __construct(?string $libPath = null, ?FirePdfRuntimeOptions $runtimeOptions = null)
    {
        $this->inspector = new Inspector($libPath);
        $this->runtimeOptions = $runtimeOptions ?? new FirePdfRuntimeOptions();
        $this->currentMemoryBytes = memory_get_usage(true);
        $this->peakMemoryBytes = memory_get_peak_usage(true);
    }

    public function getRuntimeSnapshot(): FirePdfRuntimeSnapshot
    {
        $this->currentMemoryBytes = memory_get_usage(true);
        $this->peakMemoryBytes = max($this->peakMemoryBytes, memory_get_peak_usage(true), $this->currentMemoryBytes);
        $averageDurationMs = $this->opCount > 0 ? $this->totalDurationMs / $this->opCount : 0.0;

        return new FirePdfRuntimeSnapshot(
            opCount: $this->opCount,
            lastDurationMs: $this->lastDurationMs,
            averageDurationMs: $averageDurationMs,
            maxDurationMs: $this->maxDurationMs,
            currentMemoryBytes: $this->currentMemoryBytes,
            peakMemoryBytes: $this->peakMemoryBytes,
            recycleRecommended: $this->recycleRecommended,
            recycleReason: $this->recycleReason,
        );
    }

    public function resetRuntimeSnapshot(): void
    {
        $this->opCount = 0;
        $this->lastDurationMs = 0.0;
        $this->totalDurationMs = 0.0;
        $this->maxDurationMs = 0.0;
        $this->recycleRecommended = false;
        $this->recycleReason = null;

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $this->currentMemoryBytes = memory_get_usage(true);
        $this->peakMemoryBytes = memory_get_peak_usage(true);
    }

    public function shouldRecycleWorker(): bool
    {
        return $this->recycleRecommended;
    }

    public function close(): void
    {
        $this->inspector->close();
        gc_collect_cycles();

        $this->currentMemoryBytes = memory_get_usage(true);
        $this->peakMemoryBytes = max($this->peakMemoryBytes, memory_get_peak_usage(true));
    }

    /**
     * @template T
     * @param  callable(): T  $operation
     * @return T
     */
    private function observeOperation(callable $operation): mixed
    {
        if (!$this->runtimeOptions->telemetry) {
            return $operation();
        }

        $startedAt = hrtime(true);

        try {
            return $operation();
        } finally {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $this->recordRuntimeStats($durationMs);
        }
    }

    private function recordRuntimeStats(float $durationMs): void
    {
        $this->opCount++;
        $this->lastDurationMs = $durationMs;
        $this->totalDurationMs += $durationMs;
        $this->maxDurationMs = max($this->maxDurationMs, $durationMs);

        if ($this->runtimeOptions->gcCollectEvery > 0 && $this->opCount % $this->runtimeOptions->gcCollectEvery === 0) {
            gc_collect_cycles();
        }

        $currentMemoryBytes = memory_get_usage(true);
        $this->currentMemoryBytes = $currentMemoryBytes;
        $this->peakMemoryBytes = max($this->peakMemoryBytes, memory_get_peak_usage(true), $currentMemoryBytes);

        $this->updateRecycleRecommendation($currentMemoryBytes);
    }

    private function updateRecycleRecommendation(int $currentMemoryBytes): void
    {
        $hardLimitBytes = $this->runtimeOptions->hardLimitBytes();
        if ($hardLimitBytes > 0 && $currentMemoryBytes >= $hardLimitBytes) {
            $this->recycleRecommended = true;
            $this->recycleReason = 'hard_limit_mb_exceeded';

            return;
        }

        $softLimitBytes = $this->runtimeOptions->softLimitBytes();
        if ($softLimitBytes > 0 && $currentMemoryBytes >= $softLimitBytes && !$this->recycleRecommended) {
            $this->recycleRecommended = true;
            $this->recycleReason = 'soft_limit_mb_exceeded';
        }
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
        return $this->observeOperation(function () use ($path, $pages): PdfResult {
            $decoded = $this->inspector->processPdf($path, $pages);

            return PdfResult::fromArray($this->requireDataAssoc($decoded, 'processPdf'));
        });
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function processPdfBytes(string $bytes, ?array $pages = null): PdfResult
    {
        return $this->observeOperation(function () use ($bytes, $pages): PdfResult {
            $decoded = $this->inspector->processPdfBytes($bytes, $pages);

            return PdfResult::fromArray($this->requireDataAssoc($decoded, 'processPdfBytes'));
        });
    }

    public function detectPdf(string $path): PdfResult
    {
        return $this->observeOperation(function () use ($path): PdfResult {
            $decoded = $this->inspector->detectPdf($path);

            return PdfResult::fromArray($this->requireDataAssoc($decoded, 'detectPdf'));
        });
    }

    public function detectPdfBytes(string $bytes): PdfResult
    {
        return $this->observeOperation(function () use ($bytes): PdfResult {
            $decoded = $this->inspector->detectPdfBytes($bytes);

            return PdfResult::fromArray($this->requireDataAssoc($decoded, 'detectPdfBytes'));
        });
    }

    public function classifyPdf(string $path): PdfClassification
    {
        return $this->observeOperation(function () use ($path): PdfClassification {
            $decoded = $this->inspector->classifyPdf($path);

            return PdfClassification::fromArray($this->requireDataAssoc($decoded, 'classifyPdf'));
        });
    }

    public function classifyPdfBytes(string $bytes): PdfClassification
    {
        return $this->observeOperation(function () use ($bytes): PdfClassification {
            $decoded = $this->inspector->classifyPdfBytes($bytes);

            return PdfClassification::fromArray($this->requireDataAssoc($decoded, 'classifyPdfBytes'));
        });
    }

    public function extractText(string $path): string
    {
        return $this->observeOperation(fn (): string => $this->inspector->extractText($path));
    }

    public function extractTextBytes(string $bytes): string
    {
        return $this->observeOperation(fn (): string => $this->inspector->extractTextBytes($bytes));
    }

    /**
     * @param  list<int>|null  $pages
     * @return list<TextItem>
     */
    public function extractTextWithPositions(string $path, ?array $pages = null): array
    {
        return $this->observeOperation(function () use ($path, $pages): array {
            $decoded = $this->inspector->extractTextWithPositions($path, $pages);
            $items = $this->requireDataAssocList($decoded, 'extractTextWithPositions');

            return array_map(
                static fn (array $item): TextItem => TextItem::fromArray($item),
                $items
            );
        });
    }

    /**
     * @param  list<int>|null  $pages
     * @return list<TextItem>
     */
    public function extractTextWithPositionsBytes(string $bytes, ?array $pages = null): array
    {
        return $this->observeOperation(function () use ($bytes, $pages): array {
            $decoded = $this->inspector->extractTextWithPositionsBytes($bytes, $pages);
            $items = $this->requireDataAssocList($decoded, 'extractTextWithPositionsBytes');

            return array_map(
                static fn (array $item): TextItem => TextItem::fromArray($item),
                $items
            );
        });
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTextInRegions(string $path, array $pageRegions): array
    {
        return $this->observeOperation(function () use ($path, $pageRegions): array {
            $decoded = $this->inspector->extractTextInRegions($path, $pageRegions);
            $pages = $this->requireDataAssocList($decoded, 'extractTextInRegions');

            return array_map(
                static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
                $pages
            );
        });
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTextInRegionsBytes(string $bytes, array $pageRegions): array
    {
        return $this->observeOperation(function () use ($bytes, $pageRegions): array {
            $decoded = $this->inspector->extractTextInRegionsBytes($bytes, $pageRegions);
            $pages = $this->requireDataAssocList($decoded, 'extractTextInRegionsBytes');

            return array_map(
                static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
                $pages
            );
        });
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTablesInRegions(string $path, array $pageRegions): array
    {
        return $this->observeOperation(function () use ($path, $pageRegions): array {
            $decoded = $this->inspector->extractTablesInRegions($path, $pageRegions);
            $pages = $this->requireDataAssocList($decoded, 'extractTablesInRegions');

            return array_map(
                static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
                $pages
            );
        });
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return list<PageRegionTexts>
     */
    public function extractTablesInRegionsBytes(string $bytes, array $pageRegions): array
    {
        return $this->observeOperation(function () use ($bytes, $pageRegions): array {
            $decoded = $this->inspector->extractTablesInRegionsBytes($bytes, $pageRegions);
            $pages = $this->requireDataAssocList($decoded, 'extractTablesInRegionsBytes');

            return array_map(
                static fn (array $page): PageRegionTexts => PageRegionTexts::fromArray($page),
                $pages
            );
        });
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function extractPagesMarkdown(string $path, ?array $pages = null): PagesExtractionResult
    {
        return $this->observeOperation(function () use ($path, $pages): PagesExtractionResult {
            $decoded = $this->inspector->extractPagesMarkdown($path, $pages);

            return PagesExtractionResult::fromArray($this->requireDataAssoc($decoded, 'extractPagesMarkdown'));
        });
    }

    /**
     * @param  list<int>|null  $pages
     */
    public function extractPagesMarkdownBytes(string $bytes, ?array $pages = null): PagesExtractionResult
    {
        return $this->observeOperation(function () use ($bytes, $pages): PagesExtractionResult {
            $decoded = $this->inspector->extractPagesMarkdownBytes($bytes, $pages);

            return PagesExtractionResult::fromArray($this->requireDataAssoc($decoded, 'extractPagesMarkdownBytes'));
        });
    }
}
