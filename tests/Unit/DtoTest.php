<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\DTOs\PageMarkdown;
use Ferdiunal\FirePdf\DTOs\PageRegionTexts;
use Ferdiunal\FirePdf\DTOs\PagesExtractionResult;
use Ferdiunal\FirePdf\DTOs\PdfClassification;
use Ferdiunal\FirePdf\DTOs\PdfResult;
use Ferdiunal\FirePdf\DTOs\RegionText;
use Ferdiunal\FirePdf\DTOs\TextItem;

describe('DTOs', function () {
    it('creates PdfResult from array', function () {
        $dto = PdfResult::fromArray([
            'pdf_type' => 'TextBased',
            'markdown' => '# Hello',
            'page_count' => 5,
            'processing_time_ms' => 42,
            'pages_needing_ocr' => [1, 3],
            'title' => 'Test',
            'confidence' => 0.95,
            'is_complex_layout' => true,
            'pages_with_tables' => [2],
            'pages_with_columns' => [3],
            'has_encoding_issues' => false,
        ]);

        expect($dto->pdfType)->toBe('TextBased');
        expect($dto->markdown)->toBe('# Hello');
        expect($dto->pageCount)->toBe(5);
        expect($dto->processingTimeMs)->toBe(42);
        expect($dto->pagesNeedingOcr)->toBe([1, 3]);
        expect($dto->title)->toBe('Test');
        expect($dto->confidence)->toBe(0.95);
        expect($dto->isComplexLayout)->toBeTrue();
        expect($dto->pagesWithTables)->toBe([2]);
        expect($dto->pagesWithColumns)->toBe([3]);
        expect($dto->hasEncodingIssues)->toBeFalse();
    });

    it('maps PdfResult from Rust JSON envelope with nested layout', function () {
        $envelope = [
            'ok' => true,
            'data' => [
                'pdf_type' => 'Mixed',
                'markdown' => null,
                'page_count' => 7,
                'processing_time_ms' => 18,
                'pages_needing_ocr' => [2, 5],
                'title' => null,
                'confidence' => 0.81,
                'layout' => [
                    'is_complex' => true,
                    'pages_with_tables' => [3],
                    'pages_with_columns' => [4, 6],
                ],
                'has_encoding_issues' => true,
            ],
        ];

        $dto = PdfResult::fromArray($envelope['data']);

        expect($dto->pdfType)->toBe('Mixed');
        expect($dto->isComplexLayout)->toBeTrue();
        expect($dto->pagesWithTables)->toBe([3]);
        expect($dto->pagesWithColumns)->toBe([4, 6]);
        expect($dto->hasEncodingIssues)->toBeTrue();
    });

    it('creates PdfClassification from array', function () {
        $dto = PdfClassification::fromArray([
            'pdf_type' => 'Scanned',
            'page_count' => 10,
            'pages_needing_ocr' => [0, 2, 4],
            'confidence' => 0.88,
        ]);

        expect($dto->pdfType)->toBe('Scanned');
        expect($dto->pageCount)->toBe(10);
        expect($dto->pagesNeedingOcr)->toBe([0, 2, 4]);
        expect($dto->confidence)->toBe(0.88);
    });

    it('creates TextItem from array', function () {
        $dto = TextItem::fromArray([
            'text' => 'Hello',
            'x' => 10.5,
            'y' => 20.0,
            'width' => 30.0,
            'height' => 12.0,
            'font' => 'Arial',
            'font_size' => 11.0,
            'page' => 1,
            'is_bold' => true,
            'is_italic' => false,
            'item_type' => 'Text',
            'link_url' => null,
        ]);

        expect($dto->text)->toBe('Hello');
        expect($dto->x)->toBe(10.5);
        expect($dto->isBold)->toBeTrue();
        expect($dto->itemType)->toBe('Text');
        expect($dto->linkUrl)->toBeNull();
    });

    it('creates TextItem Link from array', function () {
        $dto = TextItem::fromArray([
            'text' => 'Click',
            'x' => 0.0,
            'y' => 0.0,
            'width' => 0.0,
            'height' => 0.0,
            'font' => '',
            'font_size' => 0.0,
            'page' => 1,
            'is_bold' => false,
            'is_italic' => false,
            'item_type' => ['Link' => 'https://example.com'],
            'link_url' => null,
        ]);

        expect($dto->itemType)->toBe('Link');
        expect($dto->linkUrl)->toBe('https://example.com');
    });

    it('creates PageRegionTexts from array', function () {
        $dto = PageRegionTexts::fromArray([
            'page' => 2,
            'regions' => [
                ['text' => 'Region 1', 'needs_ocr' => false],
                ['text' => '', 'needs_ocr' => true],
            ],
        ]);

        expect($dto->page)->toBe(2);
        expect($dto->regions)->toHaveCount(2);
        expect($dto->regions[0])->toBeInstanceOf(RegionText::class);
        expect($dto->regions[0]->needsOcr)->toBeFalse();
        expect($dto->regions[1]->needsOcr)->toBeTrue();
    });

    it('creates PagesExtractionResult from array', function () {
        $dto = PagesExtractionResult::fromArray([
            'pages' => [
                ['page' => 0, 'markdown' => '# Page 1', 'needs_ocr' => false],
                ['page' => 1, 'markdown' => '', 'needs_ocr' => true],
            ],
            'pages_with_tables' => [1],
            'pages_with_columns' => [2],
            'pages_needing_ocr' => [2],
            'is_complex' => true,
        ]);

        expect($dto->pages)->toHaveCount(2);
        expect($dto->pages[0])->toBeInstanceOf(PageMarkdown::class);
        expect($dto->pages[0]->markdown)->toBe('# Page 1');
        expect($dto->pages[1]->needsOcr)->toBeTrue();
        expect($dto->isComplex)->toBeTrue();
    });
});
