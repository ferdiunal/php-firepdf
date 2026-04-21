#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

OS_NAME="$(uname -s)"
ARCH_NAME="$(uname -m)"

case "${OS_NAME}" in
  Darwin)
    OS_SLUG="darwin"
    LIB_NAME="libpdf_inspector_ffi.dylib"
    ;;
  Linux)
    OS_SLUG="linux"
    LIB_NAME="libpdf_inspector_ffi.so"
    ;;
  MINGW*|MSYS*|CYGWIN*|Windows_NT)
    OS_SLUG="windows"
    LIB_NAME="pdf_inspector_ffi.dll"
    ;;
  *)
    echo "Unsupported OS: ${OS_NAME}" >&2
    exit 1
    ;;
esac

case "${ARCH_NAME}" in
  x86_64|amd64)
    ARCH_SLUG="x86_64"
    ;;
  aarch64|arm64)
    ARCH_SLUG="arm64"
    ;;
  *)
    echo "Unsupported architecture: ${ARCH_NAME}" >&2
    exit 1
    ;;
esac

SOURCE_PATH="${ROOT_DIR}/native/pdf-inspector-ffi/target/release/${LIB_NAME}"
DEST_DIR="${ROOT_DIR}/native/lib/${OS_SLUG}-${ARCH_SLUG}"
DEST_PATH="${DEST_DIR}/${LIB_NAME}"

if [[ ! -f "${SOURCE_PATH}" ]]; then
  echo "Native library not found: ${SOURCE_PATH}" >&2
  echo "Build first: cargo build --release --locked (inside native/pdf-inspector-ffi)" >&2
  exit 1
fi

mkdir -p "${DEST_DIR}"
cp "${SOURCE_PATH}" "${DEST_PATH}"

echo "Bundled native library:"
echo "  ${DEST_PATH}"
