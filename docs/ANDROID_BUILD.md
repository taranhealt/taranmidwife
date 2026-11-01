# Android build and release

## Current CI behavior

The `TaranMidwife Flutter CI` workflow builds both Android apps with a **pinned** toolchain: Flutter `3.47.9` (stable) and Java 17. The pin lives in one place, `.github/actions/setup-flutter/action.yml`, and every job uses it, so re-runs are reproducible. To upgrade Flutter, change that one value in its own pull request and confirm all jobs stay green.

| Job | What it proves | Artifact |
|---|---|---|
| Core package | `flutter analyze` and `flutter test` for `packages/taran_core` | none |
| Patient Android app | analyze, test, debug APK | `taranmidwife-patient-debug-apk` |
| Midwife partner Android app | analyze, test, debug APK | `taranmidwife-mitra-debug-apk` |
| Release Android bundle compile (unsigned) | release AAB compiles | `taranmidwife-patient-release-unsigned-qa` |
| Midwife partner release bundle compile (unsigned) | release AAB compiles | `taranmidwife-mitra-release-unsigned-qa` |
| Signed Google Play AAB | signed AAB for each app (skipped until enabled) | `taranmidwife-*-release-aab` |

A newer push to the same branch cancels the older run. Every job has a timeout so a hung build cannot run for hours.

Artifacts are downloadable from the run page in GitHub Actions (also from a phone browser). Unsigned QA bundles are **not** Play Store artifacts.

A staging build is compiled against the repository variable `TARAN_STAGING_API_BASE_URL`. The workflow intentionally does not build against a production-looking default endpoint.

### Plugin `compileSdk` guard

Some Flutter plugins ship an Android library that still compiles against an older SDK, which makes the build fail with an AAR metadata error. Both apps raise any plugin library's `compileSdk` to at least 36 in `android/build.gradle.kts` using AGP's `finalizeDsl` hook. Do not replace it with `afterEvaluate` (too late: "It is too late to set compileSdk") or with `plugins.withId { compileSdk = ... }` (overwritten by the plugin's own `android {}` block).

## Configure staging

In GitHub, open the repository settings:

1. Select **Settings**.
2. Open **Secrets and variables** > **Actions**.
3. Under **Variables**, add `TARAN_STAGING_API_BASE_URL`.
4. Set its value to the HTTPS base URL of the staging API, including the API version path when required (for example, `https://staging.example.com/api/v1`).

Both apps reject a missing, non-HTTPS, credential-bearing, or query/fragment-bearing API base URL at startup. Supply the URL at build time with `--dart-define=API_BASE_URL=https://...`. Never put API credentials or tokens in this URL.

Do not use real patient data in staging until privacy, access control, tenant isolation, audit logging, backup, and security controls have been verified. Use synthetic test data.

## Android release signing

The release build must use a private upload keystore. Never commit a keystore, passwords, or `key.properties` to Git.

The Android Gradle configuration reads these environment variables:

- `ANDROID_KEYSTORE_PATH`
- `ANDROID_KEYSTORE_PASSWORD`
- `ANDROID_KEY_ALIAS`
- `ANDROID_KEY_PASSWORD`

The release signing configuration is applied only when a keystore path is supplied. A release build without signing credentials is not a Play Store-ready artifact.

For GitHub Actions, store credentials as repository or environment **Secrets**, not Variables:

- `ANDROID_KEYSTORE_BASE64` (base64-encoded keystore file)
- `ANDROID_KEYSTORE_PASSWORD`
- `ANDROID_KEY_ALIAS`
- `ANDROID_KEY_PASSWORD`

Before enabling a signed AAB workflow, add a dedicated workflow step that decodes `ANDROID_KEYSTORE_BASE64` into a temporary file under `$RUNNER_TEMP`, sets `ANDROID_KEYSTORE_PATH` in `GITHUB_ENV`, and removes the temporary keystore after the build. Do not print secret values in logs.

## Release checklist

- Confirm the final application IDs: `com.taranrainmakergroup.taranmidwife` (patient) and `com.taranrainmakergroup.taranmidwifemitra` (partner).
- Confirm the privacy policy and account-deletion URLs are publicly reachable and accurate.
- Configure and securely back up the upload keystore.
- Increment the app version in `apps/patient_app/pubspec.yaml` and `apps/partner_app/pubspec.yaml` for each release.
- Verify the signed AAB and test it through Play Console internal testing.
- Complete Google Play Data safety and app-access declarations accurately.
- Do not describe the app as SATUSEHAT-integrated until the integration has been implemented, tested, and authorized.

## Bootstrap workflow safety

The manual Android bootstrap workflow is verification-only. It does not generate files, stage changes, commit, or push to the default branch.

### CI runner and action versions

The Android CI and verification workflows use `ubuntu-24.04`. Java is provisioned with `actions/setup-java@v5` (Temurin 17); checkout uses `actions/checkout@v5`; uploaded build artifacts use `actions/upload-artifact@v5`. These major versions use the Node 24 action runtime rather than the retired Node 20 runtime. Keep the Flutter SDK pin in the shared composite action. The feature branch is validated through the open pull request; avoid duplicate push and pull-request workflow triggers for the same branch.
