<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class PageRegionTexts
{
    /**
     * @param  int  $page  0-indexed page number.
     * @param  list<RegionText>  $regions  Per-region results.
     */
    public function __construct(
        public int $page,
        public array $regions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $regionPayloads */
        $regionPayloads = ArrayValue::assocList($data, 'regions');

        /** @var list<RegionText> $regions */
        $regions = array_map(
            static fn (array $regionData): RegionText => RegionText::fromArray($regionData),
            $regionPayloads
        );

        return new self(
            page: ArrayValue::int($data, 'page'),
            regions: $regions,
        );
    }
}
