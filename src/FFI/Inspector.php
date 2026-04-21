<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\FFI;

use FFI;
use Ferdiunal\FirePdf\Exceptions\FFINotAvailableException;
use Ferdiunal\FirePdf\Exceptions\InvalidInputException;
use Ferdiunal\FirePdf\Exceptions\NativeLibraryNotFoundException;
use Ferdiunal\FirePdf\Exceptions\ProcessingException;

/**
 * Low-level FFI wrapper around the Rust pdf-inspector shared library.
 *
 * All methods return decoded JSON envelopes:
 * {"ok":true,"data":...} or {"ok":false,"error":{"code":"...","message":"..."}}
 */
class Inspector
{
    private ?FFI $ffi = null;

    private readonly string $libPath;

    private readonly string $header;

    public function __construct(?string $libPath = null)
    {
        if (!extension_loaded('ffi')) {
            throw new FFINotAvailableException();
        }

        $this->libPath = $libPath ?? $this->resolveDefaultLibPath();
        if (!is_file($this->libPath)) {
            throw new NativeLibraryNotFoundException(
                requestedPath: $this->libPath,
                candidates: self::defaultLibraryCandidates()
            );
        }

        $this->header = $this->buildHeader();
    }

    /**
     * @return list<string>
     */
    public static function defaultLibraryCandidates(): array
    {
        $packageRoot = dirname(__DIR__, 2);
        $libraryName = self::libraryFileNameForOs(PHP_OS_FAMILY);
        $platform = self::platformSlug(PHP_OS_FAMILY, php_uname('m'));

        return [
            // Production-ready bundled location shipped with the package.
            $packageRoot . '/native/lib/' . $platform . '/' . $libraryName,
            // Development fallback for local Rust builds.
            $packageRoot . '/native/pdf-inspector-ffi/target/release/' . $libraryName,
        ];
    }

    private function resolveDefaultLibPath(): string
    {
        $env = $_ENV['FIREPDF_LIB_PATH'] ?? false;
        if ($env === false || $env === '') {
            $env = getenv('FIREPDF_LIB_PATH');
        }
        if (is_string($env) && $env !== '') {
            return $env;
        }

        foreach (self::defaultLibraryCandidates() as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Return first candidate so constructor emits a consistent fail-fast exception.
        return self::defaultLibraryCandidates()[0];
    }

    private static function libraryFileNameForOs(string $osFamily): string
    {
        return match ($osFamily) {
            'Darwin' => 'libpdf_inspector_ffi.dylib',
            'Windows' => 'pdf_inspector_ffi.dll',
            default => 'libpdf_inspector_ffi.so',
        };
    }

    private static function platformSlug(string $osFamily, string $machine): string
    {
        $os = match ($osFamily) {
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            default => 'linux',
        };

        $arch = strtolower($machine);
        $arch = match ($arch) {
            'x86_64', 'amd64', 'x64' => 'x86_64',
            'aarch64', 'arm64' => 'arm64',
            default => preg_replace('/[^a-z0-9_]/', '-', $arch) ?? 'unknown',
        };

        return $os . '-' . $arch;
    }

    private function buildHeader(): string
    {
        return <<<'C'
char* firepdf_process_pdf(const char* path, const char* pages_json);
char* firepdf_process_pdf_bytes(const uint8_t* data_ptr, size_t data_len, const char* pages_json);
char* firepdf_detect_pdf(const char* path);
char* firepdf_detect_pdf_bytes(const uint8_t* data_ptr, size_t data_len);
char* firepdf_classify_pdf(const char* path);
char* firepdf_classify_pdf_bytes(const uint8_t* data_ptr, size_t data_len);
char* firepdf_extract_text(const char* path);
char* firepdf_extract_text_bytes(const uint8_t* data_ptr, size_t data_len);
char* firepdf_extract_text_with_positions(const char* path, const char* pages_json);
char* firepdf_extract_text_with_positions_bytes(const uint8_t* data_ptr, size_t data_len, const char* pages_json);
char* firepdf_extract_text_in_regions(const char* path, const char* regions_json);
char* firepdf_extract_text_in_regions_bytes(const uint8_t* data_ptr, size_t data_len, const char* regions_json);
char* firepdf_extract_tables_in_regions(const char* path, const char* regions_json);
char* firepdf_extract_tables_in_regions_bytes(const uint8_t* data_ptr, size_t data_len, const char* regions_json);
char* firepdf_extract_pages_markdown(const char* path, const char* pages_json);
char* firepdf_extract_pages_markdown_bytes(const uint8_t* data_ptr, size_t data_len, const char* pages_json);
void firepdf_free_string(char* ptr);
C;
    }

    private function ffi(): FFI
    {
        if ($this->ffi === null) {
            $this->ffi = FFI::cdef($this->header, $this->libPath);
        }

        return $this->ffi;
    }

    /**
     * @return array{ok: true, data: mixed}
     */
    private function callAndDecode(string $method, mixed ...$args): array
    {
        $ffi = $this->ffi();
        $cStr = $ffi->$method(...$args);
        if ($cStr === null) {
            throw new ProcessingException('NullResponse', 'FFI returned null');
        }
        $json = FFI::string($cStr);
        $ffi->firepdf_free_string($cStr);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new ProcessingException('JsonDecode', 'Failed to decode FFI JSON response');
        }
        if (($decoded['ok'] ?? false) === false) {
            $err = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $code = is_string($err['code'] ?? null) ? $err['code'] : 'Unknown';
            $message = is_string($err['message'] ?? null) ? $err['message'] : 'Unknown error';
            throw new ProcessingException($code, $message);
        }

        if (!array_key_exists('data', $decoded)) {
            throw new ProcessingException('InvalidResponse', 'FFI response is missing data payload');
        }

        return [
            'ok' => true,
            'data' => $decoded['data'],
        ];
    }

