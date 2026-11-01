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

- Code SHA: `f9bcafccf7248c807a9d08997137fe291a841c3c`.
- Flutter CI #81 / run ID `41362379396`: SUCCESS.
  - Core package: SUCCESS.
  - Patient Android app: SUCCESS (analyze/test SUCCESS; staging APK build/upload SKIPPED because staging URL unset).
  - Midwife partner Android app: SUCCESS (analyze/test SUCCESS; staging APK build/upload SKIPPED because staging URL unset).
  - Patient unsigned QA AAB compile + upload: SUCCESS.
  - Partner unsigned QA AAB compile + upload: SUCCESS.
  - Signed Google Play AAB: SKIPPED by design.
- Backend CI #124 / run ID `41362379382`: SUCCESS; test job SUCCESS.
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
