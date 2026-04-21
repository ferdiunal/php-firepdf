<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Exceptions;

class InvalidInputException extends FirePdfException
{
    public function __construct(string $message = 'Invalid input provided.')
    {
        parent::__construct($message, 0);
    }
}
