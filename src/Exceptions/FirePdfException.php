<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Exceptions;

use Exception;

class FirePdfException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
