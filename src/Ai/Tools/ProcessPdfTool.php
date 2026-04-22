<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

final class ProcessPdfTool extends AbstractFirePdfTool implements Tool
{
    public function description(): string
    {
        return 'Run full PDF processing (detect + extraction + markdown) for a storage-scoped PDF path.';
    }

    public function handle(Request $request): string
    {
        try {
            $input = $this->resolvePdfInput($request);
            $pages = $this->extractPages($request);
            $result = $this->firePdf()->processPdfBytes($input->bytes, $pages);

            return $this->successResponse('process_pdf', $input, $result);
        } catch (Throwable $e) {
            return $this->failureResponse('process_pdf', $e);
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
