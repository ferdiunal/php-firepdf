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
];
