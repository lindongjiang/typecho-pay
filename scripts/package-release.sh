#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
VERSION="${VERSION:-$(git -C "$ROOT_DIR" describe --tags --always --dirty 2>/dev/null || date +%Y%m%d%H%M%S)}"
DIST_DIR="${DIST_DIR:-$ROOT_DIR/dist}"
WORK_DIR="${DIST_DIR}/.release-work"

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

copy_package() {
  src="$1"
  dest="$2"
  shift 2
  filter_file="$WORK_DIR/exclude-$(basename "$dest").txt"

  {
    echo '.git'
    echo '.github'
    echo '.DS_Store'
    echo 'node_modules'
    echo 'tests'
    echo '*.log'
    echo '*.tmp'
    for pattern in "$@"; do
      echo "$pattern"
    done
  } > "$filter_file"

  mkdir -p "$dest"
  rsync -a --delete --exclude-from="$filter_file" "$src/" "$dest/"
}

require_command rsync
require_command zip

rm -rf "$WORK_DIR"
mkdir -p "$WORK_DIR" "$DIST_DIR"

copy_package "$ROOT_DIR/plugins/TypechoPay" "$WORK_DIR/TypechoPay" \
  'docs' \
  'temp_screenshot.png'

copy_package "$ROOT_DIR/themes/VOID" "$WORK_DIR/VOID" \
  '.vscode' \
  '.eslintrc.json' \
  '.gitattributes' \
  'package-lock.json' \
  'package.json' \
  'gulpfile.js'

(
  cd "$WORK_DIR"
  zip -qr "$DIST_DIR/TypechoPay-${VERSION}.zip" TypechoPay
  zip -qr "$DIST_DIR/VOID-cloudmantou-${VERSION}.zip" VOID
)

rm -rf "$WORK_DIR"

echo "$DIST_DIR/TypechoPay-${VERSION}.zip"
echo "$DIST_DIR/VOID-cloudmantou-${VERSION}.zip"
