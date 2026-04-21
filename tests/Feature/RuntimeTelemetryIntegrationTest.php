<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\FirePdf;
use Ferdiunal\FirePdf\FFI\Inspector;
use Ferdiunal\FirePdf\Runtime\FirePdfRuntimeOptions;

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

$resolveSamplePdf = static function (): ?string {
    $projectRoot = dirname(__DIR__, 2);
    $candidates = [
        $projectRoot . '/SaaS Chatbot Yapılandırma ve Maliyet Analizi.pdf',
        $projectRoot . '/o_henry_oykuleri_turkce.pdf',
        $projectRoot . '/../pdf-inspector/tests/fixtures/2013-app2.pdf',
        $projectRoot . '/../pdf-inspector/tests/fixtures/td9264.pdf',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && filesize($candidate) > 0) {
            return $candidate;
        }
    }

    return null;
};

describe('Runtime telemetry integration', function () use ($resolveLibraryPath, $resolveSamplePdf) {
    beforeEach(function () use ($resolveLibraryPath, $resolveSamplePdf): void {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        if ($resolveLibraryPath() === null) {
            $this->markTestSkipped('Native FFI library is not built');
        }

        if ($resolveSamplePdf() === null) {
            $this->markTestSkipped('No sample PDF found for telemetry loop integration test');
        }
    });

    it('keeps runtime snapshot stable across parse loop', function () use ($resolveLibraryPath, $resolveSamplePdf): void {
        $libPath = $resolveLibraryPath();
        $samplePdf = $resolveSamplePdf();
        if ($libPath === null || $samplePdf === null) {
            $this->markTestSkipped('Runtime telemetry prerequisites are not available');
        }

        $firePdf = new FirePdf(
            $libPath,
            new FirePdfRuntimeOptions(telemetry: true)
        );

        $startSnapshot = $firePdf->getRuntimeSnapshot();

        $iterations = 8;
        for ($i = 0; $i < $iterations; $i++) {
            $firePdf->processPdf($samplePdf);
        }

        $endSnapshot = $firePdf->getRuntimeSnapshot();
        $memoryGrowth = $endSnapshot->currentMemoryBytes - $startSnapshot->currentMemoryBytes;

        expect($endSnapshot->opCount)->toBe($iterations);
        expect($endSnapshot->averageDurationMs)->toBeGreaterThan(0.0);
        expect($endSnapshot->maxDurationMs)->toBeGreaterThan(0.0);
        expect($endSnapshot->recycleRecommended)->toBeFalse();
        expect($memoryGrowth)->toBeLessThan(96 * 1024 * 1024);
    });
});
