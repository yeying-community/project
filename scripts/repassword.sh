#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
  sed -n '1,24p' "$root_dir/docker/mysql/repassword.sh"
  exit 0
fi

[[ -f "$root_dir/.env" ]] || { echo "缺少 $root_dir/.env，请先配置数据库连接。" >&2; exit 1; }
env_value() {
  local key="$1"
  sed -n "s/^${key}=//p" "$root_dir/.env" | head -n 1 | sed -e 's/^['\''"]//' -e 's/['\''"]$//'
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

if ! command -v mysql >/dev/null 2>&1; then
  command -v docker >/dev/null 2>&1 || {
    echo "需要 mysql 客户端或 Docker。Ubuntu 可执行 scripts/ubuntu-deps.sh --install，或安装 Docker。" >&2
    exit 1
  }

  mysql_container="${MYSQL_CONTAINER:-}"
  if [[ -z "$mysql_container" ]]; then
    for candidate in project-local-mysql mysql "project-mysql-${APP_ID:-}"; do
      if [[ -n "$candidate" ]] && docker inspect "$candidate" >/dev/null 2>&1; then
        mysql_container="$candidate"
        break
      fi
    done
  fi
  [[ -n "$mysql_container" ]] || {
    echo "未找到 MySQL 客户端，也未找到运行中的 MySQL 容器。可设置 MYSQL_CONTAINER 指定容器名称。" >&2
    exit 1
  }
  export MYSQL_CONTAINER="$mysql_container"
  export MYSQL_HOST=127.0.0.1
  export MYSQL_PORT=3306
  echo "未找到 mysql 客户端，改用 Docker 容器: $MYSQL_CONTAINER" >&2
fi

exec sh "$root_dir/docker/mysql/repassword.sh" "$@"
