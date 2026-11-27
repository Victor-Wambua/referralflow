# Known limitations (disclosed)

**Not production ready. Synthetic data only. No compliance claim.**

## Product and scope
- Referral forms: a generic configurable schema, not any official Kenyan form; mapping to official forms needs Ministry/facility partners.
- The national referral policy is proposed only; nothing claims alignment.
- No EMR, no billing/claims, no patient-facing app, no SMS or push notifications (in-app notices and email only; email is logged in dev).
- Facility roles: no "receiving clinician can accept" (by design only coordinators accept/decline/schedule); one referral destination per referral; no re-referral chain tool (refer elsewhere = create a new referral).
- Identity: same-facility exact-identifier and exact name+date-of-birth+sex matching only; no fuzzy/transliteration matching; no patient merge; no cross-facility index.
- Facility admins cannot edit their facility profile (platform admin does); verification and services are editable by the facility's coordinator or admin.
- No data-subject self service; requests are handled by staff.
- No analytics beyond the dashboard; no KHIS/DHIS2 feed.

## Interoperability
- FHIR output is FHIR-shaped, validated only against schemas authored in this repository (a simplified subset), not the official FHIR validator, not any national HIE. No national system access exists.
- The KMHFL CSV format is invented for this project (the real list could not be fetched).

## Security and privacy
- No penetration test, no code audit, no real antivirus (hook only; required mode refuses uploads without one).
- Audit chain is not anchored outside the database.
- Keys live in the environment; no KMS/HSM; the blind-index key has no rebuild command.
- MFA is TOTP only; no WebAuthn. The setup screen shows the key and `otpauth://` URI as text (no QR code image).
- Session timeout, CSRF and headers were tested locally, not behind a production proxy or under load.
- Free-text fields can still contain identifiers typed by users; warnings only.
- Retention periods are placeholders; deletion does not reach backups.
- Cached facility phone numbers (non-patient data) can remain in the browser's local storage until sign-out.

## Offline
- No patient data is stored offline by design: you cannot create a patient referral without a connection. Only a non-PHI draft (priority, reason category, destination) and a facility directory snapshot are stored. No background sync. Not tested on low-end devices or poor networks.

## Quality
- Coverage percentage is not reported: no coverage driver (pcov/xdebug) is installed on the dev machine. CI is configured to produce it.
- Larastan is at level 5, not the maximum.
- Accessibility verified only by design-stage scripts and the structure of the app; no screen-reader or keyboard audit on devices; WCAG conformance is NOT claimed.
- Kiswahili copy was drafted without a native clinical translator; only completeness (not correctness) is tested.
- No usability testing with clinicians; the "under 2 minutes" target is unmeasured.
- Docker/Postgres configuration not run locally.
- Browser tests run in Chromium only.

## Dependencies outside software
Legal review, ODPC position, DPIA, facility agreements, MoH engagement, clinical-safety review, penetration test, real KMHFL access (all in `GO-LIVE-GATE.md`).
