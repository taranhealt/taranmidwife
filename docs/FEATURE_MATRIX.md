# TaranMidwife — Feature Matrix

**Branch:** `infra/android-ci-hardening`  
**Source audit SHA:** `49d5f09cd0d1b827a8481eb57add0fafdf41ce13`  
**Scope:** repository source and automated CI evidence. No staging URL, emulator, physical-device session, or production deployment was available. This matrix does not claim end-to-end readiness.

Status meanings:
- **Selesai + teruji:** source exists and automated tests/build exercise it.
- **Belum teruji:** implementation exists but adequate integration/device evidence is absent.
- **Scaffold:** partial UI or data structure; full workflow incomplete.
- **Belum ada:** no implementation evidence found.
- Green CI is not proof of deployed or production behavior.

## Backend — Laravel API

| Feature | Status | Evidence | Remaining |
|---|---|---|---|
| Sanctum register/login/logout | Selesai + teruji | AuthController, AuthAccessTest, LoginUniformityTest; Backend CI #132 | Deployed staging and mail-provider test |
| Non-sensitive database readiness endpoint `/api/health` | Selesai + teruji | `routes/api.php`, `HealthEndpointTest` (success and sanitized 503) | Verify behind deployed proxy |
| Service and area catalog | Selesai + teruji | CatalogController and API tests | Flutter contract tests against staging |
| Patient booking create/list/detail/cancel | Selesai + teruji | BookingController, BookingFlowTest | Cross-app E2E and concurrent-device QA |
| Booking state transitions | Selesai + teruji | BookingWorkflow, BookingFlowTest, OfferDispatchTest | Verify sync in two apps on staging |
| Partner accept/decline/start/complete | Selesai + teruji | MidwifeBookingController and workflow tests | Cross-app E2E |
| Partner profile/verification submission | Selesai + teruji | MidwifeProfileController, PartnerVerificationTest | Device/API round-trip |
| Partner offerings/areas/hours/service point | Selesai + teruji | PartnerOfferingsController, PartnerApiHardeningTest | Staging round-trip |
| Private partner document upload | Selesai + teruji | MIME/size validation and PartnerApiHardeningTest | Production storage permissions |
| Admin document download | Selesai + teruji | AdminPartnerController issues 5-minute temporary signed URLs; signed middleware, authenticated staff role, private storage and audit log; PartnerVerificationTest covers valid, expired, tampered URLs | Browser/mobile QA and deployed HTTPS proxy validation |
| Partner ownership/offerings isolation | Selesai + teruji for tested paths | authenticated-user ownership and isolation assertions | Expand IDOR coverage to every sensitive resource |
| Complaints create/list/admin workflow | Selesai + teruji at API level; admin web UI CI checked | AdminComplaintController/ComplaintWorkflow, ComplaintTest, admin.js and AdminUiSupportTest; status filter, review/resolve/reopen/close | Browser/device QA and legal retention review |
| Account deletion/anonymization | Selesai + teruji | AccountController, AccountDeletionTest; complaint free text redacted to [DIHAPUS] | Legal review and deployed wording check |
| Password reset API | Selesai + teruji (synchronous path) | PasswordResetController, PasswordResetTest | Real mail provider, timing-side-channel mitigation |
| Rate limiting | Selesai + teruji for configured paths | auth/complaint/partner/upload limiters; booking-create 10/min/user-IP regression test | Verify trusted proxy/IP behavior |
| SATUSEHAT | Belum ada | No verified integration client/mapping/test | Separate approved integration scope |
| ANC/PNC | Belum ada | No complete clinical workflow/test | Clinical requirements and validation |
| Clinical medical record | Belum ada | No longitudinal clinical module/test | See CLINICAL_RECORDS_PLAN.md |

## Patient App

| Feature | Status | Evidence | Remaining |
|---|---|---|---|
| App bootstrap/API URL guard | Selesai + teruji at unit/config level | config.dart, shared core tests, Flutter CI #89 | Real staging installation |
| Auth screens and API calls | Belum teruji end-to-end | auth_page.dart and shared API client | Staging request/response tests |
| Service catalogue | Belum teruji end-to-end | services_tab.dart | Loading/empty/error and response mapping |
| Booking form/submission | Belum teruji end-to-end | booking_form_page.dart | API contract, idempotency and status sync |
| Orders/history/cancellation | Belum teruji end-to-end | orders_tab.dart | Staging transitions and expired-token behavior |
| Profile/account deletion | Belum teruji end-to-end | profile_tab.dart | Password confirmation and deletion round-trip |
| Complaint form | Belum teruji end-to-end | orders_tab.dart POST /complaints | Widget/API test and staging ownership test |
| GPS | Deliberately not implemented | No location permission; TARAN_REQUIRE_GPS=false default | Keep no-GPS policy |
| Password reset | Scaffold / partial | Forgot-password request; completion through email web page | Verify mail/web reset end-to-end |

