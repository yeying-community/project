#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "${SKIP_DEPENDENCY_CHECK:-0}" != "1" ]]; then
  "$root_dir/scripts/ubuntu-deps.sh" --check
fi

if ! command -v php >/dev/null 2>&1; then
  echo "PHP 8.4 is not installed. Run: sudo $root_dir/scripts/ubuntu-deps.sh --install" >&2
  exit 1
fi
command -v composer >/dev/null 2>&1 || { echo "Composer is required. Run ubuntu-deps.sh --install first." >&2; exit 1; }
php -m | grep -qi '^swoole$' || { echo "PHP Swoole extension is required. Run ubuntu-deps.sh --install first." >&2; exit 1; }

cd "$root_dir"
[[ -f .env ]] || cp .env.template .env
mkdir -p bootstrap/cache storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan dootask:ensure-admin
php artisan config:clear
php artisan route:clear
php artisan view:clear

chmod -R ug+rwX bootstrap/cache storage
echo "YeYing installation complete. Configure .env, then run scripts/starter.sh start."
