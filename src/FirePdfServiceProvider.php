<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FirePdfServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('php-firepdf')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FirePdf::class, static function (): FirePdf {
            $libPath = config('php-firepdf.lib_path');

            return new FirePdf(is_string($libPath) && $libPath !== '' ? $libPath : null);
        });
    }
}