## Partner / Midwife App

| Feature | Status | Evidence | Remaining |
|---|---|---|---|
| Bootstrap/API client | Belum teruji end-to-end | main.dart, midwife_repository.dart | Staging auth and unauthorized handling |
| Login/role guard | Belum teruji end-to-end | Partner pages/repository | Verify inactive/suspended handling |
| Dashboard/bookings | Belum teruji end-to-end | Partner pages/repository | Live state refresh |
| Accept/decline/start/complete | Backend teruji; app belum teruji | Repository actions and backend workflow tests | Cross-app staging tests |
| Profile/verification | Belum teruji end-to-end | Typed models and repository | Field mapping and reason display |
| Document upload | Belum teruji end-to-end | Shared upload and partner repository | Real upload and rejection tests |
| Offerings/areas/availability/service point | Backend teruji; app belum teruji | PartnerOfferingsController and repository | Round-trip staging |
| In-app password reset | Belum ada evidence | No verified mobile completion flow | Implement/test |

## Requested product extensions

| Feature | Status | Evidence / reason |
|---|---|---|
| Admin verification API | Selesai + teruji at backend level | AdminPartnerController and PartnerVerificationTest |
| Admin mobile web dashboard metrics and complaint console | Implemented; backend/API and UI support tests pass, browser E2E belum teruji | AdminDashboardController, admin.js metrics cards, AdminDashboardMetricsTest (role guard + no PII) | Browser/device QA and visual accessibility check |
| Temporary signed document URLs | Selesai + teruji in backend | Signed URLs expire after 5 minutes; signature + staff auth required; tests reject expired/tampered URLs | Validate HTTPS proxy URL generation in staging |
| Partner status/reason UX | Backend available; Flutter belum teruji | API returns verification status/review reason; no device evidence |
| Reschedule/cancellation policy | Belum ada | No complete reschedule workflow/policy test |
| Automatic offer expiry | Backend command exists; scheduler belum teruji | Command/schedule exists; no deployed worker heartbeat |
| Manual payment ledger/history | Selesai + teruji API; Patient App + admin console UI CI-verified | BookingPaymentController, payment events, locked one-way status, role guards, audit events; BookingPaymentTest; Patient orders detail displays payment status/history; admin console records cash/bank/other or cancellation | Physical device/admin browser QA and deployed staging; Partner App display not yet added |
| Search/filter/rating/favorites/address book | Belum ada | No complete feature/API/test evidence |
| Local reminders/education content | Belum ada | No verified encrypted reminder/content API |
| FCM/device token/in-app inbox | Belum ada | No token lifecycle/inbox implementation evidence |
| Docker/health/boot validation | Belum ada | No verified deploy stack/health/boot tests |
| Backup/restore/monitoring | Belum ada | No restore drill or alert evidence |
| Dependency vulnerability automation | Scaffold / partial | Dependabot configured; audit/outdated gates not yet integrated |
| R8/minify/mapping upload/version metadata | Belum ada | Not configured/proven by current pipeline |

## CI and release evidence

| Item | Status | Evidence | Notes |
|---|---|---|---|
| Shared core analyze/test | Selesai + teruji | Flutter CI #81, run 41364515586, SHA f9bcafc | Core job SUCCESS |
| Patient analyze/test | Selesai + teruji | Flutter CI #94, run 41366219716 | Analyze/test SUCCESS |
| Partner analyze/test | Selesai + teruji | Flutter CI #81, run 41362379396 | Analyze/test SUCCESS |
| Patient/Partner staging debug APK | Dilewati | TARAN_STAGING_API_BASE_URL unset; build, summary and upload steps SKIPPED | No APK artifact for this run |
| Patient/Partner unsigned QA AAB compile | Selesai + teruji | Flutter CI #81, run 41362379396 | Both compile and upload SUCCESS; unsigned only |
| Backend tests | Selesai + teruji | Backend CI #124, run 41364515569, SHA f9bcafc | test job SUCCESS |
| Signed Play AAB | Belum tersedia | Gated manual job SKIPPED | Requires owner-managed signing and production URL |
| Staging integration | Menunggu pemilik | No verified owned HTTPS URL/Actions variable | Do not claim app connected |
| Physical Android QA | Belum teruji | No device test record | Required before release |

