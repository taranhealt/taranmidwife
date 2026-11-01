# Operations Runbook — Template Only

No production infrastructure is verified or authorized.

Daily: HTTPS availability, disk, DB, scheduler heartbeat, backups and errors. Weekly: review admin/document access, auth abuse and dependency advisories. Monthly: restore encrypted backup into isolated environment and run smoke tests. Each deploy records SHA, migration output, smoke result and rollback reference.

Backup design: encrypted PostgreSQL dump with restrictive permissions and off-host storage; separately back up private documents with encryption/checksums; keep keys separate; record timestamp, DB version, commit, size and SHA-256; restore to isolated PostgreSQL/storage; verify migrations, counts and synthetic smoke tests. Do not call backup tested until restore succeeds.

Incident: triage; contain affected account/endpoint; revoke tokens and rotate exposed secrets; block public objects; preserve minimal sanitized evidence; identify affected data/users/time/processors; notify legal/privacy lead to assess UU PDP duties; recover from verified clean state; monitor and write post-incident report.

Rollback: stop rollout on authorization/integrity regression. Use previous code only when schema compatibility is known. Prefer additive migrations/forward fixes. Verify auth, booking ownership, docs, health and scheduler after recovery.
