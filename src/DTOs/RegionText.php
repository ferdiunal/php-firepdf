<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class RegionText
{
    /**
     * @param  string  $text  Extracted text.
     * @param  bool  $needsOcr  True when the text should not be trusted.
     */
    public function __construct(
        public string $text,
        public bool $needsOcr,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            text: ArrayValue::string($data, 'text'),
            needsOcr: ArrayValue::bool($data, 'needs_ocr'),
        );
    }
}
