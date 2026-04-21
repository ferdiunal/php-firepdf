<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Exceptions;

class FFINotAvailableException extends FirePdfException
{
    public function __construct(string $message = 'PHP FFI extension is not available.')
    {
        parent::__construct($message, 0);
    }
}
