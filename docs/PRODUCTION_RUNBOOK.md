# Operations Runbook — Template Only

No production infrastructure is verified or authorized.

Daily: HTTPS availability, disk, DB, scheduler heartbeat, backups and errors. Weekly: review admin/document access, auth abuse and dependency advisories. Monthly: restore encrypted backup into isolated environment and run smoke tests. Each deploy records SHA, migration output, smoke result and rollback reference.

Backup template: `scripts/backup-db.sh` creates GPG-encrypted PostgreSQL and private-file archives plus SHA-256 checksums; `scripts/restore-test.sh <encrypted-db-dump>` restores into an isolated temporary database and drops it on exit. Configure daily execution with cron (example: `17 2 * * * cd /opt/taranmidwife && ./scripts/backup-db.sh >> /var/log/taranmidwife-backup.log 2>&1`), copy encrypted artifacts off-host, and store the GPG private key separately offline. The private-file archive restore must also be rehearsed separately. These scripts are not evidence of a completed backup or restore; record date, SHA, counts and result after a real drill.

Incident: triage; contain affected account/endpoint; revoke tokens and rotate exposed secrets; block public objects; preserve minimal sanitized evidence; identify affected data/users/time/processors; notify legal/privacy lead to assess UU PDP duties; recover from verified clean state; monitor and write post-incident report.

Rollback: stop rollout on authorization/integrity regression. Use previous code only when schema compatibility is known. Prefer additive migrations/forward fixes. Verify auth, booking ownership, docs, health and scheduler after recovery.
