<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Rules;

use Closure;
use Ferdiunal\FirePdf\Validation\PdfUploadValidator;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPdf implements ValidationRule
{
    public function __construct(private readonly ?PdfUploadValidator $validator = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->validator()->validate($value)) {
            return;
        }

        $fail(__('firepdf::validation.valid_pdf', ['attribute' => str_replace('_', ' ', $attribute)]));
    }

    private function validator(): PdfUploadValidator
    {
        if ($this->validator instanceof PdfUploadValidator) {
            return $this->validator;
        }

        /** @var PdfUploadValidator $validator */
        $validator = app(PdfUploadValidator::class);

        return $validator;
    }
}
