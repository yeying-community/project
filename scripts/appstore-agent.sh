#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$root_dir/.env"
[[ "${1:-}" == "--dry-run" && $# -eq 1 ]] || { echo "Usage: $(basename "$0") --dry-run" >&2; exit 2; }
[[ -f "$env_file" ]] || { echo "Missing $env_file" >&2; exit 1; }

env_value() { sed -n "s/^$1=//p" "$env_file" | head -n 1 | sed -e 's/^['\''"]//' -e 's/['\''"]$//'; }
appstore_url="$(env_value APPSTORE_INTERNAL_URL)"; instance_id="$(env_value APPSTORE_INSTANCE_ID)"
agent_id="$(env_value APPSTORE_AGENT_ID)"; agent_token="$(env_value APPSTORE_AGENT_TOKEN)"
[[ -n "$appstore_url" && -n "$instance_id" && -n "$agent_id" && -n "$agent_token" ]] || { echo "Missing AppStore Agent configuration." >&2; exit 1; }
appstore_url="${appstore_url%/}"

json_path() { php -r '$v=json_decode(stream_get_contents(STDIN),true);foreach(explode(".",$argv[1]) as $k){if(!is_array($v)||!array_key_exists($k,$v))exit(1);$v=$v[$k];}if(is_array($v))echo json_encode($v,JSON_UNESCAPED_SLASHES);elseif($v!==null)echo $v;' "$1"; }
request() {
  local method="$1" path="$2" body="${3:-}"
  local args=(-fsS -X "$method" "$appstore_url$path" -H "X-YeYing-Instance: $instance_id" -H "X-YeYing-Agent: $agent_id" -H "Authorization: Bearer $agent_token" -H "Accept: application/json")
  [[ -n "$body" ]] && args+=(-H "Content-Type: application/json" --data "$body")
  curl "${args[@]}"
}
verify_release() {
  php -r '
    $p=json_decode(file_get_contents($argv[1]),true);if(($p["code"]??0)!==200||!is_array($p["data"]??null))exit(1);$d=$p["data"];if(($d["release_digest"]??"")!==$argv[2]||!is_array($d["files"]??null))exit(1);$f=$d["files"];
    foreach(["application.json","runtime.json","config.schema.json","permissions.json","compose.yaml","checksums.json","signature.json"] as $r)if(!array_key_exists($r,$f))exit(1);
    $c=json_decode($f["checksums.json"],true);if(!is_array($c))exit(1);foreach($c as $path=>$sum){if(str_contains($path,"..")||str_starts_with($path,"/")||!isset($f[$path])||!is_string($sum)||!hash_equals($sum,hash("sha256",$f[$path])))exit(1);}
    $r=json_decode($f["runtime.json"],true);if(!is_array($r)||!preg_match("/^[^\\s]+@sha256:[a-f0-9]{64}$/",(string)($r["image"]??"")))exit(1);echo json_encode($r["dependencies"]??[],JSON_UNESCAPED_SLASHES);
  ' "$1" "$2"
}
tcp_check() { php -r '$s=@fsockopen($argv[1],(int)$argv[2],$e,$m,3);if(!$s)exit(1);fclose($s);' "$2" "$3" || { echo "Dependency unavailable: $1 ($2:$3)" >&2; return 1; }; echo "Dependency ready: $1 ($2:$3)"; }

task_response="$(request POST /api/v1/runtime/tasks/claim)"; task_id="$(printf '%s' "$task_response" | json_path data.task_id || true)"
[[ -n "$task_id" ]] || { echo "No pending AppStore runtime task."; exit 0; }
revision="$(printf '%s' "$task_response" | json_path data.revision)"; app_id="$(printf '%s' "$task_response" | json_path data.app_id)"
version="$(printf '%s' "$task_response" | json_path data.target_version)"; release_digest="$(printf '%s' "$task_response" | json_path data.release_digest)"; claimed=1
return_task() { [[ "${claimed:-0}" == 1 ]] || return 0; request POST "/api/v1/runtime/tasks/$task_id/release" "{\"revision\":$revision}" >/dev/null || true; claimed=0; }
release_file="$(mktemp)"; trap 'rm -f "$release_file"; return_task' EXIT
request GET "/api/v1/runtime/releases/$app_id/$version" >"$release_file"
dependencies="$(verify_release "$release_file" "$release_digest")" || { echo "Release verification failed for $app_id@$version." >&2; exit 1; }

db_host="$(env_value DB_HOST)"; db_port="$(env_value DB_PORT)"; redis_host="$(env_value REDIS_HOST)"; redis_port="$(env_value REDIS_PORT)"; search_host="$(env_value SEARCH_HOST)"; search_port="$(env_value SEARCH_PORT)"
app_url="$(env_value APP_URL)"; project_host="$(printf '%s' "$app_url" | sed -E 's#^[a-z]+://##;s#/.*$##;s/:.*$//')"; project_port="$(printf '%s' "$app_url" | sed -nE 's#^[a-z]+://[^/:]+:([0-9]+).*#\1#p')"; project_port="${project_port:-$(env_value LARAVELS_LISTEN_PORT)}"
while IFS= read -r capability; do case "$capability" in mysql) tcp_check mysql "$db_host" "${db_port:-3306}";; redis) tcp_check redis "$redis_host" "${redis_port:-6379}";; manticore) tcp_check manticore "$search_host" "${search_port:-9306}";; project-api) tcp_check project-api "$project_host" "${project_port:-2222}";; object-storage) echo "Dependency declared: object-storage (not probed in dry-run)";; *) echo "Unsupported declared dependency: $capability" >&2; exit 1;; esac; done < <(printf '%s' "$dependencies" | php -r '$a=json_decode(stream_get_contents(STDIN),true);foreach((array)$a as $i)if(!empty($i["required"]))echo $i["capability"].PHP_EOL;')
echo "Dry-run passed for $app_id@$version ($release_digest). Task returned to pending; no container or configuration was changed."
