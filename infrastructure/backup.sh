#!/usr/bin/env bash
# Backup PostgreSQL + dokumen privat. Variabel: PGHOST PGUSER PGDATABASE PGPASSWORD APP_DIR [BACKUP_DIR] [RETENTION_DAYS]
set -euo pipefail
: "${PGHOST:?}" "${PGUSER:?}" "${PGDATABASE:?}" "${PGPASSWORD:?}" "${APP_DIR:?}"
OUT="${BACKUP_DIR:-$HOME/taranmidwife-backups}"
mkdir -p "$OUT"; chmod 700 "$OUT"
TS=$(date -u +%Y%m%dT%H%M%SZ)
pg_dump -Fc -f "$OUT/db-$TS.dump"
if [ -d "$APP_DIR/storage/app/private" ]; then
  tar -C "$APP_DIR/storage/app/private" -czf "$OUT/files-$TS.tgz" .
fi
chmod 600 "$OUT"/*-"$TS".* 2>/dev/null || true
find "$OUT" -name 'db-*.dump' -mtime +"${RETENTION_DAYS:-14}" -delete
find "$OUT" -name 'files-*.tgz' -mtime +"${RETENTION_DAYS:-14}" -delete
echo "BACKUP OK $TS -> $OUT"
