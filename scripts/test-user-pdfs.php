<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ferdiunal\FirePdf\FirePdf;

/**
 * @param  string  $name
 */
function slugify(string $name): string
{
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'file';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'file';
}

$projectRoot = dirname(__DIR__);
$outputDir = $projectRoot . '/build/markdown-output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$pdfPaths = [
    $projectRoot . '/SaaS Chatbot Yapılandırma ve Maliyet Analizi.pdf',
    $projectRoot . '/o_henry_oykuleri_turkce.pdf',
];

$firePdf = new FirePdf();
$summary = [];

foreach ($pdfPaths as $pdfPath) {
    $name = basename($pdfPath);
    $slug = slugify(pathinfo($name, PATHINFO_FILENAME));
    $outputPath = $outputDir . '/' . $slug . '.md';

    $exists = is_file($pdfPath);
    $size = $exists ? (int) filesize($pdfPath) : 0;

    $header = "# PDF Test Report\n\n";
    $header .= "- Source: `{$pdfPath}`\n";
    $header .= "- Exists: `" . ($exists ? 'yes' : 'no') . "`\n";
    $header .= "- Size: `{$size}` bytes\n\n";

    if (!$exists || $size === 0) {
        $body = "## Result\n\n";
        $body .= "Input PDF is missing or empty. Markdown extraction cannot run on this file.\n";
        file_put_contents($outputPath, $header . $body);

        $summary[] = [
            'file' => $name,
            'status' => 'skipped-empty',
            'output' => $outputPath,
        ];
        continue;
    }

    try {
        $result = $firePdf->processPdf($pdfPath);
        $markdown = $result->markdown;

        if (!is_string($markdown) || trim($markdown) === '') {
            $pages = $firePdf->extractPagesMarkdown($pdfPath);
            $chunks = [];
            foreach ($pages->pages as $page) {
                $chunks[] = "## Page {$page->page}\n\n" . $page->markdown;
            }
            $markdown = trim(implode("\n\n", $chunks));
        }

        $body = "## Result\n\n";
        $body .= "Extraction succeeded.\n\n";
        $body .= "## Markdown\n\n";
        $body .= ($markdown !== '' ? $markdown : "_No markdown content produced._") . "\n";
        file_put_contents($outputPath, $header . $body);

        $summary[] = [
            'file' => $name,
            'status' => 'ok',
            'output' => $outputPath,
        ];
    } catch (\Throwable $e) {
        $body = "## Result\n\n";
        $body .= "Extraction failed.\n\n";
        $body .= "## Error\n\n";
        $body .= "- Type: `" . get_class($e) . "`\n";
        $body .= "- Message: `" . $e->getMessage() . "`\n";
        file_put_contents($outputPath, $header . $body);

        $summary[] = [
            'file' => $name,
            'status' => 'error',
            'output' => $outputPath,
        ];
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
