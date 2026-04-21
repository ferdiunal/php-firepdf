<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Runtime;

final class FirePdfRuntimeSnapshot
{
    public function __construct(
        public readonly int $opCount,
        public readonly float $lastDurationMs,
        public readonly float $averageDurationMs,
        public readonly float $maxDurationMs,
        public readonly int $currentMemoryBytes,
        public readonly int $peakMemoryBytes,
        public readonly bool $recycleRecommended,
        public readonly ?string $recycleReason,
    ) {}
}
