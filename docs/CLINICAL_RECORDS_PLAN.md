# Clinical Records — Deferred Scope

**NOT IMPLEMENTED.** Booking/complaint text is not a clinical record. No ANC/PNC, diagnosis, prescription, clinical chat, longitudinal record or SATUSEHAT integration is claimed.

## Proposed model (requires clinical/legal approval)
- ClinicalEncounter: patient, assigned partner, booking, encounter time and status.
- ClinicalConsent: version, purpose, scope, lawful basis, grant/withdrawal time and evidence.
- ClinicalObservation: coded value, unit, method, effective time, author and provenance.
- ClinicalAssessment: clinician-authored assessment, signature and amendment chain.
- ClinicalPlan: clinician-approved follow-up and responsible professional.
- ClinicalDocument: private object key, MIME, size, hash, encryption-key reference and retention metadata.
- ClinicalAccessEvent: append-only actor, patient, resource, purpose, time and outcome; never log clinical content.

## Consent and access
Use granular purpose-specific consent where applicable, retain policy version and evidence, support access/correction/export, define lawful deletion/retention with counsel, and implement emergency break-glass access with reason, expiry and retrospective review. Partners access only assigned encounters and minimum necessary time. Separate clinical roles from support/admin.

## Security/lifecycle
TLS, managed encryption keys, key rotation, private object storage, malware scanning, MIME allow-list, short-lived authorized links, immutable hashes, append-only access audit, versioned amendments, retention/legal hold and encrypted tested backups. No clinical values, tokens, names or contacts in logs, analytics or notifications.

## Indonesian review
Qualified counsel must review UU 27/2022 PDP, controller/processor roles, lawful basis, sensitive data, user rights, breach notification, DPIA where applicable, cross-border transfer and processor contracts. Review current health-sector medical-record rules, professional scope, PSE, retention and clinical governance. SATUSEHAT later requires approved onboarding, FHIR profiles, terminology, IHS identity matching, consent, sandbox and production authorization. This is not legal advice.

No clinical schema/API until owner, licensed clinical lead, counsel and security reviewer approve data dictionary, consent, retention, access matrix, emergency access, audit, hosting and tests.
