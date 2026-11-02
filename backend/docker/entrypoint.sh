#!/bin/sh
set -eu
if [ "${APP_ENV:-production}" = "production" ]; then
  [ "${APP_DEBUG:-true}" = "false" ] || { echo "FATAL: APP_DEBUG must be false in production" >&2; exit 1; }
  [ -n "${APP_KEY:-}" ] || { echo "FATAL: APP_KEY is required" >&2; exit 1; }
  case "${APP_URL:-}" in https://*) ;; *) echo "FATAL: APP_URL must use HTTPS" >&2; exit 1 ;; esac
  [ "${DB_CONNECTION:-}" = "pgsql" ] || { echo "FATAL: DB_CONNECTION must be pgsql" >&2; exit 1; }
fi
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
if [ "${CACHE_CONFIG_ON_BOOT:-true}" = "true" ]; then php artisan config:cache; fi
exec "$@"
