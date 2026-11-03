#!/usr/bin/env bash
# Runs a command with a code coverage driver loaded for this run only.
# Usage: scripts/with-php-test-coverage-driver.sh vendor/bin/pest --parallel --coverage --min=100
set -euo pipefail

if php -m | grep -qiE '^(xdebug|pcov)$'; then
    exec "$@"
fi

arch=$(uname -m)
version=$(php -r 'echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;')
extension_dir=$(php -r 'echo ini_get("extension_dir");')
driver=""

for candidate in \
    "/Applications/Herd.app/Contents/Resources/xdebug/xdebug-${version}-${arch}.so" \
    "${extension_dir}/xdebug.so"; do
    if [ -f "$candidate" ]; then
        driver=$candidate
        break
    fi
done

if [ -z "$driver" ]; then
    echo "No Xdebug or pcov build found for PHP ${version} (${arch}). Install one, then run again." >&2
    exit 1
fi

dir=$(mktemp -d)
trap 'rm -rf "$dir"' EXIT
printf 'zend_extension=%s\nxdebug.mode=coverage\nxdebug.start_with_request=no\n' "$driver" > "$dir/php.ini"

PHPRC=$dir "$@"
