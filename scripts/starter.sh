#!/usr/bin/env bash
set -euo pipefail

command="${1:-start}"
root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
pid_dir="${YEYING_RUN_DIR:-$root_dir/run}"
log_dir="${YEYING_LOG_DIR:-$root_dir/storage/logs}"
pid_file="$pid_dir/yeying.pid"
port="${LARAVELS_LISTEN_PORT:-2222}"

ensure_dirs() {
  mkdir -p "$pid_dir" "$log_dir" "$root_dir/bootstrap/cache" "$root_dir/storage/framework/cache"
  touch "$log_dir/starter.log"
}

is_running() {
  [[ -f "$pid_file" ]] || return 1
  local pid
  pid="$(cat "$pid_file")"
  [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null
}

start() {
  ensure_dirs
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
  echo "YeYing started (pid $(cat "$pid_file"), port $port)."
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
