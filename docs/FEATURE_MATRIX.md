# TaranMidwife — Feature Matrix

**Branch audited:** `infra/android-ci-hardening`  
**Audit baseline:** repository HEAD `1d7a5b267775c52009e539af8cf7c1dc314d0152` (before this audit's follow-up commits).  
**Scope:** source and automated tests available in this branch. No staging URL, credentials, emulator, or physical-device session was available; therefore no end-to-end claim is made.

Status meanings:
- **Selesai + teruji**: implementation exists and an automated test/build directly exercises it.
- **Belum teruji**: implementation is present, but no adequate end-to-end or automated evidence was found.
- **Scaffold**: partial UI/data structure exists; workflow or integration is incomplete.
- **Belum ada**: no implementation evidence found in the audited source.
- A green CI build proves compilation/analyze/tests only for the tested commit, not production readiness.

## Backend (Laravel API)

| Area | Status | Evidence in source/tests | Remaining verification |
|---|---|---|---|
| Sanctum authentication, registration/login/logout | Selesai + teruji (CI/local tests) | `AuthController`, `AuthAccessTest`, `LoginUniformityTest` | Exercise against deployed staging |
| Public service/area catalog | Selesai + teruji | `CatalogController`, API routes | Contract test from both Flutter apps |
| Patient booking create/list/detail/cancel and state workflow | Selesai + teruji (backend tests) | `BookingController`, `BookingFlowTest`, `OfferDispatchTest` | Cross-app staging workflow |
| Partner booking accept/decline/start/complete | Selesai + teruji (backend tests) | `MidwifeBookingController`, `OfferAcceptTest`, `PartnerOfferDeadlineTest` | Staging and concurrent-device QA |
| Partner profile and verification submission | Selesai + teruji (backend tests) | `MidwifeProfileController`, `PartnerVerificationTest` | Verify UI field mapping and document lifecycle |
| Partner offerings, areas, hours and service point | Selesai + teruji (backend tests) | `PartnerOfferingsController`, `PartnerApiHardeningTest` | Staging API parity |
| Private partner document upload/admin download | Selesai + teruji (backend tests) | local private disk, MIME/size validation, `AdminPartnerController`, `PartnerApiHardeningTest` | Reverse-proxy/storage permissions and authorized admin device test |
| Partner ownership / offerings isolation | Selesai + teruji (backend tests) | ownership derived from authenticated user; isolation assertions in `PartnerApiHardeningTest` | Expand adversarial IDOR matrix across all resources |
| Complaint create/list/detail/admin workflow | Selesai + teruji | `ComplaintController`, `ComplaintTest` | Clinical escalation policy and production retention verification |
| Account deletion and anonymization | Selesai + teruji, with follow-up regression test added in this branch | `AccountController`, `AccountDeletionTest` | Legal/privacy review and verify deployed public wording |
| Password reset API | Selesai + teruji (synchronous mail path) | `PasswordResetController`, `PasswordResetTest` | Real mail provider and mobile UI |
| Rate limiting | Selesai + teruji for configured API limits | `routes/api.php`, named partner limiters and tests | Verify proxy/IP behavior in deployment |
| SATUSEHAT integration | Belum ada bukti implementasi | No verified integration client, credentials/config, mapping or integration tests identified | Separate approved integration project and test environment |
| ANC / PNC clinical workflows | Belum ada bukti implementasi | No complete clinical workflow or tests established by this audit | Clinical requirements, workflows, validation and clinical review |
| Electronic clinical medical record | Belum ada bukti implementasi | No verified longitudinal clinical record module and tests | Data model, access policy, audit trail, retention and clinical validation |

## Patient App (Flutter)

| Feature | Status | Evidence | Remaining verification |
|---|---|---|---|
| App bootstrap and API base URL guard | Selesai + teruji at unit/config level | `lib/config.dart`, shared core validator and CI | Install and test with real staging URL |
| Authentication screens and API repository calls | Belum teruji end-to-end | `lib/pages/auth_page.dart` and shared API client | Validate request/response against backend |
| Service catalogue | Belum teruji end-to-end | `lib/pages/services_tab.dart` | Empty/error/loading and API mapping |
| Booking form and submission | Belum teruji end-to-end | `lib/pages/booking_form_page.dart` | Backend contract, idempotency, booking status |
| Orders/history/cancellation | Belum teruji end-to-end | `lib/pages/orders_tab.dart` | Status transitions, ownership, expired token |
| Profile/account deletion | Belum teruji end-to-end | `lib/pages/profile_tab.dart`, API client | Confirm password and deletion behavior |
| Complaint UI | Scaffold / not established as complete | No confirmed dedicated complaint flow in inspected patient page inventory | Implement and connect only after confirming existing widgets/routes |
| Patient GPS | Deliberately not implemented | No location permission; policy default `TARAN_REQUIRE_GPS=false` | Keep location optional/off unless policy changes |
| Reset password in mobile app | Belum ada evidence | Backend exists; no verified mobile reset screens in inspected page inventory | Implement forgot/reset UI and deep-link/token handling |

## Partner / Midwife App (Flutter)

| Feature | Status | Evidence | Remaining verification |
|---|---|---|---|
| App bootstrap/API client | Belum teruji end-to-end | `lib/main.dart`, `lib/data/midwife_repository.dart` | Staging login and unauthorized handling |
| Login and role guard | Belum teruji end-to-end | Partner pages and repository | Validate roles and inactive/suspended response |
| Dashboard and bookings | Belum teruji end-to-end | Partner pages/repository | Live API state and refresh |
| Accept/decline/start/complete | Backend tested; app integration belum teruji | Partner repository action methods and backend workflow tests | Cross-user/cross-device staging tests |
| Profile and verification form | Belum teruji end-to-end | Typed models and repository | Field mapping and validation |
| Document upload | Belum teruji end-to-end | Shared upload support and partner repository | Real upload, size/type rejection, private storage |
| Offerings/services/areas/availability/service point | Backend tested; app integration belum teruji | `PartnerOfferingsController`, partner repository | Round-trip staging contract and duplicate weekday/hour cases |
| In-app password reset | Belum ada evidence | No verified mobile reset flow | Implement |

## CI and release

| Item | Status | Evidence | Notes |
|---|---|---|---|
| Shared Dart core analyze/test | Selesai + teruji on baseline CI run | Flutter CI run #53 | Re-run required after current commits |
| Patient app analyze/test/debug APK | Selesai + teruji on baseline CI run | Flutter CI run #53 | No physical-device test implied |
| Partner app analyze/test/debug APK | Selesai + teruji on baseline CI run | Flutter CI run #53 | No physical-device test implied |
| Unsigned QA AAB for both apps | Selesai + teruji on baseline CI run | Flutter CI run #53 artifacts | Not signed; not Play Store ready |
| Backend automated test suite | Selesai + teruji on baseline CI run | Backend CI run #95 | Re-run required after current commits |
| Signed Play AAB | Belum tersedia | Signed job intentionally gated; no signing secrets | Requires owner-managed keystore and production URL |
| Staging integration | Belum teruji / menunggu | No verified staging URL or configured variable available in audit | Do not claim connected |
| Physical device QA | Belum teruji | No device test evidence | Required before release |

## Security audit coverage

| Control | Status from source/test evidence | Caveat |
|---|---|---|
| Authentication/role middleware | Selesai + teruji | Review all newly added routes on every change |
| Partner data ownership | Selesai + teruji for key partner endpoints | Extend object-level authorization tests to all endpoints |
| Document upload validation/private disk | Selesai + teruji in backend tests | Validate production storage and download headers |
| Complaint reporter isolation | Selesai + teruji | Deleted reporter text redaction regression added |
| API rate limits | Selesai + teruji for named limiters | Proxy trust configuration must be checked at deployment |
| Android cleartext/backup protections | Configured in manifests; CI build verifies compile only | Inspect final merged manifest and test on device |
| Secure token storage | Implemented in app dependencies/code; no device-level penetration test | Test uninstall/backup/rooted-device threat assumptions |
| SATUSEHAT, ANC/PNC, clinical records | Not implemented/verified | Explicitly not release claims |

## Audit conclusion

The repository has a substantial backend and two Flutter app codebases, but the product is **not yet proven end-to-end**. The next hard blocker is a real HTTPS staging deployment and a controlled synthetic-data test pass on physical Android devices. This matrix must be updated only when new source and test evidence exists.
