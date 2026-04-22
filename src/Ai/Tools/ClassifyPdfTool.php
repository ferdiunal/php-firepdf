<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

final class ClassifyPdfTool extends AbstractFirePdfTool implements Tool
{
    public function description(): string
    {
        return 'Classify a PDF document for OCR needs using a storage-scoped PDF path.';
    }

    public function handle(Request $request): string
    {
        try {
            $input = $this->resolvePdfInput($request);
            $result = $this->firePdf()->classifyPdfBytes($input->bytes);

            return $this->successResponse('classify_pdf', $input, $result);
        } catch (Throwable $e) {
            return $this->failureResponse('classify_pdf', $e);
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->pathSchema($schema);
    }
}
