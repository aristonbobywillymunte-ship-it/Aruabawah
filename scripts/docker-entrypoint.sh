#!/bin/sh
set -eu

TIMEZONE="${APP_TIMEZONE:-${TZ:-UTC}}"
PHP_TZ_INI="/usr/local/etc/php/conf.d/zz-timezone.ini"

printf 'date.timezone="%s"\n' "$TIMEZONE" > "$PHP_TZ_INI"

exec "$@"
