# Google Play Release Readiness

**Current decision: NO-GO for production publication.**

This document distinguishes the existence of application source code
from verified release readiness.

## Verified repository state

| Item | Current status | Evidence / notes |
|---|---|---|
| Patient Flutter source | PRESENT | `apps/patient_app/` |
| Android project structure | PRESENT | Android Gradle project exists |
| Android application ID | CONFIGURED | `com.taranrainmakergroup.taranmidwife` |
| Shared Dart package | PRESENT | `packages/taran_core/` |
| Debug APK build | PREVIOUSLY BUILT | GitHub Actions previously reported successful debug build |
| Debug artifact upload | CONFIGURED LOCALLY | Must be verified in a new GitHub Actions run after workflow push |
| Signed production AAB | NOT VERIFIED | No verified signed release artifact |
| Production upload key | NOT CONFIGURED | Must be generated and securely controlled by the company |
| Release signing | NOT READY | Current Gradle release configuration uses debug signing |
| Privacy policy public URL | NOT VERIFIED | Backend page exists; public deployment and legal review remain |
| Account deletion | PARTIAL | Backend API tested locally; application workflow requires verification |
| Data Safety declaration | DRAFT | Backend inventory exists; application-side inventory is incomplete |
| Store screenshots and graphics | NOT VERIFIED | Must be captured from the actual application |
| Play Console organization verification | OWNER TASK | Must be checked in Play Console |
| Closed testing requirements | OWNER TASK | Must be checked against current Play Console requirements |
| Legal and clinical review | NOT COMPLETE | No compliance claim is made |

## Android signing requirements

The current Android Gradle release configuration uses the debug
signing configuration. This must not be used as the production
publishing configuration.

Before release:

1. Generate a company-controlled upload keystore.
2. Store keystore credentials outside the repository.
3. Configure Gradle release signing through protected local properties
   or GitHub Actions secrets.
4. Never upload the keystore or passwords to GitHub.
5. Build a release Android App Bundle.
6. Verify the signature and package identity.
7. Retain secure backups of the upload key.

## Application verification

Before publication, verify on actual devices:

- Registration and authentication.
- Password recovery.
- Service browsing.
- Booking creation and cancellation.
- Order history.
- Profile and account deletion.
- API error handling.
- Network interruption and retry behavior.
- Privacy disclosures and permissions.
- Accessibility and user-facing content.

## Data Safety and privacy

The backend data inventory is a technical draft, not a final Google
Play declaration.

The Flutter application, its dependencies, and any future analytics,
crash reporting, or third-party SDKs must also be reviewed.

Privacy policy wording, retention, legal basis, and healthcare data
handling require appropriate legal review.

## Release decision

Production publication remains NO-GO until signing, end-to-end testing,
privacy and Data Safety review, store assets, and Play Console checks
have been completed and approved by the project owner.
