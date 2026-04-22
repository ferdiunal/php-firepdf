<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Ferdiunal\FirePdf\Exceptions\InvalidInputException;
use Ferdiunal\FirePdf\FirePdf;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

final class PdfToolInputResolver
{
    public function __construct(
        private readonly ?FirePdf $firePdf = null,
        private readonly ?FilesystemFactory $filesystems = null,
    ) {}

    public function resolveFromPath(string $requestedPath): ResolvedPdfInput
    {
        $normalizedPath = $this->normalizeRequestedPath($requestedPath);
        $this->assertPdfExtension($normalizedPath);

        $scopedPath = $this->toScopedPath($normalizedPath);
        $disk = $this->disk();

        if (! $disk->exists($scopedPath)) {
            throw new InvalidInputException('The requested PDF was not found in the configured AI tool storage scope.');
        }

        try {
            $bytes = $disk->get($scopedPath);
        } catch (Throwable) {
            throw new InvalidInputException('The requested PDF could not be read from the configured storage disk.');
        }

        if (! is_string($bytes) || $bytes === '') {
            throw new InvalidInputException('The requested PDF is empty or unreadable.');
        }

        // Parse validation: keep behavior deterministic for malformed files.
        $this->firePdf()->detectPdfBytes($bytes);

        return new ResolvedPdfInput(
            requestedPath: $normalizedPath,
            scopedPath: $scopedPath,
            bytes: $bytes,
        );
    }

    private function toScopedPath(string $path): string
    {
        $basePath = $this->configuredBasePath();

        return $basePath === '' ? $path : $basePath.'/'.$path;
    }

    private function configuredDisk(): string
    {
        $configured = function_exists('config')
            ? config('php-firepdf.ai_tools.disk', 'local')
            : 'local';

        if (! is_string($configured) || trim($configured) === '') {
            throw new InvalidInputException('The AI tool disk configuration is invalid.');
        }

        return trim($configured);
    }

    private function configuredBasePath(): string
    {
        $configured = function_exists('config')
            ? config('php-firepdf.ai_tools.base_path', '')
            : '';

        if (! is_string($configured)) {
            throw new InvalidInputException('The AI tool base path configuration is invalid.');
        }

        return $this->normalizeConfiguredPath($configured);
    }

    private function normalizeConfiguredPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            throw new InvalidInputException('The AI tool base path must be relative.');
        }

        $path = ltrim($path, '/');

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidInputException('The AI tool base path may not contain parent directory segments.');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function normalizeRequestedPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            throw new InvalidInputException('The path argument must be a non-empty string.');
        }

        if (str_contains($path, "\0")) {
            throw new InvalidInputException('The path argument contains invalid characters.');
        }

        if ($this->isAbsolutePath($path)) {
            throw new InvalidInputException('Absolute paths are not allowed. Use a path relative to the configured AI tool storage scope.');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidInputException('Path traversal is not allowed.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new InvalidInputException('The path argument must point to a PDF file within the configured storage scope.');
        }

        return implode('/', $segments);
    }

    private function assertPdfExtension(string $path): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            throw new InvalidInputException('Only files with the .pdf extension are allowed.');
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function firePdf(): FirePdf
    {
        if ($this->firePdf instanceof FirePdf) {
            return $this->firePdf;
        }

        if (! function_exists('app')) {
            throw new InvalidInputException('Laravel application container is not available.');
        }

        /** @var mixed $service */
        $service = app(FirePdf::class);

        if (! $service instanceof FirePdf) {
            throw new InvalidInputException('Unable to resolve FirePdf from the application container.');
        }

        return $service;
    }

    private function disk(): Filesystem
    {
        try {
            return $this->filesystems()->disk($this->configuredDisk());
        } catch (Throwable) {
            throw new InvalidInputException('Unable to resolve the configured AI tool storage disk.');
        }
    }

    private function filesystems(): FilesystemFactory
    {
        if ($this->filesystems instanceof FilesystemFactory) {
            return $this->filesystems;
        }

        if (! function_exists('app')) {
            throw new InvalidInputException('Laravel application container is not available.');
        }

        /** @var mixed $factory */
        $factory = app(FilesystemFactory::class);

        if (! $factory instanceof FilesystemFactory) {
            throw new InvalidInputException('Unable to resolve the filesystem factory from the application container.');
        }

        return $factory;
    }
}
