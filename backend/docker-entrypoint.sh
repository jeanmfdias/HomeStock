#!/bin/sh
set -e

mkdir -p /app/var/data /app/var/cache /app/var/log

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /app/var
fi

exec docker-php-entrypoint "$@"
