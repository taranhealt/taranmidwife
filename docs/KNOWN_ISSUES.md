# Known Issues and Deferred Work

| Priority | Issue | Evidence / next action |
|---|---|---|
| P0 | No verified staging API | No owned HTTPS URL or app-to-staging run; provision synthetic staging |
| P0 | No physical Android QA | No device execution record; run QA checklist after staging |
| P1 | Reset timing side-channel | Synchronous email only for active users; generic body does not equalize timing |
| P1 | No temporary signed document URL | Authenticated direct download exists; signed-link workflow still needed |
| P1 | Booking scheduling/reschedule/payment | Reschedule policy and manual payment ledger not implemented |
| P1 | No backup/restore, health endpoint or boot validation | Implement and run restore drill |
| P1 | No Docker/compose deployment | Not found in audited source |
| P1 | No FCM/inbox | No device-token lifecycle or inbox implementation |
| P1 | Search, favorites, address book and reviews | No complete implementation/test evidence |
| P2 | R8/minify/mapping and CI version metadata | Not configured/proven |
| P2 | Composer audit / pub outdated CI gates | Dependabot weekly config exists; audit/outdated gates not yet integrated |
| P2 | Strict CORS/CSP | Exact allowlist/CSP not verified |
| P2 | Admin metrics/dashboard | API primitives/admin shell exist; dashboard metrics not proven |

## CI follow-up

- Latest code SHA: `f9bcafccf7248c807a9d08997137fe291a841c3c`.
- Flutter CI #81 (41362379396) and Backend CI #124 (41362379382) completed successfully.
- Since `TARAN_STAGING_API_BASE_URL` is unset, the Patient and Partner staging APK build/summary/upload steps are intentionally skipped. No APK is provided by that run.
- Both unsigned QA AAB compile/upload jobs succeeded. These are not signed Play Store artifacts.
- Workflow includes AAB artifact size/SHA-256 summaries; APK summary executes only when staging is configured.
