# Decisions log

All data is synthetic. Nothing here is a compliance claim; legal points are marked "confirm with counsel".

## D1. No "MOH 100" label; configurable referral form schema
The product brief's "MOH 100" is the Community Referral Form (community health volunteer to facility, with feedback). It is not a facility-to-facility referral form. ReferralFlow therefore does not label any form MOH 100. The referral form is a generic, versioned schema stored in the database (`referral_form_schemas`: key, version, status, field definitions as JSON). A referral records the schema version it was written against. Mapping to official Kenyan forms must be done with the Ministry and facility partners (open dependency).

## D2. National referral policy is only proposed
The Ministry of Health page read in Phase A (dateline 3 March 2026) describes a proposed national referral policy being drafted. ReferralFlow never claims alignment or compliance with it. The status model, reason lists, SLAs and the form are configuration built on generic referral-journey principles (closed loop, minimum necessary, no silent states).

## D3. Interoperability is FHIR-shaped only
A FHIR R4-shaped exporter (ServiceRequest, Patient minimal, Organization and Location, Task, Communication, DocumentReference) with JSON Schemas authored in this repo and tested. Every export carries the label "FHIR-shaped, not validated against any national HIE; no access to national systems exists or is claimed". Facility import uses a KMHFL-style CSV authored here with synthetic fixtures; the real Master Facility List could not be fetched in Phase A (outstanding dependency).

## D4. Synthetic patients only; go-live gate
Every patient row has `is_synthetic = true` and the UI shows "Synthetic demonstration data". Patient creation requires ticking a synthetic-person confirmation. If `REFERRALFLOW_MODE=production` (or APP_ENV=production) the application refuses to boot unless `REFERRALFLOW_PRODUCTION_ACK` carries the acknowledgement phrase, and even then it prints the unmet checklist and keeps the banner. `php artisan golive:gate` lists unmet legal and security prerequisites and exits non-zero while any are unmet.

## D5. Privacy defaults
Identifiers are masked by default and revealed only with a reason (logged). No patient data in URLs, page titles, notifications, mail, logs or error pages (tests assert this). A receiving facility sees a referral only after it is sent to it, and only minimum fields until it accepts. A consent record is required before send.

## D6. Stack
Laravel 13 / PHP 8.4, SQLite for dev and test with Postgres-portable migrations, Blade plus vanilla JS via Vite (no CDN), Pest, Pint, Larastan. See ARCHITECTURE.md. No deviation from the recommended stack.

## D7. Own TOTP implementation
RFC 6238 TOTP is about 40 lines on top of `hash_hmac`; it is implemented in `App\Security\Totp` and tested against the RFC test vectors, avoiding a dependency in the authentication path. Recovery codes are stored hashed.

## D8. Roles
facility clinician, facility coordinator, facility admin, network analyst (aggregates only), platform admin (no clinical access by default). The design's "manager" maps to network analyst; "administrator" splits into facility admin (own staff, own facility) and platform admin (facilities, audit, retention, form schemas).

## D9. Attachments, identity matching, Swahili, offline
Designed in `design/addenda.md` before being built.

## D10. No automatic status changes on timeout
Unacknowledged referrals escalate (notifications, flags) but are never auto-cancelled: silently changing a referral's state is a clinical-safety hazard.
