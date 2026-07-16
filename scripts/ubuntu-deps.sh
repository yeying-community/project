#!/usr/bin/env bash
set -euo pipefail

mode="${1:---check}"
php_version="${PHP_VERSION:-8.4}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
info() { printf '%s\n' "$*"; }

[[ "$mode" == "--check" || "$mode" == "--install" ]] || {
  echo "Usage: $(basename "$0") [--check|--install]" >&2
  exit 1
}

[[ "$(id -u)" -eq 0 || "$mode" == "--check" ]] || fail "--install 需要 root 或 sudo 权限。"
if ! command -v lsb_release >/dev/null 2>&1 && [[ ! -f /etc/os-release ]]; then
  if [[ "$mode" == "--check" ]]; then
    info "当前环境没有 Ubuntu 系统信息；该脚本仅在 Ubuntu 上执行安装，跳过本机检查。"
    exit 0
  fi
  fail "无法识别操作系统。"
fi

if [[ -f /etc/os-release ]]; then
  . /etc/os-release
  if [[ "${ID:-}" != "ubuntu" ]]; then
    if [[ "$mode" == "--check" ]]; then
      info "当前系统为 ${ID:-unknown}；该脚本仅在 Ubuntu 上执行安装，跳过本机检查。"
      exit 0
    fi
    fail "仅支持 Ubuntu，当前系统为 ${ID:-unknown}。"
  fi
fi

check_command() {
  local command_name="$1"
  command -v "$command_name" >/dev/null 2>&1 && info "OK  command: $command_name" || info "MISS command: $command_name"
}

check_php_extension() {
  local extension="$1"
  php -m 2>/dev/null | awk '{print tolower($0)}' | grep -qx "${extension,,}" \
    && info "OK  php extension: $extension" \
    || info "MISS php extension: $extension"
}

check() {
  info "YeYing Ubuntu dependency check"
  info "OS: ${PRETTY_NAME:-Ubuntu}"
  check_command php
  if command -v php >/dev/null 2>&1; then
    info "PHP: $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
    for extension in curl dom ffi fileinfo gd gmp imagick json ldap libxml openssl simplexml swoole zip; do
      check_php_extension "$extension"
    done
  fi
  check_command composer
  check_command curl
  check_command mysql
  check_command redis-cli
  check_command npm
  info "检查结束。--check 不会修改系统。"
}

install() {
  export DEBIAN_FRONTEND=noninteractive
  command -v apt-get >/dev/null 2>&1 || fail "未找到 apt-get。"

  info "更新 APT 索引..."
  apt-get update
  apt-get install -y software-properties-common ca-certificates lsb-release \
    curl git unzip rsync build-essential pkg-config libssl-dev libcurl4-openssl-dev \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libmagickwand-dev \
    libpcre2-dev libonig-dev libxml2-dev

  if ! apt-cache policy "php${php_version}" | grep -q Candidate; then
    info "添加 Ondrej PHP PPA..."
    apt-get install -y software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt-get update
  fi

  info "安装 PHP ${php_version} 和扩展..."
  apt-get install -y \
    "php${php_version}" "php${php_version}-cli" "php${php_version}-dev" \
    "php${php_version}-common" "php${php_version}-curl" "php${php_version}-dom" \
    "php${php_version}-ffi" "php${php_version}-gd" "php${php_version}-imagick" \
    "php${php_version}-gmp" "php${php_version}-ldap" \
    "php${php_version}-mbstring" "php${php_version}-mysql" "php${php_version}-redis" \
    "php${php_version}-xml" "php${php_version}-zip"

  apt-get install -y mysql-client redis-tools

  update-alternatives --set php "/usr/bin/php${php_version}" 2>/dev/null || true
  update-alternatives --set phpize "/usr/bin/phpize${php_version}" 2>/dev/null || true
  update-alternatives --set php-config "/usr/bin/php-config${php_version}" 2>/dev/null || true

  if ! command -v composer >/dev/null 2>&1; then
    info "安装 Composer..."
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi

  if ! php -m | awk '{print tolower($0)}' | grep -qx swoole; then
    info "通过 PECL 安装 Swoole..."
    apt-get install -y "php-pear"
    pecl channel-update pecl.php.net || true
    printf '\n' | pecl install swoole
    echo "extension=swoole.so" > "/etc/php/${php_version}/cli/conf.d/99-swoole.ini"
  fi

  info "依赖安装完成。请执行 --check 复核。"
}

if [[ "$mode" == "--check" ]]; then
  check
else
  install
  check
fi
