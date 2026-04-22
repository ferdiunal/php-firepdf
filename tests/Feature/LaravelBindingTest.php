<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\Facades\FirePdf;
use Ferdiunal\FirePdf\FFI\Inspector;
use Ferdiunal\FirePdf\FirePdf as FirePdfClass;

describe('Laravel binding', function () {
    beforeEach(function (): void {
        if (! extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }
    });

    it('registers FirePdf as a singleton', function () {
        $a = app(FirePdfClass::class);
        $b = app(FirePdfClass::class);

        expect($a)->toBeInstanceOf(FirePdfClass::class);
        expect($a)->toBe($b);
    });

    it('resolves FirePdf via facade', function () {
        $instance = FirePdf::getFacadeRoot();

        expect($instance)->toBeInstanceOf(FirePdfClass::class);
    });

    it('reads config lib_path', function () {
        $libPath = null;
        foreach (Inspector::defaultLibraryCandidates() as $candidate) {
            if (is_file($candidate)) {
                $libPath = $candidate;
                break;
            }
        }

        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        config()->set('php-firepdf.lib_path', $libPath);
        $instance = app(FirePdfClass::class);

        expect($instance)->toBeInstanceOf(FirePdfClass::class);
    });

    it('applies runtime config from service container binding', function () {
        $libPath = null;
        foreach (Inspector::defaultLibraryCandidates() as $candidate) {
            if (is_file($candidate)) {
                $libPath = $candidate;
                break;
            }
        }

        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available');
        }

        config()->set('php-firepdf.lib_path', $libPath);
        config()->set('php-firepdf.runtime', [
            'telemetry' => true,
            'gc_collect_every' => 0,
            'soft_limit_mb' => 1,
            'hard_limit_mb' => 0,
        ]);

        $instance = app(FirePdfClass::class);

        try {
            $instance->processPdfBytes('not a pdf');
        } catch (ProcessingException) {
            // Expected for invalid bytes.
        }

        expect($instance->shouldRecycleWorker())->toBeTrue();
        expect($instance->getRuntimeSnapshot()->recycleReason)->toBe('soft_limit_mb_exceeded');
    });
});
