<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class PageMarkdown
{
    /**
     * @param  int  $page  0-indexed page number.
     * @param  string  $markdown  Formatted markdown for this page.
     * @param  bool  $needsOcr  True when text on this page is unreliable.
     */
    public function __construct(
        public int $page,
        public string $markdown,
        public bool $needsOcr,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            page: ArrayValue::int($data, 'page'),
            markdown: ArrayValue::string($data, 'markdown'),
            needsOcr: ArrayValue::bool($data, 'needs_ocr'),
        );
    }
}
