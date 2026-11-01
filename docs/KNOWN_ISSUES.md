# Known Issues and Deferred Work

| Priority | Issue | Evidence / next action |
|---|---|---|
| P0 | No verified staging API | No owned HTTPS URL or app-to-staging run; provision synthetic staging |
| P0 | Flutter CI #76 unresolved in last retrieved snapshot | API reported in_progress; fetch terminal result for exact HEAD |
| P1 | Reset timing side-channel | Sync mail only for active users; generic body does not equalize timing |
| P1 | No short-lived signed document URL | Authenticated direct download exists; keep private and design signed link with tests |
| P1 | Booking limiter | Added 10/min per user/IP and regression test in this commit; await CI |
| P1 | No backup/restore, health endpoint or boot validation | Implement and test |
| P1 | No Docker/compose | Not found in audited tree |
| P1 | No FCM/inbox | No verified device token lifecycle or inbox |
| P1 | Search, favorites, address book, reviews | No complete implementation/test evidence |
| P1 | Reschedule, cancellation policy, manual payment ledger | Not implemented as complete flow |
| P2 | R8/minify/mapping and CI version metadata | Not verified |
| P2 | Dependabot/composer audit/pub outdated | Automation not verified |
| P2 | Strict CORS/CSP | Exact allowlist/CSP not verified |
| P2 | Admin metrics and signed doc links | Not proven |
