<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

final class ExtractPagesMarkdownTool extends AbstractFirePdfTool implements Tool
{
    public function description(): string
    {
        return 'Extract per-page markdown output from a storage-scoped PDF path.';
    }

    public function handle(Request $request): string
    {
        try {
            $input = $this->resolvePdfInput($request);
            $pages = $this->extractPages($request);
            $result = $this->firePdf()->extractPagesMarkdownBytes($input->bytes, $pages);

            return $this->successResponse('extract_pages_markdown', $input, $result);
        } catch (Throwable $e) {
            return $this->failureResponse('extract_pages_markdown', $e);
        }
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->pathAndPagesSchema($schema);
    }
}
