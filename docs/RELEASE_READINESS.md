# Release Readiness — Preparation Only

No release authorized. Unsigned QA AAB is not a Play Store release.

## Keystore in Termux
Run locally; protect passwords and key material; keep two encrypted offline backups. Never commit/share them.

    pkg update && pkg install openjdk-17
    mkdir -p ~/private/taranmidwife-signing
    cd ~/private/taranmidwife-signing
    umask 077
    keytool -genkeypair -v -keystore taranmidwife-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias taranmidwife-upload
    sha256sum taranmidwife-upload.jks
    base64 -w 0 taranmidwife-upload.jks > taranmidwife-upload.jks.base64

Base64 contains private key material; protect it as the keystore.

## GitHub secrets via Android browser
Repository Settings -> Secrets and variables -> Actions. Add ANDROID_KEYSTORE_BASE64, ANDROID_KEYSTORE_PASSWORD, ANDROID_KEY_ALIAS and ANDROID_KEY_PASSWORD as Secrets. Add TARAN_PRODUCTION_API_BASE_URL only after approved HTTPS deployment. Keep TARAN_ENABLE_SIGNED_AAB disabled. Never paste secrets in chat, issues, logs or Git.

## Data Safety draft
Inspect final merged manifest and SDK behavior. Patient App policy: no GPS permission. Partner App uploads professional documents. Account deletion redacts complaint free text to [DIHAPUS], anonymizes selected fields and revokes tokens; metadata can remain. No payment gateway, clinical records, ANC/PNC or SATUSEHAT claim. Verify actual processors, analytics/crash libraries and permissions before Play Console declarations.

## Play Console
Internal testing -> resolve install/policy issues -> closed testing -> production only after explicit owner approval and staged rollout. Keep previous artifact and migration rollback plan. Incident: contain, revoke tokens, rotate secrets, preserve sanitized evidence, assess impacted data and legal notification obligations, restore only from verified encrypted backup.
