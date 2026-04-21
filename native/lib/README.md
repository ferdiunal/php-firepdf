# Native Bundle Layout

Place prebuilt FFI libraries in this directory using:

`native/lib/<os>-<arch>/<library>`

Examples:

- `native/lib/linux-x86_64/libpdf_inspector_ffi.so`
- `native/lib/darwin-arm64/libpdf_inspector_ffi.dylib`
- `native/lib/windows-x86_64/pdf_inspector_ffi.dll`

`Inspector` resolves this bundled path first in production when `FIREPDF_LIB_PATH` is not set.

Use GitHub Actions workflow `native-bundles` to generate Win/macOS/Linux bundle artifacts.
