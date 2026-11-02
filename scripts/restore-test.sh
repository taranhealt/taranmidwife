#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ "$#" -eq 1 ] || { echo "Usage: scripts/restore-test.sh backups/db-<timestamp>.dump.gpg" >&2; exit 2; }
backup="$1"
[ -f "$backup" ] || { echo "Backup not found: $backup" >&2; exit 1; }
[ -f .env ] || { echo "Missing root .env." >&2; exit 1; }
set -a; . ./.env; set +a
: "${DB_USERNAME:?DB_USERNAME required}"
command -v gpg >/dev/null 2>&1 || { echo "Install GnuPG and import backup private key first." >&2; exit 1; }
test_db="restore_check_$(date -u +%s)"
cleanup() { docker compose exec -T db dropdb -U "$DB_USERNAME" --if-exists "$test_db" >/dev/null 2>&1 || true; }
trap cleanup EXIT HUP INT TERM
docker compose exec -T db createdb -U "$DB_USERNAME" "$test_db"
gpg --batch --decrypt "$backup" | docker compose exec -T db pg_restore -U "$DB_USERNAME" -d "$test_db" --no-owner --exit-on-error -
count="$(docker compose exec -T db psql -U "$DB_USERNAME" -d "$test_db" -tAc "SELECT count(*) FROM migrations")"
[ "${count:-0}" -gt 0 ] || { echo "Restore failed: migrations table is empty." >&2; exit 1; }
echo "Restore test passed: $test_db contains $count migration records. Temporary DB will be dropped."
