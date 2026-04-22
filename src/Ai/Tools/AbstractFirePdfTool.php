<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Ferdiunal\FirePdf\Exceptions\InvalidInputException;
use Ferdiunal\FirePdf\Exceptions\ProcessingException;
use Ferdiunal\FirePdf\FirePdf;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Ai\Tools\Request;
use Throwable;

abstract class AbstractFirePdfTool
{
    public function __construct(
        private readonly ?FirePdf $firePdf = null,
        private readonly ?PdfToolInputResolver $inputResolver = null,
    ) {}

    /**
     * @return array<string, Type>
     */
    protected function pathSchema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->min(1)
                ->description('Path to a PDF file, relative to config("php-firepdf.ai_tools.base_path") on the configured disk.')
                ->required(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function pathAndPagesSchema(JsonSchema $schema): array
    {
        return [
            ...$this->pathSchema($schema),
            'pages' => $schema->array()
                ->items($schema->integer()->min(0))
                ->description('Optional list of zero-indexed page numbers to process.'),
        ];
    }

    protected function resolvePdfInput(Request $request): ResolvedPdfInput
    {
        $requestPayload = $request->toArray();
        $path = $requestPayload['path'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            throw new InvalidInputException('The path argument is required and must be a non-empty string.');
        }

        return $this->inputResolver()->resolveFromPath($path);
    }

    /**
     * @return list<int>|null
     */
    protected function extractPages(Request $request): ?array
    {
        $requestPayload = $request->toArray();
        $pages = $requestPayload['pages'] ?? null;

        if ($pages === null) {
            return null;
        }

        if (! is_array($pages)) {
            throw new InvalidInputException('The pages argument must be an array of non-negative integers.');
        }

        $normalized = [];
        foreach ($pages as $page) {
            if (! is_int($page) || $page < 0) {
                throw new InvalidInputException('Each page in the pages argument must be a non-negative integer.');
            }

            $normalized[] = $page;
        }

        /** @var list<int> $uniquePages */
        $uniquePages = array_values(array_unique($normalized));

        return $uniquePages;
    }

    protected function successResponse(string $tool, ResolvedPdfInput $input, mixed $data): string
    {
        return $this->toJson([
            'ok' => true,
            'tool' => $tool,
            'input' => [
                'path' => $input->requestedPath,
                'scoped_path' => $input->scopedPath,
            ],
            'data' => $data,
        ]);
    }

    protected function failureResponse(string $tool, Throwable $exception): string
    {
        $code = 'internal_error';
        $message = 'An unexpected error occurred while running the tool.';

        if ($exception instanceof InvalidInputException) {
            $code = 'invalid_input';
            $message = $exception->getMessage();
        } elseif ($exception instanceof ProcessingException) {
            $code = $exception->errorCode;
            $message = $exception->getMessage();
        }

        return $this->toJson([
            'ok' => false,
            'tool' => $tool,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    protected function firePdf(): FirePdf
    {
        if ($this->firePdf instanceof FirePdf) {
            return $this->firePdf;
        }

        if (! function_exists('app')) {
            throw new InvalidInputException('Laravel application container is not available.');
        }

        /** @var mixed $service */
        $service = app(FirePdf::class);

        if (! $service instanceof FirePdf) {
            throw new InvalidInputException('Unable to resolve FirePdf from the application container.');
        }

        return $service;
    }

    private function inputResolver(): PdfToolInputResolver
    {
        if ($this->inputResolver instanceof PdfToolInputResolver) {
            return $this->inputResolver;
        }

        return new PdfToolInputResolver($this->firePdf());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toJson(array $payload): string
    {
        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            return '{"ok":false,"error":{"code":"json_encoding_failed","message":"Unable to encode tool response."}}';
        }
    }
}
