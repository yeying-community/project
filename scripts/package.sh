#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1
root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
project_name="${PROJECT_NAME:-project}"
output_dir="${OUTPUT_DIR:-$root_dir/output}"
version="${1:-$(node -p "require('$root_dir/package.json').version")}"
version="${version#v}"
revision="$(git -C "$root_dir" rev-parse --short=7 HEAD)"
stage_dir="$(mktemp -d "${TMPDIR:-/tmp}/${project_name}-package.XXXXXX")"
package_name="${project_name}-v${version}-${revision}"
package_dir="$stage_dir/$package_name"
archive_path="$output_dir/$package_name.tar.gz"

cleanup() { rm -rf "$stage_dir"; }
trap cleanup EXIT

command -v npm >/dev/null || { echo "Missing npm command in PATH" >&2; exit 1; }
command -v composer >/dev/null || { echo "Missing composer command in PATH" >&2; exit 1; }

cd "$root_dir"
echo "Installing frontend dependencies..."
npm install
echo "Building frontend static assets..."
npm run build
echo "Installing production PHP dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader

mkdir -p "$output_dir" "$package_dir"
rm -rf "$output_dir"/*

rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'node_modules' \
  --exclude 'output' \
  --exclude 'storage/logs/*' \
  --exclude '.env' \
  "$root_dir/" "$package_dir/"

chmod +x "$package_dir/scripts/ubuntu-deps.sh" "$package_dir/scripts/starter.sh" "$package_dir/scripts/install.sh"
tar -czf "$archive_path" -C "$stage_dir" "$package_name"
echo "Package created: $archive_path"
