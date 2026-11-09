#!/usr/bin/env bash
# Runs a command with a code coverage driver loaded for this run only.
# Usage: scripts/with-php-test-coverage-driver.sh vendor/bin/pest --parallel --coverage --min=100
set -euo pipefail

# Read once: under pipefail, `php -m | grep -q` fails when grep stops reading early.
modules=$(php -m 2>/dev/null)

if grep -qiE '^pcov$' <<< "$modules"; then
    exec "$@"
fi

# A loaded Xdebug collects coverage only in its coverage mode, which its ini may not set.
if grep -qiE '^xdebug$' <<< "$modules"; then
    XDEBUG_MODE=coverage exec "$@"
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
