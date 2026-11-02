#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ -f .env ] || { echo "Missing root .env; copy .env.docker.example and configure it." >&2; exit 1; }
set -a; . ./.env; set +a
: "${DB_DATABASE:?DB_DATABASE required}"
: "${DB_USERNAME:?DB_USERNAME required}"
: "${BACKUP_GPG_RECIPIENT:?BACKUP_GPG_RECIPIENT must be configured}"
command -v gpg >/dev/null 2>&1 || { echo "Install GnuPG on the backup host." >&2; exit 1; }
mkdir -p backups; chmod 700 backups
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT HUP INT TERM
docker compose exec -T db pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > "$tmp"
gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "backups/db-$stamp.dump.gpg" "$tmp"
docker compose exec -T app tar -czf - -C /var/www/html/storage/app private | gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "backups/private-files-$stamp.tar.gz.gpg"
sha256sum "backups/db-$stamp.dump.gpg" "backups/private-files-$stamp.tar.gz.gpg" > "backups/$stamp.sha256"
chmod 600 backups/*
find backups -type f -mtime +14 -delete
echo "Encrypted backup created: $stamp (copy off-host; retain GPG private key separately)."
