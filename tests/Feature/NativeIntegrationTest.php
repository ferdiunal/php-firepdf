<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FirePdf;
use Ferdiunal\FirePdf\FFI\Inspector;

$resolveLibraryPath = static function (): ?string {
    $env = getenv('FIREPDF_LIB_PATH');
    if ($env !== false && $env !== '') {
        return $env;
    }

    foreach (Inspector::defaultLibraryCandidates() as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
};

describe('Native integration', function () use ($resolveLibraryPath) {
    beforeEach(function () use ($resolveLibraryPath): void {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        if ($resolveLibraryPath() === null) {
            $this->markTestSkipped('Native FFI library is not built');
        }
    });

    it('returns a processing exception for non-pdf bytes', function () use ($resolveLibraryPath): void {
        $firePdf = new FirePdf($resolveLibraryPath());
        $firePdf->processPdfBytes('not a pdf');
    })->throws(ProcessingException::class);
});
