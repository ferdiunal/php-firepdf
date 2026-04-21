<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\Exceptions\FFINotAvailableException;
use Ferdiunal\FirePdf\Exceptions\InvalidInputException;
use Ferdiunal\FirePdf\FirePdf;
use Ferdiunal\FirePdf\FFI\Inspector;

describe('FirePdf', function () {
    it('prefers bundled native library path candidates', function () {
        $candidates = Inspector::defaultLibraryCandidates();

        expect($candidates)->toHaveCount(2);
        expect($candidates[0])->toContain('/native/lib/');
        expect($candidates[1])->toContain('/native/pdf-inspector-ffi/target/release/');
    });

    it('throws when FFI is not available', function () {
        if (extension_loaded('ffi')) {
            // Cannot meaningfully test this path without unloading the extension
            $this->markTestSkipped('FFI extension is loaded');
        }

        new FirePdf();
    })->throws(FFINotAvailableException::class);

    it('throws InvalidInputException for malformed regions', function () {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }

        $libPath = null;
        foreach (Inspector::defaultLibraryCandidates() as $candidate) {
            if (is_file($candidate)) {
                $libPath = $candidate;
                break;
            }
        }

        if ($libPath === null) {
            $this->markTestSkipped('Native FFI library is not available for validation test');
        }

        // We can't instantiate FirePdf without a real library, so test
        // the validation at the FFI\Inspector level directly.
        $inspector = new \Ferdiunal\FirePdf\FFI\Inspector(
            libPath: $libPath
        );

        $inspector->extractTextInRegions('/tmp/test.pdf', [
            [0, [[0, 0, 100]]], // only 3 coords
        ]);
    })->throws(InvalidInputException::class, 'Invalid region format');
});
