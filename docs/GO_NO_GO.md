# GO / NO-GO

Decision snapshot: 2 November 2026. Code audit SHA: `9d86e8b296be091f2f387483216d2883b52b4407`. Overall **NO-GO for release or external clinical use**.

| Criterion | Status | Evidence/blocker |
|---|---|---|
| Correct branch; main untouched; PR unmerged | Terpenuhi at snapshot | PR #1 base main, head infra/android-ci-hardening, OPEN/DRAFT |
| Flutter core/patient/partner analyze and tests | Terpenuhi | Flutter CI #85, run 41363527847, SHA 30da655; all three jobs SUCCESS |
| Staging debug APKs | Dilewati | TARAN_STAGING_API_BASE_URL unset; APK build/upload steps SKIPPED, no APK artifact |
| Unsigned QA AABs | Terpenuhi | Flutter CI #81, both Patient and Partner compile/upload SUCCESS; not signed |
| Backend automated tests | Terpenuhi | Backend CI #128, run 41363527786, SHA 30da655, test SUCCESS |
| Non-sensitive `/api/health` database readiness endpoint | Terpenuhi | Backend `HealthEndpointTest`: healthy response and sanitized 503 on DB exception |
| Admin metrics API role/privacy regression | Terpenuhi | Backend CI #132; AdminDashboardMetricsTest verifies role denial and no PII in aggregate response |
| Signed partner document URL (5-minute expiry, signature and staff authorization) | Terpenuhi | PartnerVerificationTest on Backend CI #137 (41366219710), exact SHA a5e5f4e |
| Admin console duplicate-action guard | Terpenuhi | AdminUiSupportTest on Backend CI #139, run 41367194962 |
| Manual payment ledger, one-way status transitions, role/booking-state guards, audit history and Patient/Partner payment status display | Terpenuhi at code/CI level | BookingPaymentTest + AdminUiSupportTest, Backend CI #147 run 41374239305 (139 tests/837 assertions); Flutter CI #104 run 41374239341 Patient model/UI analyze/test and unsigned AAB jobs SUCCESS |
| Full backend integration flow including payment ledger | Belum | Full register-to-visit/manual-payment/review/delete E2E not evidenced |
| Staging API configured and apps connected | Menunggu pemilik | No verified owned HTTPS staging URL or Actions variable |
| Physical Android QA | Belum | No device execution record |
| Signed AAB/signature verification | Menunggu pemilik | Keystore/secrets absent; signed job gated |
| Privacy/account-deletion legal approval | Menunggu pemilik | Complaint free text redaction implemented; legal review outstanding |
| Data Safety matches final build | Belum | Play Console declarations incomplete |
| Backup/restore | Belum | No restore evidence |
| Monitoring/incident alerting | Belum | Not configured/verified |
| Legal review / UU PDP and health rules | Menunggu pemilik | Qualified legal and clinical review required |
| Clinical records / ANC / PNC / SATUSEHAT | Not implemented | Explicitly out of scope |
| Owner release approval | Menunggu pemilik | Not granted |

NO-GO until every required gate is Terpenuhi with exact commit, CI, deployment and device evidence plus owner approval. This is not a production-readiness statement.


## Latest payment ledger evidence
- Exact code SHA: `49d5f09cd0d1b827a8481eb57add0fafdf41ce13`.
- Backend CI #147 / `41374239305`: SUCCESS, 139 tests and 837 assertions.
- Flutter CI #104 / `41374239341`: SUCCESS; Patient/Partner/Core and unsigned QA AABs all SUCCESS; signed AAB SKIPPED; staging APK steps SKIPPED (no staging URL).
- Patient AAB 51,346,135 bytes; Partner AAB 51,194,150 bytes. Both unsigned QA only.


## Partner payment status evidence
- PartnerBooking parses payment_status and Partner App booking cards show translated status; midwife_models_test covers PAID and default UNPAID.
- Backend CI #151 / run `41375405844`: SUCCESS.
- Flutter CI #108 / run `41375405895`: SUCCESS, all core/app/AAB jobs pass; signed AAB and staging APK intentionally skipped.
- Exact code SHA `9d86e8b296be091f2f387483216d2883b52b4407`.
