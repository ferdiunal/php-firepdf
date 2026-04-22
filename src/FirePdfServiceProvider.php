<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf;

use Ferdiunal\FirePdf\Runtime\FirePdfRuntimeOptions;
use Ferdiunal\FirePdf\Support\ArrayValue;
use Ferdiunal\FirePdf\Validation\PdfUploadValidator;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FirePdfServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('php-firepdf')
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FirePdf::class, static function (): FirePdf {
            $libPath = config('php-firepdf.lib_path');
            $runtime = config('php-firepdf.runtime');

            $runtimeOptions = FirePdfRuntimeOptions::fromArray(
                is_array($runtime) ? ArrayValue::assocFromValue($runtime) : []
            );

            return new FirePdf(
                is_string($libPath) && $libPath !== '' ? $libPath : null,
                $runtimeOptions
            );
        });
    }

    public function packageBooted(): void
    {
        $this->loadTranslationsFrom(dirname(__DIR__).'/resources/lang', 'firepdf');

        Validator::extend(
            'firepdf_pdf',
            static function (string $attribute, mixed $value): bool {
                /** @var PdfUploadValidator $validator */
                $validator = app(PdfUploadValidator::class);

                return $validator->validate($value);
            }
        );

        Validator::replacer(
            'firepdf_pdf',
            static function (string $message, string $attribute): string {
                return __('firepdf::validation.valid_pdf', [
                    'attribute' => str_replace('_', ' ', $attribute),
                ]);
            }
        );
    }
}
