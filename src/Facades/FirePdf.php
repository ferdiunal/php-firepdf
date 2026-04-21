<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ferdiunal\FirePdf\FirePdf
 */
class FirePdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ferdiunal\FirePdf\FirePdf::class;
    }
}
