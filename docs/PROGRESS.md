# Autonomous Progress

Branch: `infra/android-ci-hardening`; base: `main`; PR #1 remains OPEN/DRAFT. Main has not been modified or merged.

## Verified source/code changes

- Retained Android AGP `finalizeDsl` compileSdk guard.
- CI uses `ubuntu-24.04`, `actions/setup-java@v5`, checkout/upload-artifact v5; shared Flutter setup caches Gradle.
- Dependabot weekly update configuration covers GitHub Actions, Composer and Dart package directories.
- Added booking-create throttle: 10/minute per authenticated user (IP fallback), with regression test.
- Account deletion redacts reporter free-text complaint to `[DIHAPUS]`; public copy aligned.
- Added authorization matrix, clinical-record plan, QA checklist, security review, release readiness, staging/operations runbooks, known issues and GO/NO-GO.
- Flutter CI skips staging APK build and upload when `TARAN_STAGING_API_BASE_URL` is empty; unsigned QA AAB compile remains a compile-only check.
- CI adds artifact size/SHA-256 run-summary entries for AABs and staging APKs when generated.

## Latest exact code-head verification

- Code SHA: `c3ddb6ff72997d991a2dcc4e225fe2b99b5c26ff`.
- Flutter CI #85 / run ID `41363527847`: SUCCESS.
  - Core package: SUCCESS.
  - Patient Android app: SUCCESS (analyze/test SUCCESS; staging APK build/upload SKIPPED because staging URL unset).
  - Midwife partner Android app: SUCCESS (analyze/test SUCCESS; staging APK build/upload SKIPPED because staging URL unset).
  - Patient unsigned QA AAB compile + upload: SUCCESS.
  - Partner unsigned QA AAB compile + upload: SUCCESS.
  - Signed Google Play AAB: SKIPPED by design.
- Backend CI #128 / run ID `41363527786`: SUCCESS; test job SUCCESS.
- AAB artifacts: Patient `taranmidwife-patient-release-unsigned-qa`, 51,331,418 bytes; Partner `taranmidwife-mitra-release-unsigned-qa`, 51,194,150 bytes. Unsigned only.
- No debug APK artifacts exist in this run because no staging URL is configured.

## Remaining work / blockers

- No staging URL or staging app-to-backend E2E.
- No physical Android device QA.
- Temporary signed document URLs, admin metrics UI, reschedule/cancellation policy, scheduled offer worker verification, manual payment ledger, search/favorites/address book/reviews, reminders/education and FCM/inbox remain incomplete.
- Docker/health/boot validation, backup/restore, monitoring, strict CORS/CSP, composer/pub audit gates, R8/mapping/version metadata remain incomplete.
- Reset-password timing side-channel remains unresolved; synchronous email is retained per owner default.
- Signed AAB, Data Safety/legal review, monitoring and owner release approval remain outstanding.

## Execution limitation

Repository work was performed through GitHub APIs. No local PHP/Flutter runtime, PostgreSQL instance, Android device, staging host, mail provider, or signing material was available. Missing capabilities are not marked complete.


## Additional backend hardening
- Added unauthenticated `GET /api/health` readiness endpoint. It checks database connectivity and returns only `{"status":"ok"}` or `{"status":"unavailable"}`; no exception/configuration details are returned.
- Added `HealthEndpointTest` for healthy response and sanitized 503 response.
- Backend CI #128 passed on code SHA `30da6557ea6ecd1ed898d7040162c56570d13caa`.


## Admin dashboard metrics increment
- Added role-protected `GET /api/v1/admin/metrics` for ops_admin, reviewer and sysadmin.
- Returns only aggregate counts: bookings total/open, active partners and open complaints.
- Added responsive admin dashboard metric cards; no patient contact/address/complaint content is returned by this endpoint.
- Added regression test for role denial and PII-free aggregate output.
- Backend CI #132 / 41364515569: SUCCESS.
- Flutter CI #89 / 41364515586: SUCCESS; staging APK steps skipped because staging URL remains unset; both unsigned AABs compiled and uploaded.


## Admin complaint console and temporary document URLs
- Admin web console complaint tab now loads/filter complaints and supports start review, resolve with mandatory note, close and reopen. Backend transition/audit/privacy coverage remains in ComplaintTest; markup and JavaScript syntax are checked in Backend CI.
- Admin partner detail now issues 5-minute Laravel temporary signed document URLs. Download still requires authenticated reviewer/sysadmin role, signed URL validation and private local storage; access is audited and responses use no-store.
- PartnerVerificationTest covers signed URL success, expired signature rejection, tampered signature rejection, role denial and audit logging.
- First backend run after signed route change exposed one legacy test using the now-protected unsigned URL (1 failed, 132 passed). Updated the regression test to use the issued signed URL; latest Backend CI #137 passes.
- Latest exact code SHA `a5e5f4eaf93948422e478013c3e53824cb7a28ac`: Flutter CI #94 (`41366219716`) and Backend CI #137 (`41366219710`) both SUCCESS. Unsigned QA AABs uploaded; no staging APK because staging URL unset.


## Admin duplicate-action protection
- The admin web console's shared button helper now disables buttons while asynchronous actions are pending and restores them after success/failure.
- Complaint review/resolve/close/reopen callbacks return their request promises, so repeat taps cannot submit the same action concurrently from the same page.
- Added AdminUiSupportTest source assertion; JavaScript syntax check remains in backend CI.
- Backend CI #139 / `41367194962`: SUCCESS. Flutter CI #96 / `41367194904`: SUCCESS. Exact code SHA `c3ddb6ff72997d991a2dcc4e225fe2b99b5c26ff`.


