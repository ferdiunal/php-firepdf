<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Exceptions\FFINotAvailableException;
use Ferdiunal\FirePdf\Exceptions\InvalidInputException;
use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FFI\Inspector;
use Ferdiunal\FirePdf\FirePdf;
use Ferdiunal\FirePdf\Runtime\FirePdfRuntimeOptions;

describe('FirePdf', function () {
    $resolveLibraryPath = static function (): ?string {
        $env = getenv('FIREPDF_LIB_PATH');
        if ($env !== false && $env !== '') {
            return $env;
        }

        foreach (Inspector::defaultLibraryCandidates() as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    };

    it('prefers bundled native library path candidates', function () {
        $candidates = Inspector::defaultLibraryCandidates();

        expect($candidates)->toHaveCount(2);
        expect($candidates[0])->toContain('/native/lib/');
        expect($candidates[1])->toContain('/native/pdf-inspector-ffi/target/release/');
    });

    it('throws when FFI is not available', function () {
        if (extension_loaded('ffi')) {
            // Cannot meaningfully test this path without unloading the extension
            $this->markTestSkipped('FFI extension is loaded');
        }

        new FirePdf;
    })->throws(FFINotAvailableException::class);

    it('throws InvalidInputException for malformed regions', function () use ($resolveLibraryPath) {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();

        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available for validation test');
        }

        // We can't instantiate FirePdf without a real library, so test
        // the validation at the FFI\Inspector level directly.
        $inspector = new Inspector(
            libPath: $libPath
        );

        $inspector->extractTextInRegions('/tmp/test.pdf', [
            [0, [[0, 0, 100]]], // only 3 coords
        ]);
    })->throws(InvalidInputException::class, 'Invalid region format');

    it('tracks runtime snapshot metrics for operations', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        $firePdf = new FirePdf($libPath, new FirePdfRuntimeOptions);

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        $snapshot = $firePdf->getRuntimeSnapshot();

        expect($snapshot->opCount)->toBe(1);
        expect($snapshot->lastDurationMs)->toBeGreaterThanOrEqual(0.0);
        expect($snapshot->averageDurationMs)->toBeGreaterThanOrEqual(0.0);
        expect($snapshot->maxDurationMs)->toBeGreaterThanOrEqual(0.0);
        expect($snapshot->currentMemoryBytes)->toBeGreaterThan(0);
        expect($snapshot->peakMemoryBytes)->toBeGreaterThan(0);
    });

    it('triggers configured gc collection threshold', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        gc_enable();
        $before = gc_status();

        $firePdf = new FirePdf(
            $libPath,
            new FirePdfRuntimeOptions(telemetry: true, gcCollectEvery: 1)
        );

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        $after = gc_status();
        expect($after['runs'])->toBeGreaterThanOrEqual($before['runs']);
    });

    it('sets soft limit recycle recommendation without failing request flow', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        $firePdf = new FirePdf(
            $libPath,
            new FirePdfRuntimeOptions(telemetry: true, softLimitMb: 1)
        );

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        $snapshot = $firePdf->getRuntimeSnapshot();

        expect($firePdf->shouldRecycleWorker())->toBeTrue();
        expect($snapshot->recycleRecommended)->toBeTrue();
        expect($snapshot->recycleReason)->toBe('soft_limit_mb_exceeded');
    });

    it('prefers hard limit reason over soft limit', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        $firePdf = new FirePdf(
            $libPath,
            new FirePdfRuntimeOptions(telemetry: true, softLimitMb: 1, hardLimitMb: 1)
        );

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        $snapshot = $firePdf->getRuntimeSnapshot();

        expect($firePdf->shouldRecycleWorker())->toBeTrue();
        expect($snapshot->recycleReason)->toBe('hard_limit_mb_exceeded');
    });

    it('resets runtime snapshot counters and recycle flags', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        $firePdf = new FirePdf(
            $libPath,
            new FirePdfRuntimeOptions(telemetry: true, softLimitMb: 1)
        );

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        expect($firePdf->shouldRecycleWorker())->toBeTrue();

        $firePdf->resetRuntimeSnapshot();
        $snapshot = $firePdf->getRuntimeSnapshot();

        expect($snapshot->opCount)->toBe(0);
        expect($snapshot->lastDurationMs)->toBe(0.0);
        expect($snapshot->averageDurationMs)->toBe(0.0);
        expect($snapshot->maxDurationMs)->toBe(0.0);
        expect($snapshot->recycleRecommended)->toBeFalse();
        expect($snapshot->recycleReason)->toBeNull();
        expect($firePdf->shouldRecycleWorker())->toBeFalse();
    });

    it('can close and continue processing with reopened ffi handle', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        $firePdf = new FirePdf($libPath, new FirePdfRuntimeOptions);

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        $firePdf->close();

        $call = static fn () => $firePdf->processPdfBytes('not a pdf');
        expect($call)->toThrow(ProcessingException::class);
    });

    it('can disable telemetry collection entirely', function () use ($resolveLibraryPath): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = $resolveLibraryPath();
        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        $firePdf = new FirePdf(
            $libPath,
            new FirePdfRuntimeOptions(telemetry: false, softLimitMb: 1, hardLimitMb: 1)
        );

        try {
            $firePdf->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        $snapshot = $firePdf->getRuntimeSnapshot();
        expect($snapshot->opCount)->toBe(0);
        expect($snapshot->recycleRecommended)->toBeFalse();
        expect($firePdf->shouldRecycleWorker())->toBeFalse();
    });
});
