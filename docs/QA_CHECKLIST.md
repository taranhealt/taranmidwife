# QA Checklist — Synthetic Data Only

No physical-device test evidence is attached. Record date, commit, app version, device and evidence before marking pass.

| ID | Scenario | Expected | Status |
|---|---|---|---|
| 01 | Fresh Patient/Partner install | Safe config screen without API URL; no crash | Belum diuji |
| 02 | Patient register/login | Synthetic account works; safe errors | Belum diuji |
| 03 | Partner role guard | Only partner role enters Partner App | Belum diuji |
| 04 | Catalogue/offline | Loading, empty, retry and offline states clear | Belum diuji |
| 05 | Booking validation | Invalid service/area/date/slot/address/phone rejected | Belum diuji |
| 06 | Idempotency | Retry same key creates one booking | Backend test exists; device belum diuji |
| 07 | Lifecycle | Patient request -> ops assign -> partner accept/start/complete; status consistent | Belum diuji |
| 08 | Contact privacy | Contact hidden before accept; unrelated partner denied | Backend coverage partial |
| 09 | Cancellation | Only allowed transitions; boundary times tested | Belum diuji |
| 10 | Verification | Upload/review/sysadmin approve or reject; reason and resubmit | Backend partial; app E2E belum diuji |
| 11 | Documents | MIME/extension/size rejection; authorized private download | Backend partial |
| 12 | Token expiry | Expired token cleared and app returns to login | Core unit test; app E2E belum diuji |
| 13 | Complaint/delete | Ownership, duplicate conflict, [DIHAPUS] redaction | Backend tests; device belum diuji |
| 14 | Password reset | Generic response, real email, single-use/expiry, session revoke | Backend partial; timing equality not proven |
| 15 | WIB timezone | Correct UTC storage/WIB display at date boundary | Unit coverage partial |
| 16 | Poor network | Friendly retry, no SQL/stack/token/PII leakage | Belum diuji |
| 17 | Accessibility | Screen reader, large text, contrast, focus and touch targets | Belum diuji |
| 18 | Backup restore | Restore DB and private files in isolation, smoke test | Belum diuji |
| 19 | Release | Signed AAB, package/version, mapping and internal install | Belum diuji |

Use example.test emails, synthetic names, dummy phone numbers and dummy documents marked DATA UJI. Never use real patient data, NIK, SIP or location traces.
