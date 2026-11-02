# GO / NO-GO

Decision snapshot: 2 November 2026. Code audit SHA: `30da6557ea6ecd1ed898d7040162c56570d13caa`. Overall **NO-GO for release or external clinical use**.

| Criterion | Status | Evidence/blocker |
|---|---|---|
| Correct branch; main untouched; PR unmerged | Terpenuhi at snapshot | PR #1 base main, head infra/android-ci-hardening, OPEN/DRAFT |
| Flutter core/patient/partner analyze and tests | Terpenuhi | Flutter CI #85, run 41363527847, SHA 30da655; all three jobs SUCCESS |
| Staging debug APKs | Dilewati | TARAN_STAGING_API_BASE_URL unset; APK build/upload steps SKIPPED, no APK artifact |
| Unsigned QA AABs | Terpenuhi | Flutter CI #81, both Patient and Partner compile/upload SUCCESS; not signed |
| Backend automated tests | Terpenuhi | Backend CI #128, run 41363527786, SHA 30da655, test SUCCESS |
| Non-sensitive `/api/health` database readiness endpoint | Terpenuhi | Backend `HealthEndpointTest`: healthy response and sanitized 503 on DB exception |
| Admin metrics API role/privacy regression | Terpenuhi | Backend CI #132; AdminDashboardMetricsTest verifies role denial and no PII in aggregate response |
| Full backend integration flow | Belum | Full register-to-visit/manual-payment/review/delete E2E not evidenced |
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
