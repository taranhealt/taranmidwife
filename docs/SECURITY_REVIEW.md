# Security Review

Source review at cc161bc12ce8824d0f1abeb68dbf0cf4fcb7945e. No deployed penetration test, mobile reverse engineering, infra scan, load test or legal audit was performed.

| Control | Evidence observed | Residual risk |
|---|---|---|
| Sanctum and role middleware | API routes and auth tests | Staging behavior unknown |
| Patient booking ownership | patient_id scope and cross-account test | Expand all IDOR tests |
| Partner ownership | active assignment and offerings isolation test | Historical access policy open |
| Booking transitions | BookingWorkflow and row locks | Concurrency test against PostgreSQL |
| Idempotency | Idempotency-Key and DB uniqueness | Simultaneous retry test |
| Contact minimization | BookingPresenter status-aware output | Test every response variant |
| Upload | MIME/extension allow-list, 5 MB, random key, private disk | Malware scanning absent; host storage unverified |
| Document read | Reviewer/sysadmin route, audit, no-store | Direct authenticated download, not signed URL |
| Complaint | encrypted cast; deletion redacts free text | Backup retention/legal schedule open |
| Rate limits | auth/reset/complaint/account/partner limits; booking limiter added here | Verify proxy/IP behavior on staging |
| Token | reset/deletion revokes Sanctum tokens | Test all future password-change flows |
| Android | HTTPS URL validation, cleartext/backup off | Final manifest/device check absent |
| Headers | nosniff, frame deny, referrer, permissions, HSTS production | CSP and CORS allowlist not verified |
| Password reset | generic body; sync mail only for active account | Timing can differ; do not claim equal timing |
| Logs | audit events record action/object/actor | Review framework/proxy logs for PII |

## Priority risks
P0 no verified staging, TLS/proxy/CORS/storage/scheduler/backup unknown. P0 reset timing side-channel remains. P1 verify booking rate limit through reverse proxy, admin web CSRF/token storage, private file backups and malware scanning. P1 backup/restore and dependency scanning. P2 monitoring/alerting, legal review and privacy retention. No production readiness claim; no SATUSEHAT, ANC/PNC, clinical records or payment gateway claim.
