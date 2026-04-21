<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Exceptions;

final class NativeLibraryNotFoundException extends FirePdfException
{
    /**
     * @param  list<string>  $candidates
     */
    public function __construct(?string $requestedPath, array $candidates)
    {
        $lines = [
            'Native pdf-inspector FFI library could not be found.',
        ];

        if (is_string($requestedPath) && $requestedPath !== '') {
            $lines[] = "Requested path: {$requestedPath}";
        }

        if ($candidates !== []) {
            $lines[] = 'Checked paths:';
            foreach ($candidates as $candidate) {
                $lines[] = " - {$candidate}";
            }
        }

        $lines[] = 'Set FIREPDF_LIB_PATH (or php-firepdf.lib_path) to an absolute library path, or ship bundled libs under native/lib/<os>-<arch>/.';

        parent::__construct(implode("\n", $lines), 0);
    }
}
