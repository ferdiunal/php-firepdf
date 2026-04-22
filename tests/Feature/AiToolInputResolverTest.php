<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Ai\Tools\PdfToolInputResolver;
use Ferdiunal\FirePdf\DTOs\PdfResult;
use Ferdiunal\FirePdf\Exceptions\InvalidInputException;
use Ferdiunal\FirePdf\FirePdf;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Storage;

describe('AI tool input resolver', function () {
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

    it('resolves storage-scoped paths and validates parseability', function () use ($fakePdfResult): void {
        $pdfBytes = '%PDF-1.7 sample';
        Storage::disk('firepdf-ai')->put('incoming/docs/reports/sample.pdf', $pdfBytes);

        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldReceive('detectPdfBytes')
            ->once()
            ->with($pdfBytes)
            ->andReturn($fakePdfResult());

        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        $resolved = $resolver->resolveFromPath('reports/sample.pdf');

        expect($resolved->requestedPath)->toBe('reports/sample.pdf');
        expect($resolved->scopedPath)->toBe('incoming/docs/reports/sample.pdf');
        expect($resolved->bytes)->toBe($pdfBytes);
    });

    it('normalizes base_path config before resolving scoped path', function () use ($fakePdfResult): void {
        config()->set('php-firepdf.ai_tools.base_path', '/incoming//docs/');

        $pdfBytes = '%PDF-1.7 normalized';
        Storage::disk('firepdf-ai')->put('incoming/docs/reports/normalized.pdf', $pdfBytes);

        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldReceive('detectPdfBytes')
            ->once()
            ->with($pdfBytes)
            ->andReturn($fakePdfResult());

        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        $resolved = $resolver->resolveFromPath('reports/normalized.pdf');

        expect($resolved->scopedPath)->toBe('incoming/docs/reports/normalized.pdf');
    });

    it('rejects traversal attempts', function (): void {
        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldNotReceive('detectPdfBytes');

        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        $resolve = static fn () => $resolver->resolveFromPath('../secrets/private.pdf');

        expect($resolve)->toThrow(InvalidInputException::class, 'Path traversal is not allowed');
    });

    it('rejects non-pdf files', function (): void {
        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldNotReceive('detectPdfBytes');

        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        $resolve = static fn () => $resolver->resolveFromPath('reports/sample.txt');

        expect($resolve)->toThrow(InvalidInputException::class, 'Only files with the .pdf extension are allowed.');
    });

    it('rejects absolute paths', function (): void {
        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldNotReceive('detectPdfBytes');

        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        $resolve = static fn () => $resolver->resolveFromPath('/tmp/sample.pdf');

        expect($resolve)->toThrow(InvalidInputException::class, 'Absolute paths are not allowed');
    });

    it('rejects missing files within the configured scope', function (): void {
        $firePdf = Mockery::mock(FirePdf::class);
        $firePdf->shouldNotReceive('detectPdfBytes');

        $resolver = new PdfToolInputResolver(
            firePdf: $firePdf,
            filesystems: app(FilesystemFactory::class),
        );

        $resolve = static fn () => $resolver->resolveFromPath('reports/missing.pdf');

        expect($resolve)->toThrow(InvalidInputException::class, 'was not found');
    });
});
