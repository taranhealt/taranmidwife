# Staging Deployment Guide

Synthetic data only. No staging host is verified.

Minimum: Ubuntu LTS, PHP 8.3+, Nginx, PostgreSQL 16+, TLS, persistent private storage, scheduler and mail sandbox. PostgreSQL private only; firewall SSH/80/443; non-root deploy account. Configure trusted proxy before relying on client-IP rate limits.

Required env: unique APP_KEY, APP_DEBUG=false, HTTPS APP_URL, PostgreSQL credentials, private filesystem, TARAN_REQUIRE_GPS=false. Secrets stay in host env, never Git.

Not verified in current tree: Docker/compose, health endpoint, boot env validator, automated backup/restore or deployed smoke test. Do not assume these assets exist.

After infrastructure implementation: provision DNS/TLS/firewall; create least-privilege DB; deploy exact reviewed SHA; configure env outside web root; run config cache and migrations (never migrate:fresh on shared staging); run persistent scheduler for offer/SIP expiry; set GitHub Actions variable TARAN_STAGING_API_BASE_URL to owned HTTPS API; install APKs on physical devices; execute QA_CHECKLIST; verify private docs, CORS, rate limits, logs and backup restore before non-synthetic data.

Rollback only to compatible code/schema; otherwise forward-fix. Keep prior SHA and backup.
