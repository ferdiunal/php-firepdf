<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Runtime;

use Ferdiunal\FirePdf\Support\ArrayValue;

final class FirePdfRuntimeOptions
{
    public readonly bool $telemetry;

    public readonly int $gcCollectEvery;

    public readonly int $softLimitMb;

    public readonly int $hardLimitMb;

    public function __construct(
        bool $telemetry = true,
        int $gcCollectEvery = 0,
        int $softLimitMb = 0,
        int $hardLimitMb = 0,
    ) {
        $this->telemetry = $telemetry;
        $this->gcCollectEvery = max(0, $gcCollectEvery);
        $this->softLimitMb = max(0, $softLimitMb);
        $this->hardLimitMb = max(0, $hardLimitMb);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            telemetry: ArrayValue::bool($config, 'telemetry', true),
            gcCollectEvery: ArrayValue::int($config, 'gc_collect_every'),
            softLimitMb: ArrayValue::int($config, 'soft_limit_mb'),
            hardLimitMb: ArrayValue::int($config, 'hard_limit_mb'),
        );
    }

    public function softLimitBytes(): int
    {
        return $this->softLimitMb > 0 ? $this->softLimitMb * 1024 * 1024 : 0;
    }

    public function hardLimitBytes(): int
    {
        return $this->hardLimitMb > 0 ? $this->hardLimitMb * 1024 * 1024 : 0;
    }
}
