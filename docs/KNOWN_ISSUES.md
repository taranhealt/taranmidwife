# Known Issues and Deferred Work

| Priority | Issue | Evidence / next action |
|---|---|---|
| P0 | No verified staging API | No owned HTTPS URL or app-to-staging run; provision synthetic staging |
| P0 | No physical Android QA | No device execution record; run QA checklist after staging |
| P1 | Reset timing side-channel | Synchronous email only for active users; generic body does not equalize timing |
| P1 | Signed document URL needs deployed proxy validation | 5-minute signed URL + authenticated staff route implemented and covered by CI; validate URL generation behind real HTTPS reverse proxy |
| P1 | Booking scheduling/reschedule/payment | Reschedule policy and manual payment ledger not implemented |
| P1 | No backup/restore, health endpoint or boot validation | Implement and run restore drill |
| P1 | No Docker/compose deployment | Not found in audited source |
| P1 | No FCM/inbox | No device-token lifecycle or inbox implementation |
| P1 | Search, favorites, address book and reviews | No complete implementation/test evidence |
| P2 | R8/minify/mapping and CI version metadata | Not configured/proven |
| P2 | Composer audit / pub outdated CI gates | Dependabot weekly config exists; audit/outdated gates not yet integrated |
| P2 | Strict CORS/CSP | Exact allowlist/CSP not verified |
| P2 | Admin metrics browser QA | Aggregate endpoint and UI cards implemented; browser/device E2E still needed |

## CI follow-up

- Latest code SHA: `c3ddb6ff72997d991a2dcc4e225fe2b99b5c26ff`.
- Flutter CI #85 (41363527847) and Backend CI #128 (41363527786) completed successfully.
- Since `TARAN_STAGING_API_BASE_URL` is unset, the Patient and Partner staging APK build/summary/upload steps are intentionally skipped. No APK is provided by that run.
- Both unsigned QA AAB compile/upload jobs succeeded; `/api/health` is covered by success and sanitized failure regression tests. These are not signed Play Store artifacts.
- Workflow includes AAB artifact size/SHA-256 summaries; APK summary executes only when staging is configured.

- Admin metrics API/UI is implemented on latest code; API role/privacy regression test passed in Backend CI #132. Browser interaction and accessibility still require device/browser QA.

- Backend CI #137 (41366219710) and Flutter CI #94 (41366219716) passed on exact code SHA `a5e5f4eaf93948422e478013c3e53824cb7a28ac`.

- Admin web console prevents duplicate asynchronous action submissions. Regression assertion is in AdminUiSupportTest; Backend CI #139 passed. Flutter CI #96 passed on the same SHA.
