# Changelog

All notable changes to `php-firepdf` will be documented in this file.

## v0.1.0 - 2026-04-22

**Full Changelog**: https://github.com/ferdiunal/php-firepdf/commits/v0.1.0

## [0.1.0] - 2026-04-22

### Added

- Laravel AI SDK uyumlu tool seti eklendi (`DetectPdfTool`, `ClassifyPdfTool`, `ProcessPdfTool`, `ExtractTextTool`, `ExtractPagesMarkdownTool`).
- Tool input çözümleme katmanı eklendi (`PdfToolInputResolver`) ve storage-scope güvenlik kontrolleri uygulandı:
  - relatif path zorunluluğu
  - path traversal engeli (`..`)
  - `.pdf` uzantı doğrulaması
  - scope dışı dosya erişimlerinin engellenmesi
  
- `php-firepdf.ai_tools.disk` ve `php-firepdf.ai_tools.base_path` konfigürasyon anahtarları eklendi.
- AI tool davranışları için kapsamlı feature testleri eklendi.
- Teknik Türkçe dokümantasyon dosyası eklendi (`README.TR.md`).

### Changed

- Ana README dosyasına çok dilli dokümantasyon geçiş bağlantıları eklendi (`English` / `Türkçe`).