## Security controls

| Control | Status | Caveat |
|---|---|---|
| Auth/role middleware | Selesai + teruji on covered routes | Maintain route-by-route authorization matrix |
| Partner data ownership | Selesai + teruji for core partner resources | Extend IDOR test coverage |
| Private document validation/storage | Selesai + teruji in backend tests | Production storage and temporary URL remain |
| Complaint reporter isolation/redaction | Selesai + teruji | Legal retention review remains |
| API rate limits | Selesai + teruji for configured routes | Proxy trust must be reviewed at deployment |
| Android backup/cleartext protections | Configured; CI compiles apps | Inspect merged manifest and physical device |
| Secure token storage | Implemented in app source | Device-level security test absent |
| Clinical workflows/SATUSEHAT | Not implemented | Explicitly excluded |

## Audit conclusion

The backend has tested core marketplace workflows, and both Flutter apps pass analyze/test/build compilation. However, there is no verified staging connection or physical-device E2E evidence. This product is **NO-GO for release/external clinical use** until staging, device QA, privacy/legal, backup/restore, monitoring, signed bundle and owner approval gates are met.


## Latest exact-head verification
Code SHA `30da6557ea6ecd1ed898d7040162c56570d13caa`.
- Flutter CI #85 / `41363527847`: SUCCESS. Core, Patient, Partner and both unsigned QA AAB jobs SUCCESS; staging APK build/upload steps SKIPPED because no staging URL; signed AAB SKIPPED by design.
- Backend CI #128 / `41363527786`: SUCCESS. Backend test job SUCCESS, including `HealthEndpointTest`.
- Patient unsigned QA AAB: 51,331,418 bytes; Partner unsigned QA AAB: 51,194,150 bytes. Neither is signed.


## Admin metrics increment
Admin dashboard now fetches aggregate metrics from `GET /api/v1/admin/metrics`: total bookings, non-terminal bookings, active partners and open/in-review complaints. Route allows only ops_admin, reviewer and sysadmin. The endpoint returns aggregate counts only, with regression coverage proving patient denial and absence of booking address, phone and complaint text in the response. Browser E2E remains untested.


## Latest exact-head verification — signed document URL increment
- Code SHA: `a5e5f4eaf93948422e478013c3e53824cb7a28ac`.
- Backend CI #137 / run `41366219710`: SUCCESS; test job SUCCESS (including valid, expired and tampered signed-document URL tests).
- Flutter CI #94 / run `41366219716`: SUCCESS. Core, Patient, Partner, both unsigned AAB jobs SUCCESS; staging APK steps SKIPPED because staging URL unset; signed AAB SKIPPED.
- Patient unsigned AAB: 51,331,418 bytes; Partner unsigned AAB: 51,194,150 bytes.


## Latest exact-head verification — admin action guard
- Code SHA: `c3ddb6ff72997d991a2dcc4e225fe2b99b5c26ff`.
- Admin console async action buttons now ignore duplicate clicks while a returned Promise is pending; complaint workflow actions return their request Promise so they receive this guard.
- `AdminUiSupportTest` asserts the guard and complaint resolution binding; Backend CI #139 / run `41367194962`: SUCCESS.
- Flutter CI #96 / run `41367194904`: SUCCESS. Core, Patient, Partner and both unsigned QA AAB jobs SUCCESS; staging APK steps SKIPPED (no staging URL); signed AAB SKIPPED.
- Patient unsigned AAB 51,331,418 bytes; Partner unsigned AAB 51,194,150 bytes.


## Manual payment ledger — exact code-head evidence
- Code SHA: `49d5f09cd0d1b827a8481eb57add0fafdf41ce13`.
- Backend CI #147 / run `41374239305`: SUCCESS; 139 tests / 837 assertions.
- Flutter CI #104 / run `41374239341`: SUCCESS; Core, Patient, Partner and both unsigned QA AAB compile/upload jobs SUCCESS; signed AAB SKIPPED by design; staging APK skipped because staging URL unset.
- Patient unsigned QA AAB: 51,346,135 bytes. Partner unsigned QA AAB: 51,194,150 bytes.
- Ledger stores only status, method, actor and timestamp (no free-text payment note). PAID can be recorded only after COMPLETED; CANCELLED only for cancelled bookings; transitions are final through normal admin workflow. No gateway or payment processing.
