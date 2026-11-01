# Android build and release

## Current CI behavior

The `TaranMidwife Flutter CI` workflow uses the latest available Flutter stable release and Java 17. It checks the shared Dart package and patient app by running dependency resolution, static analysis, and tests.

A staging debug APK is built only when the repository variable `TARAN_STAGING_API_BASE_URL` is configured. The workflow intentionally does not build an APK against a production-looking default endpoint.

## Configure staging

In GitHub, open the repository settings:

1. Select **Settings**.
2. Open **Secrets and variables** > **Actions**.
3. Under **Variables**, add `TARAN_STAGING_API_BASE_URL`.
4. Set its value to the HTTPS base URL of the staging API, including the API version path when required (for example, `https://staging.example.com/api/v1`).

The patient app rejects a missing, non-HTTPS, credential-bearing, or query/fragment-bearing API base URL at startup. Supply the URL at build time with `--dart-define=API_BASE_URL=https://...`. Never put API credentials or tokens in this URL.

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

- Confirm the final application ID: `com.taranrainmakergroup.taranmidwife`.
- Confirm the privacy policy and account-deletion URLs are publicly reachable and accurate.
- Configure and securely back up the upload keystore.
- Increment the app version in `apps/patient_app/pubspec.yaml` for each release.
- Verify the signed AAB and test it through Play Console internal testing.
- Complete Google Play Data safety and app-access declarations accurately.
- Do not describe the app as SATUSEHAT-integrated until the integration has been implemented, tested, and authorized.

## Bootstrap workflow safety

The manual Android bootstrap workflow is verification-only. It does not generate files, stage changes, commit, or push to the default branch.
