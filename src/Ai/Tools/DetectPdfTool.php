<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

final class DetectPdfTool extends AbstractFirePdfTool implements Tool
{
    public function description(): string
    {
        return 'Detect the PDF type and base metadata for a storage-scoped PDF path.';
    }

    public function handle(Request $request): string
    {
        try {
            $input = $this->resolvePdfInput($request);
            $result = $this->firePdf()->detectPdfBytes($input->bytes);

            return $this->successResponse('detect_pdf', $input, $result);
        } catch (Throwable $e) {
            return $this->failureResponse('detect_pdf', $e);
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
