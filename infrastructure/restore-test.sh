#!/usr/bin/env bash
# Uji pemulihan ke database sementara (dihapus otomatis). Butuh hak CREATEDB. Variabel: PGHOST PGUSER PGPASSWORD
set -euo pipefail
DUMP="${1:?pakai: restore-test.sh <file.dump>}"
: "${PGHOST:?}" "${PGUSER:?}" "${PGPASSWORD:?}"
TMPDB="restoretest_$(date +%s)"
createdb "$TMPDB"
trap 'dropdb --if-exists "$TMPDB" >/dev/null 2>&1 || true' EXIT
pg_restore --no-owner --exit-on-error -d "$TMPDB" "$DUMP"
echo "tabel_publik=$(psql -d "$TMPDB" -Atc "select count(*) from information_schema.tables where table_schema='public'")"
echo "users=$(psql -d "$TMPDB" -Atc 'select count(*) from users')"
echo "RESTORE OK (database uji dihapus)"
