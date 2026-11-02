# Staging Deployment Guide

Synthetic data only. No staging host is verified.

Minimum: Ubuntu LTS, PHP 8.3+, Nginx, PostgreSQL 16+, TLS, persistent private storage, scheduler and mail sandbox. PostgreSQL private only; firewall SSH/80/443; non-root deploy account. Configure trusted proxy before relying on client-IP rate limits.

Required env: unique APP_KEY, APP_DEBUG=false, HTTPS APP_URL, PostgreSQL credentials, private filesystem, TARAN_REQUIRE_GPS=false. Secrets stay in host env, never Git.

Docker/Compose, health endpoint and boot env validator now exist and are build/config-validated by Backend CI #152 on SHA `9a32032c848e5af743aa11051c1016ffe72e3333`. No VPS deployment or smoke test has been performed. Encrypted backup/restore scripts are templates; no daily schedule or successful restore drill is evidenced.

Deploy from the repository root after reviewing `.env.docker.example`: copy it to `.env`, set a unique APP_KEY, strong DB_PASSWORD, APP_DOMAIN, TLS_EMAIL and GPG recipient; keep `.env` out of Git. Configure DNS/TLS/firewall; start with `docker compose up -d --build`; verify `docker compose ps`, `docker compose logs --tail=100 app web caddy`, and `curl -fsS https://YOUR_DOMAIN/api/health`. Run migrations with `docker compose exec app php artisan migrate --force` only after backup. Configure daily encrypted backup and test restore before using non-synthetic data. Provision DNS/TLS/firewall; create least-privilege DB; deploy exact reviewed SHA; configure env outside web root; run config cache and migrations (never migrate:fresh on shared staging); run persistent scheduler for offer/SIP expiry; set GitHub Actions variable TARAN_STAGING_API_BASE_URL to owned HTTPS API; install APKs on physical devices; execute QA_CHECKLIST; verify private docs, CORS, rate limits, logs and backup restore before non-synthetic data.

Rollback only to compatible code/schema; otherwise forward-fix. Keep prior SHA and backup.
