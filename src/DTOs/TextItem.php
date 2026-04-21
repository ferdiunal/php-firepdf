<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\DTOs;

use Ferdiunal\FirePdf\Support\ArrayValue;

final readonly class TextItem
{
    /**
     * @param  string  $text  The text content.
     * @param  float  $x  X position on page.
     * @param  float  $y  Y position on page.
     * @param  float  $width  Width of text.
     * @param  float  $height  Height of text.
     * @param  string  $font  Font name.
     * @param  float  $fontSize  Font size.
     * @param  int  $page  Page number (1-indexed).
     * @param  bool  $isBold  Whether the font is bold.
     * @param  bool  $isItalic  Whether the font is italic.
     * @param  string  $itemType  'Text', 'Image', 'Link', or 'FormField'.
     * @param  ?string  $linkUrl  URL for link items, null for other types.
     */
    public function __construct(
        public string $text,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $font,
        public float $fontSize,
        public int $page,
        public bool $isBold,
        public bool $isItalic,
        public string $itemType,
        public ?string $linkUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $itemType = 'Text';
        $linkUrl = ArrayValue::nullableString($data, 'link_url');
        $itemTypeRaw = $data['item_type'] ?? null;

        if (is_string($itemTypeRaw) && $itemTypeRaw !== '') {
            $itemType = $itemTypeRaw;
        }

        if (is_array($itemTypeRaw)) {
            $firstKey = array_key_first($itemTypeRaw);
            if (is_string($firstKey) && $firstKey !== '') {
                $itemType = $firstKey;
            }

            $linkValue = $itemTypeRaw['Link'] ?? null;
            if (is_string($linkValue)) {
                $itemType = 'Link';
                $linkUrl = $linkValue;
            } elseif (is_int($linkValue) || is_float($linkValue)) {
                $itemType = 'Link';
                $linkUrl = (string) $linkValue;
            }
        }

        return new self(
            text: ArrayValue::string($data, 'text'),
            x: ArrayValue::float($data, 'x'),
            y: ArrayValue::float($data, 'y'),
            width: ArrayValue::float($data, 'width'),
            height: ArrayValue::float($data, 'height'),
            font: ArrayValue::string($data, 'font'),
            fontSize: ArrayValue::float($data, 'font_size'),
            page: ArrayValue::int($data, 'page'),
            isBold: ArrayValue::bool($data, 'is_bold'),
            isItalic: ArrayValue::bool($data, 'is_italic'),
            itemType: $itemType,
            linkUrl: $linkUrl,
        );
    }
}
