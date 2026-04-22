<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Ai\Tools\ClassifyPdfTool;
use Ferdiunal\FirePdf\Ai\Tools\DetectPdfTool;
use Ferdiunal\FirePdf\Ai\Tools\ExtractPagesMarkdownTool;
use Ferdiunal\FirePdf\Ai\Tools\ExtractTextTool;
use Ferdiunal\FirePdf\Ai\Tools\PdfToolInputResolver;
use Ferdiunal\FirePdf\Ai\Tools\ProcessPdfTool;
use Ferdiunal\FirePdf\DTOs\PageMarkdown;
use Ferdiunal\FirePdf\DTOs\PagesExtractionResult;
use Ferdiunal\FirePdf\DTOs\PdfClassification;
use Ferdiunal\FirePdf\DTOs\PdfResult;
use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FirePdf;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

if (! interface_exists(Tool::class) || ! class_exists(Request::class)) {
    test('AI tools require laravel/ai package', function (): void {
        $this->markTestSkipped('laravel/ai is not installed in this environment.');
    });

    return;
}

describe('AI tools', function () {
    beforeEach(function (): void {
        Storage::fake('firepdf-ai');
        config()->set('php-firepdf.ai_tools.disk', 'firepdf-ai');
        config()->set('php-firepdf.ai_tools.base_path', 'incoming/docs');
    });

    afterEach(function (): void {
        Mockery::close();
    });

    $fakePdfResult = static fn (): PdfResult => new PdfResult(
        pdfType: 'TextBased',
        markdown: '# Parsed',
        pageCount: 1,
        processingTimeMs: 10,
        pagesNeedingOcr: [],
        title: 'Sample',
        confidence: 0.98,
        isComplexLayout: false,
        pagesWithTables: [],
        pagesWithColumns: [],
        hasEncodingIssues: false,
    );

    $makeToolContext = static function (FirePdf $firePdf): array {
        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        return [
            'detect' => new DetectPdfTool($firePdf, $resolver),
            'classify' => new ClassifyPdfTool($firePdf, $resolver),
            'process' => new ProcessPdfTool($firePdf, $resolver),
            'extract_text' => new ExtractTextTool($firePdf, $resolver),
            'extract_pages_markdown' => new ExtractPagesMarkdownTool($firePdf, $resolver),
        ];
    };

    it('defines valid schemas for tool inputs', function () use ($makeToolContext): void {
        $firePdf = Mockery::mock(FirePdf::class);
        $tools = $makeToolContext($firePdf);
        $schema = new JsonSchemaTypeFactory;

        $detectSchema = $tools['detect']->schema($schema);
        $processSchema = $tools['process']->schema($schema);

        expect($detectSchema)->toHaveKeys(['path']);
        expect($processSchema)->toHaveKeys(['path', 'pages']);
    });

    it('returns successful payloads for all extended tools', function () use ($makeToolContext, $fakePdfResult): void {
        $pdfBytes = '%PDF-1.7 test bytes';
        Storage::disk('firepdf-ai')->put('incoming/docs/reports/sample.pdf', $pdfBytes);

        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldReceive('detectPdfBytes')
            ->times(6)
            ->with($pdfBytes)
            ->andReturn($fakePdfResult());

        $firePdf->shouldReceive('classifyPdfBytes')
            ->once()
            ->with($pdfBytes)
            ->andReturn(new PdfClassification('TextBased', 1, [], 0.97));

        $firePdf->shouldReceive('processPdfBytes')
            ->once()
            ->with($pdfBytes, [1, 0])
            ->andReturn($fakePdfResult());

        $firePdf->shouldReceive('extractTextBytes')
            ->once()
            ->with($pdfBytes)
            ->andReturn('Full raw extracted text');

        $firePdf->shouldReceive('extractPagesMarkdownBytes')
            ->once()
            ->with($pdfBytes, [0])
            ->andReturn(
                new PagesExtractionResult(
                    pages: [new PageMarkdown(0, '# Page 1', false)],
                    pagesWithTables: [],
                    pagesWithColumns: [],
                    pagesNeedingOcr: [],
                    isComplex: false,
                )
            );

        $tools = $makeToolContext($firePdf);

        $detectPayload = json_decode((string) $tools['detect']->handle(new Request([
            'path' => 'reports/sample.pdf',
        ])), true, 512, JSON_THROW_ON_ERROR);

        $classifyPayload = json_decode((string) $tools['classify']->handle(new Request([
            'path' => 'reports/sample.pdf',
        ])), true, 512, JSON_THROW_ON_ERROR);

        $processPayload = json_decode((string) $tools['process']->handle(new Request([
            'path' => 'reports/sample.pdf',
            'pages' => [1, 0, 0],
        ])), true, 512, JSON_THROW_ON_ERROR);

        $extractTextPayload = json_decode((string) $tools['extract_text']->handle(new Request([
            'path' => 'reports/sample.pdf',
        ])), true, 512, JSON_THROW_ON_ERROR);

        $extractPagesPayload = json_decode((string) $tools['extract_pages_markdown']->handle(new Request([
            'path' => 'reports/sample.pdf',
            'pages' => [0, 0],
        ])), true, 512, JSON_THROW_ON_ERROR);

        expect($detectPayload['ok'])->toBeTrue();
        expect($detectPayload['tool'])->toBe('detect_pdf');
        expect($detectPayload['input']['scoped_path'])->toBe('incoming/docs/reports/sample.pdf');

        expect($classifyPayload['ok'])->toBeTrue();
        expect($classifyPayload['tool'])->toBe('classify_pdf');
        expect($classifyPayload['data']['pdfType'])->toBe('TextBased');

        expect($processPayload['ok'])->toBeTrue();
        expect($processPayload['tool'])->toBe('process_pdf');
        expect($processPayload['data']['markdown'])->toBe('# Parsed');

        expect($extractTextPayload['ok'])->toBeTrue();
        expect($extractTextPayload['tool'])->toBe('extract_text');
        expect($extractTextPayload['data'])->toBe('Full raw extracted text');

        expect($extractPagesPayload['ok'])->toBeTrue();
        expect($extractPagesPayload['tool'])->toBe('extract_pages_markdown');
        expect($extractPagesPayload['data']['pages'][0]['markdown'])->toBe('# Page 1');
    });

    it('returns invalid_input for traversal and missing file cases', function () use ($makeToolContext): void {
        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldNotReceive('detectPdfBytes');

        $tool = $makeToolContext($firePdf)['detect'];

        $traversalPayload = json_decode((string) $tool->handle(new Request([
            'path' => '../secrets/file.pdf',
        ])), true, 512, JSON_THROW_ON_ERROR);

        $missingPayload = json_decode((string) $tool->handle(new Request([
            'path' => 'reports/missing.pdf',
        ])), true, 512, JSON_THROW_ON_ERROR);

        expect($traversalPayload['ok'])->toBeFalse();
        expect($traversalPayload['error']['code'])->toBe('invalid_input');

        expect($missingPayload['ok'])->toBeFalse();
        expect($missingPayload['error']['code'])->toBe('invalid_input');
    });

    it('returns deterministic ProcessingException codes from validation phase', function () use ($makeToolContext): void {
        $pdfBytes = '%PDF-1.7 broken parse';
        Storage::disk('firepdf-ai')->put('incoming/docs/reports/broken.pdf', $pdfBytes);

        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldReceive('detectPdfBytes')
            ->once()
            ->with($pdfBytes)
            ->andThrow(new ProcessingException('InvalidPdf', 'Could not parse PDF bytes.'));

        $tool = $makeToolContext($firePdf)['detect'];

        $payload = json_decode((string) $tool->handle(new Request([
            'path' => 'reports/broken.pdf',
        ])), true, 512, JSON_THROW_ON_ERROR);

        expect($payload['ok'])->toBeFalse();
        expect($payload['error']['code'])->toBe('InvalidPdf');
        expect($payload['error']['message'])->toBe('Could not parse PDF bytes.');
    });
});
