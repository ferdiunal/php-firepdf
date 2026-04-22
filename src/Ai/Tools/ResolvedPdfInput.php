<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

final readonly class ResolvedPdfInput
{
    public function __construct(
        public string $requestedPath,
        public string $scopedPath,
        public string $bytes,
    ) {}
}
