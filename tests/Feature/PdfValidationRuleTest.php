<?php

declare(strict_types=1);

use Ferdiunal\FirePdf\FirePdf as FirePdfService;
use Ferdiunal\FirePdf\Rules\ValidPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function resolveValidPdfFixturePath(): ?string
{
    $projectRoot = dirname(__DIR__, 2);

    $candidates = [
        $projectRoot . '/SaaS Chatbot Yapılandırma ve Maliyet Analizi.pdf',
        $projectRoot . '/o_henry_oykuleri_turkce.pdf',
        $projectRoot . '/../pdf-inspector/tests/fixtures/2013-app2.pdf',
        $projectRoot . '/../pdf-inspector/tests/fixtures/td9264.pdf',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && filesize($candidate) > 0) {
            return $candidate;
        }
    }

    return null;
}

function makeUploadedFile(string $path, string $originalName): UploadedFile
{
    return new UploadedFile(
        $path,
        $originalName,
        'application/pdf',
        null,
        true
    );
}

describe('PDF validation rules', function () {
    beforeEach(function (): void {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension is not available');
        }
    });

    it('passes with object rule for a valid pdf fixture', function (): void {
        $fixturePath = resolveValidPdfFixturePath();
        if ($fixturePath === null) {
            $this->markTestSkipped('No valid PDF fixture was found for validation rule test');
        }

        $file = makeUploadedFile($fixturePath, 'fixture.pdf');

        $validator = Validator::make(
            ['document' => $file],
            ['document' => ['required', 'file', new ValidPdf()]]
        );

        expect($validator->fails())->toBeFalse();
    });

    it('fails with object rule for non-pdf content and returns translated message', function (): void {
        $tempPath = tempnam(sys_get_temp_dir(), 'firepdf-invalid-');
        if ($tempPath === false) {
            $this->fail('Failed to create temporary file');
        }

        file_put_contents($tempPath, 'not a pdf');

        try {
            $file = makeUploadedFile($tempPath, 'fake.pdf');

            $validator = Validator::make(
                ['document' => $file],
                ['document' => ['required', 'file', new ValidPdf()]]
            );

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->first('document'))
                ->toBe(__('firepdf::validation.valid_pdf', ['attribute' => 'document']));
        } finally {
            @unlink($tempPath);
        }
    });

    it('keeps parity between object rule and string alias', function (): void {
        $fixturePath = resolveValidPdfFixturePath();
        if ($fixturePath === null) {
            $this->markTestSkipped('No valid PDF fixture was found for validation rule test');
        }

        $validFile = makeUploadedFile($fixturePath, 'fixture.pdf');

        $objectPass = Validator::make(
            ['document' => $validFile],
            ['document' => ['required', 'file', new ValidPdf()]]
        );

        expect($objectPass->fails())->toBeFalse();

        $aliasPass = Validator::make(
            ['document' => $validFile],
            ['document' => ['required', 'file', 'firepdf_pdf']]
        );

        expect($aliasPass->fails())->toBeFalse();

        $tempPath = tempnam(sys_get_temp_dir(), 'firepdf-invalid-');
        if ($tempPath === false) {
            $this->fail('Failed to create temporary file');
        }

        file_put_contents($tempPath, 'plain text with pdf extension');

        try {
            $invalidFile = makeUploadedFile($tempPath, 'fake.pdf');

            $objectFail = Validator::make(
                ['document' => $invalidFile],
                ['document' => ['required', 'file', new ValidPdf()]]
            );

            $aliasFail = Validator::make(
                ['document' => $invalidFile],
                ['document' => ['required', 'file', 'firepdf_pdf']]
            );

            expect($objectFail->fails())->toBeTrue();
            expect($aliasFail->fails())->toBeTrue();
            expect($aliasFail->errors()->first('document'))
                ->toBe(__('firepdf::validation.valid_pdf', ['attribute' => 'document']));
        } finally {
            @unlink($tempPath);
        }
    });

    it('fails closed when FirePdf cannot be resolved', function (): void {
        $fixturePath = resolveValidPdfFixturePath();
        if ($fixturePath === null) {
            $this->markTestSkipped('No valid PDF fixture was found for validation rule test');
        }

        app()->bind(FirePdfService::class, static fn () => throw new RuntimeException('Native unavailable'));

        $file = makeUploadedFile($fixturePath, 'fixture.pdf');

        $validator = Validator::make(
            ['document' => $file],
            ['document' => ['required', 'file', 'firepdf_pdf']]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('document'))
            ->toBe(__('firepdf::validation.valid_pdf', ['attribute' => 'document']));
    });
});
