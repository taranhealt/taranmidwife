# Docker deployment (staging template)

This is an implementation template, not evidence of a deployed or production-tested service. Use synthetic data only until staging, restore, legal and physical-device checks are complete.

## Requirements
- Ubuntu 24.04 VPS, Docker Engine + Docker Compose plugin, DNS record for the API hostname.
- Ports 80/443 reachable; SSH restricted to trusted IPs.
- GnuPG on the VPS for encrypted backups and an imported public recipient key. Keep the private key off the VPS.
- At least 2 GB RAM for a small synthetic pilot; database and private files persist in Docker volumes.

## First setup (from repository root)
1. Clone the reviewed branch/SHA and enter the repository.
2. Copy .env.docker.example to .env.
3. Build image: docker compose build --pull.
4. Generate APP_KEY: docker compose run --rm --no-deps app php artisan key:generate --show. Copy the result into .env; do not publish it.
5. Set APP_URL to https://YOUR_API_DOMAIN, APP_DOMAIN to hostname only, a strong unique DB password and valid TLS email.
6. Create/import a GPG key pair. Put only its public fingerprint in BACKUP_GPG_RECIPIENT; store private key offline.
7. Start: docker compose up -d.
8. Check docker compose ps and docker compose logs --tail=100 app web caddy db.
9. Run migrations once: docker compose exec app php artisan migrate --force. Never run migrate:fresh on persistent data.
10. Verify curl -fsS https://YOUR_API_DOMAIN/api/health returns only {"status":"ok"}.
11. Configure GitHub Actions variable TARAN_STAGING_API_BASE_URL to the owned HTTPS API; rerun Flutter CI to produce staging debug APKs. Install on physical devices and complete docs/QA_CHECKLIST.md.

## Services
- db: PostgreSQL 16 on private Docker network, no host port.
- app: PHP-FPM Laravel API.
- web: Nginx FastCGI frontend.
- caddy: HTTPS reverse proxy and automatic certificate renewal.
- worker: database queue worker.
- scheduler: Laravel scheduler every minute.
- app-storage: persistent Laravel storage volume.

## Backup and restore
- Configure GPG public recipient, then run scripts/backup-db.sh.
- Copy encrypted backups/ files off-host. Never store the only copy on the VPS.
- Restore test: import GPG private key on secure host, copy encrypted DB dump there, then run scripts/restore-test.sh backups/db-TIMESTAMP.dump.gpg.
- Restore script creates a temporary database, restores dump, checks migration records and drops the temporary database. It does not restore private document files; verify file backup separately.
- Do not mark backup/restore passed until a real restore test has been run and recorded.

## Rollback
- Record deployed SHA and backup timestamp before each deployment.
- Prefer forward fixes for schema changes. Roll back application code only if migrations remain backward compatible.
- Never roll back by deleting persistent volumes. For document-storage compromise, restrict access, preserve evidence, rotate credentials and follow docs/PRODUCTION_RUNBOOK.md.
