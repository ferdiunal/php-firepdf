<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

final class ExtractTextTool extends AbstractFirePdfTool implements Tool
{
    public function description(): string
    {
        return 'Extract raw text from a storage-scoped PDF path.';
    }

    public function handle(Request $request): string
    {
        try {
            $input = $this->resolvePdfInput($request);
            $result = $this->firePdf()->extractTextBytes($input->bytes);

            return $this->successResponse('extract_text', $input, $result);
        } catch (Throwable $e) {
            return $this->failureResponse('extract_text', $e);
        }
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->pathSchema($schema);
    }
}