    /**
     * @param  list<int>|null  $pages
     * @return string|null
     */
    private function encodePages(?array $pages): ?string
    {
        if ($pages === null) {
            return null;
        }

        return json_encode(array_values($pages), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     */
    private function encodeRegions(array $pageRegions): string
    {
        $payload = [];
        foreach ($pageRegions as $entry) {
            if (!is_array($entry) || count($entry) !== 2) {
                throw new InvalidInputException('Invalid pageRegions format. Expected [page, [[x1,y1,x2,y2], ...]]');
            }
            $page = (int) $entry[0];
            $regions = [];
            foreach ($entry[1] as $r) {
                if (!is_array($r) || count($r) !== 4) {
                    throw new InvalidInputException('Invalid region format. Expected [x1, y1, x2, y2]');
                }
                $regions[] = [(float) $r[0], (float) $r[1], (float) $r[2], (float) $r[3]];
            }
            $payload[] = [$page, $regions];
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{0: \FFI\CData, 1: int}
     */
    private function toByteBuffer(string $bytes): array
    {
        $length = strlen($bytes);
        $allocationSize = max(1, $length);
        $buffer = $this->ffi()->new("uint8_t[{$allocationSize}]");

        if ($length > 0) {
            FFI::memcpy($buffer, $bytes, $length);
        }

        return [$buffer, $length];
    }

    /**
     * @param  array{ok: true, data: mixed}  $result
     */
    private function requireStringData(array $result, string $operation): string
    {
        $data = $result['data'];
        if (is_string($data)) {
            return $data;
        }
        if (is_int($data) || is_float($data)) {
            return (string) $data;
        }

        throw new ProcessingException('InvalidResponse', "Expected string payload for {$operation}");
    }

    /**
     * @param  list<int>|null  $pages
     * @return array<string, mixed>
     */
    public function processPdf(string $path, ?array $pages = null): array
    {
        return $this->callAndDecode(
            'firepdf_process_pdf',
            $path,
            $this->encodePages($pages)
        );
    }

    /**
     * @param  list<int>|null  $pages
     * @return array<string, mixed>
     */
    public function processPdfBytes(string $bytes, ?array $pages = null): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode(
            'firepdf_process_pdf_bytes',
            $buffer,
            $length,
            $this->encodePages($pages)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function detectPdf(string $path): array
    {
        return $this->callAndDecode('firepdf_detect_pdf', $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function detectPdfBytes(string $bytes): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode('firepdf_detect_pdf_bytes', $buffer, $length);
    }

    /**
     * @return array<string, mixed>
     */
    public function classifyPdf(string $path): array
    {
        return $this->callAndDecode('firepdf_classify_pdf', $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function classifyPdfBytes(string $bytes): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode('firepdf_classify_pdf_bytes', $buffer, $length);
    }

    public function extractText(string $path): string
    {
        $result = $this->callAndDecode('firepdf_extract_text', $path);

        return $this->requireStringData($result, 'extractText');
    }

    public function extractTextBytes(string $bytes): string
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);
        $result = $this->callAndDecode('firepdf_extract_text_bytes', $buffer, $length);

        return $this->requireStringData($result, 'extractTextBytes');
    }

    /**
     * @param  list<int>|null  $pages
     * @return array<string, mixed>
     */
    public function extractTextWithPositions(string $path, ?array $pages = null): array
    {
        return $this->callAndDecode(
            'firepdf_extract_text_with_positions',
            $path,
            $this->encodePages($pages)
        );
    }

    /**
     * @param  list<int>|null  $pages
     * @return array<string, mixed>
     */
    public function extractTextWithPositionsBytes(string $bytes, ?array $pages = null): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode(
            'firepdf_extract_text_with_positions_bytes',
            $buffer,
            $length,
            $this->encodePages($pages)
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return array<string, mixed>
     */
    public function extractTextInRegions(string $path, array $pageRegions): array
    {
        return $this->callAndDecode(
            'firepdf_extract_text_in_regions',
            $path,
            $this->encodeRegions($pageRegions)
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return array<string, mixed>
     */
    public function extractTextInRegionsBytes(string $bytes, array $pageRegions): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode(
            'firepdf_extract_text_in_regions_bytes',
            $buffer,
            $length,
            $this->encodeRegions($pageRegions)
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return array<string, mixed>
     */
    public function extractTablesInRegions(string $path, array $pageRegions): array
    {
        return $this->callAndDecode(
            'firepdf_extract_tables_in_regions',
            $path,
            $this->encodeRegions($pageRegions)
        );
    }

    /**
     * @param  list<array{0: int, 1: list<array{0: float, 1: float, 2: float, 3: float}>}>  $pageRegions
     * @return array<string, mixed>
     */
    public function extractTablesInRegionsBytes(string $bytes, array $pageRegions): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode(
            'firepdf_extract_tables_in_regions_bytes',
            $buffer,
            $length,
            $this->encodeRegions($pageRegions)
        );
    }

    /**
     * @param  list<int>|null  $pages
     * @return array<string, mixed>
     */
    public function extractPagesMarkdown(string $path, ?array $pages = null): array
    {
        return $this->callAndDecode(
            'firepdf_extract_pages_markdown',
            $path,
            $this->encodePages($pages)
        );
    }

    /**
     * @param  list<int>|null  $pages
     * @return array<string, mixed>
     */
    public function extractPagesMarkdownBytes(string $bytes, ?array $pages = null): array
    {
        [$buffer, $length] = $this->toByteBuffer($bytes);

        return $this->callAndDecode(
            'firepdf_extract_pages_markdown_bytes',
            $buffer,
            $length,
            $this->encodePages($pages)
        );
    }
}
