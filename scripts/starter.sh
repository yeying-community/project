#!/usr/bin/env bash
set -euo pipefail

command="${1:-start}"
root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
pid_dir="${YEYING_RUN_DIR:-$root_dir/run}"
log_dir="${YEYING_LOG_DIR:-$root_dir/storage/logs}"
pid_file="$pid_dir/yeying.pid"
port="${LARAVELS_LISTEN_PORT:-2222}"
health_url="${YEYING_HEALTH_URL:-http://127.0.0.1:${port}/}"

ensure_dirs() {
  mkdir -p "$pid_dir" "$log_dir" "$root_dir/bootstrap/cache" "$root_dir/storage/framework/cache"
  touch "$log_dir/starter.log"
}

check_runtime() {
  command -v curl >/dev/null 2>&1 || { echo "curl is required for health checks." >&2; exit 1; }
  if ! command -v php >/dev/null 2>&1; then
    cat >&2 <<EOF
PHP 8.4 is not installed. Run these commands first:
  sudo "$root_dir/scripts/ubuntu-deps.sh" --install
  "$root_dir/scripts/install.sh"
EOF
    exit 1
  fi
  if ! php -m | grep -qi '^swoole$'; then
    cat >&2 <<EOF
PHP Swoole extension is not installed. Run:
  sudo "$root_dir/scripts/ubuntu-deps.sh" --install
EOF
    exit 1
  fi
  [[ -f "$root_dir/vendor/autoload.php" ]] || { echo "Missing vendor/autoload.php. Run scripts/install.sh first." >&2; exit 1; }
  php -r 'exit((int) !extension_loaded("swoole"));' || { echo "PHP Swoole extension is required." >&2; exit 1; }
}

is_running() {
  [[ -f "$pid_file" ]] || return 1
  local pid
  pid="$(cat "$pid_file")"
  [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null
}

start() {
  ensure_dirs
  check_runtime
  if is_running; then
    echo "YeYing already running (pid $(cat "$pid_file"), port $port)."
    return 0
  fi
  if [[ ! -f "$root_dir/.env" ]]; then
    echo "Missing $root_dir/.env. Copy .env.example and configure the database first." >&2
    exit 1
  fi
  cd "$root_dir"
  nohup php "$root_dir/bin/laravels" start >>"$log_dir/starter.log" 2>&1 &
  echo $! > "$pid_file"
  for _ in {1..30}; do
    if curl -fsS "$health_url" >/dev/null 2>&1; then
      echo "YeYing started (pid $(cat "$pid_file"), port $port)."
      return 0
    fi
    if ! is_running; then
      echo "YeYing failed to start. See $log_dir/starter.log." >&2
      rm -f "$pid_file"
      exit 1
    fi
    sleep 1
  done
  echo "YeYing did not become healthy at $health_url. See $log_dir/starter.log." >&2
  exit 1
}

stop() {
  if ! is_running; then
    rm -f "$pid_file"
    echo "YeYing is not running."
    return 0
  fi
  local pid
  pid="$(cat "$pid_file")"
  kill "$pid"
  for _ in {1..50}; do
    kill -0 "$pid" 2>/dev/null || break
    sleep 0.2
  done
  kill -9 "$pid" 2>/dev/null || true
  rm -f "$pid_file"
  echo "YeYing stopped."
}

case "$command" in
  start) start ;;
  stop) stop ;;
  restart) stop; start ;;
  status) if is_running; then echo "YeYing is running (pid $(cat "$pid_file"), port $port)."; else echo "YeYing is stopped."; fi ;;
  *) echo "Usage: $(basename "$0") {start|stop|restart|status}" >&2; exit 1 ;;
esac
