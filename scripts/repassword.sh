#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
  sed -n '1,24p' "$root_dir/docker/mysql/repassword.sh"
  exit 0
fi

[[ -f "$root_dir/.env" ]] || { echo "缺少 $root_dir/.env，请先配置数据库连接。" >&2; exit 1; }
command -v mysql >/dev/null 2>&1 || {
  echo "需要 mysql 客户端。Ubuntu 执行 scripts/ubuntu-deps.sh --install。" >&2
  exit 1
}

env_value() {
  local key="$1"
  sed -n "s/^${key}=\(['\"]\?\)\(.*\)\1$/\2/p" "$root_dir/.env" | head -n 1
}

export MYSQL_HOST="$(env_value DB_HOST)"
export MYSQL_PORT="$(env_value DB_PORT)"
export MYSQL_USER="$(env_value DB_USERNAME)"
export MYSQL_PASSWORD="$(env_value DB_PASSWORD)"
export MYSQL_DATABASE="$(env_value DB_DATABASE)"
export MYSQL_PREFIX="$(env_value DB_PREFIX)"

: "${MYSQL_HOST:?DB_HOST 未配置}"
: "${MYSQL_PORT:?DB_PORT 未配置}"
: "${MYSQL_USER:?DB_USERNAME 未配置}"
: "${MYSQL_DATABASE:?DB_DATABASE 未配置}"
: "${MYSQL_PREFIX:=}"

exec sh "$root_dir/docker/mysql/repassword.sh" "$@"
