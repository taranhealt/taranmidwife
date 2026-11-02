#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -f .env ]] || { echo "Missing root .env; copy .env.docker.example and configure it." >&2; exit 1; }
set -a
# shellcheck disable=SC1091
source ./.env
set +a
: "${DB_DATABASE:?DB_DATABASE required}"
: "${DB_USERNAME:?DB_USERNAME required}"
: "${BACKUP_GPG_RECIPIENT:?BACKUP_GPG_RECIPIENT must be configured}"
command -v gpg >/dev/null 2>&1 || { echo "Install GnuPG on the backup host." >&2; exit 1; }
mkdir -p backups
chmod 700 backups
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
tmp_db="$(mktemp)"
tmp_files="$(mktemp)"
trap 'rm -f "$tmp_db" "$tmp_files"' EXIT HUP INT TERM
chmod 600 "$tmp_db" "$tmp_files"
# Separate streams into protected temporary files; pipefail is intentionally enabled.
docker compose exec -T db pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > "$tmp_db"
docker compose exec -T app tar -czf - -C /var/www/html/storage/app private > "$tmp_files"
gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "backups/db-$stamp.dump.gpg" "$tmp_db"
gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "backups/private-files-$stamp.tar.gz.gpg" "$tmp_files"
sha256sum "backups/db-$stamp.dump.gpg" "backups/private-files-$stamp.tar.gz.gpg" > "backups/$stamp.sha256"
chmod 600 backups/*
find backups -type f -mtime +"${BACKUP_RETENTION_DAYS:-14}" -delete
echo "Encrypted backup created: $stamp (copy off-host; retain GPG private key separately)."
