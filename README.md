# php-firepdf

PHP FFI wrapper for [pdf-inspector](https://github.com/firecrawl/pdf-inspector), a fast Rust library for PDF classification and text extraction.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ferdiunal/php-firepdf.svg?style=flat-square)](https://packagist.org/packages/ferdiunal/php-firepdf)
[![Total Downloads](https://img.shields.io/packagist/dt/ferdiunal/php-firepdf.svg?style=flat-square)](https://packagist.org/packages/ferdiunal/php-firepdf)

This package exposes the full pdf-inspector API surface (process, detect, classify, extract text, region extraction, per-page markdown) through PHP FFI. It includes a Laravel service provider and facade for seamless integration.

## Requirements

- PHP 8.4+
- PHP FFI extension enabled (`extension=ffi`)
- Rust toolchain (only needed when you build native binaries yourself)

## Installation

```bash
composer require ferdiunal/php-firepdf
```

### Native Library Resolution (Production)

The package resolves the shared library in this order:

1. `FIREPDF_LIB_PATH` (or Laravel `php-firepdf.lib_path`)
2. Bundled package path: `native/lib/<os>-<arch>/`
3. Dev fallback: `native/pdf-inspector-ffi/target/release/`

If your production deployment does not set `FIREPDF_LIB_PATH`, make sure the package contains the prebuilt file under `native/lib/<os>-<arch>/`.

### Build the Rust FFI bridge (local/dev)

```bash
cd vendor/ferdiunal/php-firepdf/native/pdf-inspector-ffi
cargo build --release --locked

# copy the built file into package bundle layout
cd ../..
./scripts/stage-native-bundle.sh
```

### Build Bundles for Win/macOS/Linux

Use GitHub Actions workflow `native-bundles` to produce bundle artifacts for Linux, macOS, and Windows.
The output folder name includes runner architecture (for example: `linux-x86_64`, `darwin-arm64`, `windows-x86_64`).

Each artifact contains:

- `native/lib/<os>-<arch>/<library>`

Include these files in the package release, or set `FIREPDF_LIB_PATH` explicitly at runtime.

### Laravel config (optional)

```bash
php artisan vendor:publish --tag="php-firepdf-config"
```

## Usage

### Standalone

```php
use Ferdiunal\FirePdf\FirePdf;

$pdf = new FirePdf();

// Full processing: detect + extract + markdown
$result = $pdf->processPdf('document.pdf');
echo $result->pdfType;   // TextBased, Scanned, ImageBased, Mixed
echo $result->markdown;  // Markdown string or null

// Fast detection only
$info = $pdf->detectPdf('document.pdf');

// From bytes (no filesystem)
$bytes = file_get_contents('document.pdf');
$result = $pdf->processPdfBytes($bytes);

// Per-page markdown
$pages = $pdf->extractPagesMarkdown('document.pdf');
foreach ($pages->pages as $page) {
    echo "Page {$page->page}: {$page->markdown}";
}
```

### Laravel

```php
use Ferdiunal\FirePdf\Facades\FirePdf;

$result = FirePdf::processPdf('document.pdf');
```

### Laravel AI SDK Tools (Laravel 13)

This package ships optional AI SDK-compatible tools under the
`Ferdiunal\FirePdf\Ai\Tools` namespace:

- `DetectPdfTool`
- `ClassifyPdfTool`
- `ProcessPdfTool`
- `ExtractTextTool`
- `ExtractPagesMarkdownTool`

These tools follow the Laravel AI SDK `Tool` contract and can be returned
explicitly from your agent's `tools()` method:

```php
<?php

namespace App\Ai\Agents;

use Ferdiunal\FirePdf\Ai\Tools\ClassifyPdfTool;
use Ferdiunal\FirePdf\Ai\Tools\DetectPdfTool;
use Ferdiunal\FirePdf\Ai\Tools\ExtractPagesMarkdownTool;
use Ferdiunal\FirePdf\Ai\Tools\ExtractTextTool;
use Ferdiunal\FirePdf\Ai\Tools\ProcessPdfTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

final class PdfAssistant implements Agent, HasTools
{
    use Promptable;

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new DetectPdfTool(),
            new ClassifyPdfTool(),
            new ProcessPdfTool(),
            new ExtractTextTool(),
            new ExtractPagesMarkdownTool(),
        ];
    }
}
```

Tool input is storage-scoped and requires a relative `path` argument. Configure
the disk and base path:

```php
// config/php-firepdf.php
'ai_tools' => [
    'disk' => env('FIREPDF_AI_TOOLS_DISK', 'local'),
    'base_path' => env('FIREPDF_AI_TOOLS_BASE_PATH', 'incoming/pdfs'),
],
```

Example call payload (from an AI tool invocation):

```json
{
  "path": "contracts/sample.pdf"
}
```

If you use these tools, install the Laravel AI SDK in your Laravel app:

```bash
composer require laravel/ai
```

### Laravel Validation Rules (Real PDF Check)

Object rule:

```php
use Ferdiunal\FirePdf\Rules\ValidPdf;

$rules = [
    'document' => ['required', 'file', new ValidPdf()],
];
```

String alias:

```php
$rules = [
    'document' => ['required', 'file', 'firepdf_pdf'],
];
```

Recommended for early filtering + deep validation:

```php
$rules = [
    'document' => ['required', 'file', 'mimetypes:application/pdf', 'firepdf_pdf'],
];
```

## API Reference

| Method | Description |
|---|---|
| `processPdf(path, pages?)` | Full processing (detect + extract + markdown) |
| `processPdfBytes(data, pages?)` | Full processing from bytes |
| `detectPdf(path)` | Fast detection only |
| `detectPdfBytes(data)` | Fast detection from bytes |
| `classifyPdf(path)` | Lightweight classification |
| `classifyPdfBytes(data)` | Lightweight classification from bytes |
| `extractText(path)` | Plain text extraction |
| `extractTextBytes(data)` | Plain text from bytes |
| `extractTextWithPositions(path, pages?)` | Text with X/Y coords and font info |
| `extractTextWithPositionsBytes(data, pages?)` | Positions from bytes |
| `extractTextInRegions(path, pageRegions)` | Extract text in bounding-box regions |
| `extractTextInRegionsBytes(data, pageRegions)` | Region extraction from bytes |
| `extractTablesInRegions(path, pageRegions)` | Table markdown in regions |
| `extractTablesInRegionsBytes(data, pageRegions)` | Table regions from bytes |
| `extractPagesMarkdown(path, pages?)` | Per-page markdown + layout metadata |
| `extractPagesMarkdownBytes(data, pages?)` | Per-page markdown from bytes |
| `getRuntimeSnapshot()` | Returns aggregate runtime telemetry for worker memory/speed |
| `resetRuntimeSnapshot()` | Resets aggregate runtime telemetry counters |
| `shouldRecycleWorker()` | Returns true when configured soft/hard memory limit was exceeded |
| `close()` | Closes the FFI handle and runs a GC cycle |

Validation extensions:

- `Ferdiunal\FirePdf\Rules\ValidPdf` (object rule)
- `firepdf_pdf` (string alias)

## Server Recipes

### Parse Time & Memory Telemetry

```php
$firePdf->resetRuntimeSnapshot();
$result = $firePdf->processPdf($path);
$snapshot = $firePdf->getRuntimeSnapshot();

echo $snapshot->lastDurationMs;      // last operation duration
echo $snapshot->averageDurationMs;   // average duration
echo $snapshot->currentMemoryBytes;  // current process memory
echo $snapshot->peakMemoryBytes;     // process peak memory
```

For quick markdown + telemetry reports on sample PDFs:

```bash
php scripts/test-user-pdfs.php
```

### Swoole / OpenSwoole (request loop)

```php
$result = $firePdf->processPdf($path);

if ($firePdf->shouldRecycleWorker()) {
    // Mark worker for graceful recycle at end of request.
}
```

### FrankenPHP worker mode

```php
$result = $firePdf->processPdf($path);

if ($firePdf->shouldRecycleWorker()) {
    // Trigger worker restart in your supervisor/worker control flow.
}
```

### RoadRunner worker

```php
$result = $firePdf->processPdf($path);

if ($firePdf->shouldRecycleWorker()) {
    // Stop current worker and let RR spawn a fresh one.
}
```

Recommended policy:

- Use worker `max requests` and `shouldRecycleWorker()` together.
- Set `soft_limit_mb` below your process hard limit.
- Set `hard_limit_mb` as a deterministic recycle threshold.

## Testing

```bash
# Native build
cd native/pdf-inspector-ffi
cargo build --release --locked

# PHP tests (requires the FFI library to be built)
composer test

# PHP static analysis
composer analyse
```

## License

MIT. Please see [License File](LICENSE.md) for more information.
