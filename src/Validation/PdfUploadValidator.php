<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Validation;

use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FirePdf;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

final class PdfUploadValidator
{
    public function validate(mixed $value): bool
    {
        if (!$value instanceof File) {
            return false;
        }

        $path = $this->resolveReadablePath($value);
        if ($path === null) {
            return false;
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes)) {
            return false;
        }

        try {
            $firePdf = app(FirePdf::class);
            if (!$firePdf instanceof FirePdf) {
                return false;
            }

            $firePdf->detectPdfBytes($bytes);

            return true;
        } catch (ProcessingException) {
            return false;
        } catch (Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }

            return false;
        }
    }

    private function resolveReadablePath(File $file): ?string
    {
        $realPath = $file->getRealPath();
        if (is_string($realPath) && $realPath !== '' && is_file($realPath) && is_readable($realPath)) {
            return $realPath;
        }

        $pathname = $file->getPathname();
        if (is_string($pathname) && $pathname !== '' && is_file($pathname) && is_readable($pathname)) {
            return $pathname;
        }

        return null;
    }
}
