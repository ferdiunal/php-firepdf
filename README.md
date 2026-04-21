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