## Manual payment ledger and patient visibility
- Added PostgreSQL migration for booking payment status and append-only payment event history.
- Added API to read payment history for booking owner / assigned partner / authorized operations roles, and ops_admin/sysadmin-only write endpoint.
- State policy: initial UNPAID; PAID only after COMPLETED; CANCELLED only after booking CANCELLED; status is final through ordinary workflow. Methods are CASH, BANK_TRANSFER, OTHER. No payment gateway, no free-text payment notes.
- Every status change records actor/time/method and an audit event; database row is locked during mutation to prevent concurrent duplicate writes.
- Patient App booking detail loads status/history; booking list displays payment status. Admin console offers payment recording to ops_admin/sysadmin.
- Tests: BookingPaymentTest (4 tests) and AdminUiSupportTest UI hook assertions.
- Backend CI #147 / run `41374239305`: SUCCESS, 139 tests / 837 assertions.
- Flutter CI #104 / run `41374239341`: SUCCESS; Patient/Partner/Core and both unsigned QA AABs SUCCESS; signed AAB skipped; staging APK skipped because URL unset.
- Exact code SHA `49d5f09cd0d1b827a8481eb57add0fafdf41ce13`.
- Remaining: partner-side payment display, real browser/device verification and full staging flow.


## Partner App payment status visibility
- PartnerBooking now parses `payment_status`, defaulting to UNPAID for older/missing payloads.
- Partner booking cards display Indonesian payment status. Detailed payment history remains visible in Patient App and admin API; partner-side history list is not implemented.
- Added regression test for PAID and safe UNPAID default.
- Backend CI #151 / `41375405844`: SUCCESS.
- Flutter CI #108 / `41375405895`: SUCCESS; core/patient/partner and unsigned QA AABs SUCCESS; signed AAB skipped; staging APK skipped because URL unset.
- Exact code SHA `9d86e8b296be091f2f387483216d2883b52b4407`.


## Docker deployment scaffold — exact HEAD
- Added multi-stage `backend/Dockerfile` (Composer dependencies, Vite assets, PHP 8.4 FPM + Nginx runtime), `compose.yaml` (PostgreSQL, app, queue worker, scheduler, Nginx and Caddy automatic HTTPS), fail-fast production environment checks and sanitized `/api/health` endpoint.
- Backend CI #152 / run `41376664500` on exact HEAD `9a32032c848e5af743aa11051c1016ffe72e3333`: SUCCESS. It validates `docker compose config`, builds production image, checks admin JS syntax and runs PostgreSQL-backed Laravel tests.
- Flutter CI #109 / run `41376664527` on same SHA: SUCCESS. Core, Patient, Partner and both unsigned QA AAB jobs SUCCESS; signed AAB SKIPPED. Patient AAB 51,346,135 bytes; Partner AAB 51,196,189 bytes.
- Encrypted GPG backup and isolated restore-test scripts added. They are templates only: no VPS, GPG key, daily cron, off-host storage or real restore drill configured.
- Remaining: deploy on owner-controlled VPS, set real env/secrets outside Git, configure DNS/Caddy TLS, schedule backup, copy off-host, execute restore drill, smoke test and physical Android QA.


## Backup pipeline hardening (next exact code commit)
- Reworked `scripts/backup-db.sh` to Bash strict mode (`set -euo pipefail`), separate protected temporary database/private-file archives, encrypt both with GPG, checksum outputs and enforce retention.
- Added shell syntax checks to backend CI for backup/restore/entrypoint scripts; ensure private storage directory exists in Docker image.
- These changes require CI verification on the resulting exact commit; no real backup/restore claim until owner VPS drill.


## Booking review feature implementation (pending CI)
- Added a one-review-per-booking backend schema with patient/partner ownership, 1–5 rating constraint and encrypted optional comment.
- API permits only the owning patient to review a COMPLETED booking with an active partner assignment; duplicate submissions return 409. Audit event stores booking reference only.
- Public partner rating endpoint returns only aggregate average/count and never returns patient identity or review comment.
- Patient App completed-booking detail now loads review state and provides a one-time rating/comment form with loading/error/double-submit guards.
- Backend and Flutter CI must pass on the resulting exact SHA before this feature can be marked tested; partner public profile display and device QA remain outstanding.


## Flutter analyzer cleanup after review UI increment
- Replaced deprecated `DropdownButtonFormField.value` with `initialValue` in Patient App booking and complaint forms.
- Fixed Patient App review widget override annotations and escaped path interpolation.
- Replaced string concatenation and added braces in coverage result state update to clear analyzer lints.
- CI on exact HEAD will determine verified status; do not mark review complete before backend tests and Flutter analyze/test/AAB jobs pass.


## Booking review feature — exact-head CI evidence
- Exact code SHA `b5395d1d802a2b9aa6426518dc84a36a35129f19`.
- Backend CI #159 / `41378854691`: SUCCESS, 142 tests / 852 assertions. Review tests cover completed booking, patient ownership, one-review constraint, rating validation, audit event and anonymous partner aggregate (no patient ID/comment).
- Flutter CI #116 / `41378854684`: SUCCESS. Core, Patient, Partner and both unsigned QA AAB compile/upload jobs SUCCESS; signed AAB SKIPPED. Patient unsigned AAB 51,367,060 bytes; Partner 51,196,189 bytes.
- Patient App shows review form only for COMPLETED bookings; comments are encrypted at rest. Public rating API returns average/count only; partner profile UI is not yet integrated.
