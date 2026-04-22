<?php

// config for Ferdiunal/FirePdf
return [
    /*
    |--------------------------------------------------------------------------
    | Path to the pdf-inspector FFI shared library
    |--------------------------------------------------------------------------
    |
    | If null, the package will auto-resolve from:
    |   1) native/lib/<os>-<arch>/ (bundled binaries)
    |   2) native/pdf-inspector-ffi/target/release/ (dev fallback)
    |
    | You can always override with an absolute path.
    |
    */
    'lib_path' => $_ENV['FIREPDF_LIB_PATH'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | Runtime guard and telemetry options
    |--------------------------------------------------------------------------
    |
    | Useful for long-running workers (Swoole, FrankenPHP, RoadRunner).
    |
    | - telemetry: enable runtime metrics collection
    | - gc_collect_every: call gc_collect_cycles() every N operations (0 disables)
    | - soft_limit_mb: recommend worker recycle when current memory exceeds this
    | - hard_limit_mb: recommend worker recycle with hard reason when exceeded
    |
    */
    'runtime' => [
        'telemetry' => $_ENV['FIREPDF_RUNTIME_TELEMETRY'] ?? true,
        'gc_collect_every' => $_ENV['FIREPDF_RUNTIME_GC_COLLECT_EVERY'] ?? 0,
        'soft_limit_mb' => $_ENV['FIREPDF_RUNTIME_SOFT_LIMIT_MB'] ?? 0,
        'hard_limit_mb' => $_ENV['FIREPDF_RUNTIME_HARD_LIMIT_MB'] ?? 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI SDK Tool Storage Scope
    |--------------------------------------------------------------------------
    |
    | These options define the storage scope used by the AI SDK tool classes
    | located under Ferdiunal\FirePdf\Ai\Tools\*. Tool input paths are
    | always resolved relative to this base path on the configured disk.
    |
    */
    'ai_tools' => [
        'disk' => $_ENV['FIREPDF_AI_TOOLS_DISK'] ?? 'local',
        'base_path' => $_ENV['FIREPDF_AI_TOOLS_BASE_PATH'] ?? '',
    ],
];
