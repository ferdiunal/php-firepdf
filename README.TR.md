# php-firepdf

[pdf-inspector](https://github.com/firecrawl/pdf-inspector) için PHP FFI sarmalayıcısıdır. `pdf-inspector`, PDF sınıflandırma ve metin çıkarımı için hızlı bir Rust kütüphanesidir.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ferdiunal/php-firepdf.svg?style=flat-square)](https://packagist.org/packages/ferdiunal/php-firepdf)
[![Total Downloads](https://img.shields.io/packagist/dt/ferdiunal/php-firepdf.svg?style=flat-square)](https://packagist.org/packages/ferdiunal/php-firepdf)

Dokümantasyon: [English](README.md) | **Türkçe**

Bu paket, `pdf-inspector` API yüzeyinin tamamını (process, detect, classify, metin çıkarımı, bölgesel çıkarım, sayfa bazlı markdown) PHP FFI üzerinden sunar. Laravel ile sorunsuz entegrasyon için service provider ve facade içerir.

## Gereksinimler

- PHP 8.4+
- PHP FFI uzantısı etkin (`extension=ffi`)
- Rust toolchain (yalnızca native binary dosyalarını kendiniz derleyecekseniz gerekir)

## Kurulum

```bash
composer require ferdiunal/php-firepdf
```

### Native Kütüphane Çözümleme Sırası (Production)

Paket, paylaşımlı kütüphane dosyasını şu sırayla çözümler:

1. `FIREPDF_LIB_PATH` (veya Laravel `php-firepdf.lib_path`)
2. Paket içi gömülü yol: `native/lib/<os>-<arch>/`
3. Geliştirme fallback yolu: `native/pdf-inspector-ffi/target/release/`

Production ortamında `FIREPDF_LIB_PATH` ayarlanmıyorsa, paket içinde `native/lib/<os>-<arch>/` altında önceden derlenmiş dosyanın bulunduğundan emin olun.

### Rust FFI köprüsünü derleme (local/dev)

```bash
cd vendor/ferdiunal/php-firepdf/native/pdf-inspector-ffi
cargo build --release --locked

# derlenen dosyayı paket bundle düzenine kopyala
cd ../..
./scripts/stage-native-bundle.sh
```

### Win/macOS/Linux için Bundle üretimi

Linux, macOS ve Windows için bundle artifact üretmek üzere GitHub Actions içindeki `native-bundles` workflow'unu kullanın.
Çıktı klasör adı runner mimarisini içerir (örneğin: `linux-x86_64`, `darwin-arm64`, `windows-x86_64`).

Her artifact şu yolu içerir:

- `native/lib/<os>-<arch>/<library>`

Bu dosyaları paket sürümüne dahil edin veya çalışma zamanında `FIREPDF_LIB_PATH` değerini açıkça ayarlayın.

### Laravel config (opsiyonel)

```bash
php artisan vendor:publish --tag="php-firepdf-config"
```

## Kullanım

### Bağımsız kullanım (Standalone)

```php
use Ferdiunal\FirePdf\FirePdf;

$pdf = new FirePdf();

// Tam işleme: detect + extract + markdown
$result = $pdf->processPdf('document.pdf');
echo $result->pdfType;   // TextBased, Scanned, ImageBased, Mixed
echo $result->markdown;  // Markdown string veya null

// Sadece hızlı tespit
$info = $pdf->detectPdf('document.pdf');

// Byte verisinden (dosya sistemi olmadan)
$bytes = file_get_contents('document.pdf');
$result = $pdf->processPdfBytes($bytes);

// Sayfa bazlı markdown
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

Bu paket, `Ferdiunal\FirePdf\Ai\Tools` namespace'i altında opsiyonel AI SDK uyumlu tool sınıfları sunar:

- `DetectPdfTool`
- `ClassifyPdfTool`
- `ProcessPdfTool`
- `ExtractTextTool`
- `ExtractPagesMarkdownTool`

Bu tool sınıfları Laravel AI SDK `Tool` kontratını uygular ve agent sınıfınızdaki `tools()` metodundan açıkça döndürülebilir:

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

Tool girdisi storage-scope mantığı ile çalışır ve relatif bir `path` argümanı gerektirir. Disk ve base path ayarı:

```php
// config/php-firepdf.php
'ai_tools' => [
    'disk' => env('FIREPDF_AI_TOOLS_DISK', 'local'),
    'base_path' => env('FIREPDF_AI_TOOLS_BASE_PATH', 'incoming/pdfs'),
],
```

Örnek tool çağrı payload'u (AI tool invocation içinden):

```json
{
  "path": "contracts/sample.pdf"
}
```

Bu tool'ları kullanacaksanız, Laravel uygulamanızda Laravel AI SDK paketini kurun:

```bash
composer require laravel/ai
```

### Laravel Validation Rules (Gerçek PDF doğrulaması)

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

Erken filtreleme + derin doğrulama için önerilen kullanım:

```php
$rules = [
    'document' => ['required', 'file', 'mimetypes:application/pdf', 'firepdf_pdf'],
];
```

## API Referansı

| Metot | Açıklama |
|---|---|
| `processPdf(path, pages?)` | Tam işleme (detect + extract + markdown) |
| `processPdfBytes(data, pages?)` | Byte verisinden tam işleme |
| `detectPdf(path)` | Sadece hızlı tespit |
| `detectPdfBytes(data)` | Byte verisinden hızlı tespit |
| `classifyPdf(path)` | Hafif sınıflandırma |
| `classifyPdfBytes(data)` | Byte verisinden hafif sınıflandırma |
| `extractText(path)` | Düz metin çıkarımı |
| `extractTextBytes(data)` | Byte verisinden düz metin |
| `extractTextWithPositions(path, pages?)` | X/Y koordinatları ve font bilgisi ile metin |
| `extractTextWithPositionsBytes(data, pages?)` | Byte verisinden konumlu metin |
| `extractTextInRegions(path, pageRegions)` | Sınır kutusu bölgelerinde metin çıkarımı |
| `extractTextInRegionsBytes(data, pageRegions)` | Byte verisinden bölgesel metin çıkarımı |
| `extractTablesInRegions(path, pageRegions)` | Bölgelerde tablo markdown çıkarımı |
| `extractTablesInRegionsBytes(data, pageRegions)` | Byte verisinden bölgesel tablo çıkarımı |
| `extractPagesMarkdown(path, pages?)` | Sayfa bazlı markdown + layout metadata |
| `extractPagesMarkdownBytes(data, pages?)` | Byte verisinden sayfa bazlı markdown |
| `getRuntimeSnapshot()` | Worker bellek/hız telemetrisi için toplu runtime snapshot döndürür |
| `resetRuntimeSnapshot()` | Toplu runtime telemetri sayaçlarını sıfırlar |
| `shouldRecycleWorker()` | Soft/hard bellek limiti aşıldıysa `true` döndürür |
| `close()` | FFI handle'ını kapatır ve GC döngüsü çalıştırır |

Validation uzantıları:

- `Ferdiunal\FirePdf\Rules\ValidPdf` (object rule)
- `firepdf_pdf` (string alias)

## Sunucu Tarifleri

### Parse süresi ve bellek telemetrisi

```php
$firePdf->resetRuntimeSnapshot();
$result = $firePdf->processPdf($path);
$snapshot = $firePdf->getRuntimeSnapshot();

echo $snapshot->lastDurationMs;      // son operasyon süresi
echo $snapshot->averageDurationMs;   // ortalama operasyon süresi
echo $snapshot->currentMemoryBytes;  // mevcut proses belleği
echo $snapshot->peakMemoryBytes;     // proses tepe bellek
```

Örnek PDF'ler için hızlı markdown + telemetri raporu:

```bash
php scripts/test-user-pdfs.php
```

### Swoole / OpenSwoole (request loop)

```php
$result = $firePdf->processPdf($path);

if ($firePdf->shouldRecycleWorker()) {
    // Worker'ı request sonunda graceful recycle için işaretle.
}
```

### FrankenPHP worker modu

```php
$result = $firePdf->processPdf($path);

if ($firePdf->shouldRecycleWorker()) {
    // Supervisor/worker kontrol akışınızda worker restart tetikleyin.
}
```

### RoadRunner worker

```php
$result = $firePdf->processPdf($path);

if ($firePdf->shouldRecycleWorker()) {
    // Mevcut worker'ı durdurun ve RR'nin yeni worker başlatmasına izin verin.
}
```

Önerilen politika:

- Worker `max requests` ayarı ile `shouldRecycleWorker()` kontrolünü birlikte kullanın.
- `soft_limit_mb` değerini proses hard limitinin altında ayarlayın.
- `hard_limit_mb` değerini deterministik recycle eşiği olarak belirleyin.

## Test

```bash
# Native build
cd native/pdf-inspector-ffi
cargo build --release --locked

# PHP testleri (FFI kütüphanesinin derlenmiş olması gerekir)
composer test

# PHP statik analiz
composer analyse
```

## Lisans

MIT. Detaylar için [License File](LICENSE.md) dosyasına bakın.
