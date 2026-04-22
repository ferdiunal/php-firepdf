<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Runtime\FirePdfRuntimeOptions;

describe('FirePdfRuntimeOptions', function () {
    it('provides safe defaults', function (): void {
        $options = new FirePdfRuntimeOptions;

        expect($options->telemetry)->toBeTrue();
        expect($options->gcCollectEvery)->toBe(0);
        expect($options->softLimitMb)->toBe(0);
        expect($options->hardLimitMb)->toBe(0);
        expect($options->softLimitBytes())->toBe(0);
        expect($options->hardLimitBytes())->toBe(0);
    });

    it('normalizes negative numeric inputs', function (): void {
        $options = new FirePdfRuntimeOptions(
            telemetry: true,
            gcCollectEvery: -5,
            softLimitMb: -10,
            hardLimitMb: -20,
        );

        expect($options->gcCollectEvery)->toBe(0);
        expect($options->softLimitMb)->toBe(0);
        expect($options->hardLimitMb)->toBe(0);
    });

    it('parses runtime config payload', function (): void {
        $options = FirePdfRuntimeOptions::fromArray([
            'telemetry' => '0',
            'gc_collect_every' => '2',
            'soft_limit_mb' => '64',
            'hard_limit_mb' => 128,
        ]);

        expect($options->telemetry)->toBeFalse();
        expect($options->gcCollectEvery)->toBe(2);
        expect($options->softLimitMb)->toBe(64);
        expect($options->hardLimitMb)->toBe(128);
        expect($options->softLimitBytes())->toBe(64 * 1024 * 1024);
        expect($options->hardLimitBytes())->toBe(128 * 1024 * 1024);
    });
});
