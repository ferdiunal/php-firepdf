<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Exceptions;

class ProcessingException extends FirePdfException
{
    public readonly string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, 0);
    }
}
