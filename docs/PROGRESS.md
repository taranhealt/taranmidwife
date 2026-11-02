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
