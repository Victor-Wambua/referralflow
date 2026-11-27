# Interoperability

**FHIR-shaped, not validated against any national HIE; no access to national systems exists or is claimed.** This label is carried in every export (`meta.tag` on the bundle and every resource, and the `X-Interop-Notice` header).

## What was researched
The Phase A research (design/product-brief.md section 3) found that FHIR is the stated direction for Kenyan digital health, that a Kenya HIE/health information mediator has been piloted on OpenHIE architecture, and that the national referral policy is only PROPOSED (a draft discussed in March 2026). The KMHFL (Master Health Facility List) could not be fetched and HL7 pages returned errors; those remain outstanding dependencies. Nothing here has been checked against an official Kenyan implementation guide.

## FHIR R4-shaped export
`GET /api/v1/referrals/{id}/fhir` returns a `Bundle` (type `collection`) containing:
| Resource | Source | Notes |
|---|---|---|
| `ServiceRequest` | the referral | status: draft/active/completed/revoked; intent `order`; priority emergency=stat, urgent=urgent, routine=routine; requester/performer = facility Organizations; reason text; clinical note only for full access |
| `Task` | the status | status requested/received/accepted/ready/in-progress/completed/rejected/cancelled; `businessStatus.text` carries the exact ReferralFlow status; `statusReason` carries the decline/cancel reason code |
| `Patient` (minimal) | the patient | before acceptance only `gender`; after acceptance also `name.text`, `birthDate`, one `identifier` |
| `Organization`, `Location` | both facilities | MFL code (synthetic) as identifier, telecom, address in Kenya, hours |
| `Communication` | each message | sender/recipient facility, payload text |
| `DocumentReference` | each document (full access only) | content type, size, SHA-1 hash (as FHIR requires), a relative URL to the authorised download endpoint; title is always "Attachment" |

Export respects the same access levels as the UI, is rate limited, and is audited (`fhir.export`).

## Schema validation test
The JSON Schemas in `app/Interop/schemas/` are AUTHORED HERE as a simplified subset of R4 (required fields, enumerations, reference patterns, the label). They are not the official definitions. `tests/Feature/InteropTest.php` validates a full bundle against them, checks all nine status mappings and three priority mappings, checks the minimum-necessary behaviour, and proves the schemas reject malformed resources. No FHIR validator was installed; running the official validator and a FHIR server round-trip is a go-live/integration task.

## Facility directory import (KMHFL-style CSV, authored here)
The real KMHFL format could not be fetched, so this format is invented for this project and exercised with synthetic fixtures (`database/fixtures/facilities-synthetic.csv`). It is NOT the KMHFL schema; a real import adapter needs the real schema and API access.

Columns (header required, comma separated, UTF-8): `mfl_code, name, facility_type, keph_level, county, sub_county, ward, ownership, operational_status, phone, email, open_hours, services`
- `mfl_code` 4-8 digits, unique; `name` and `county` required; `keph_level` 2-6 or blank; `email` valid or blank; `services` separated by `|`.
- `facility_type` maps (Dispensary, Health Centre, Medical Clinic, Sub-County Hospital, County Referral Hospital, National Referral Hospital); unknown values become `other`.
- `operational_status` "Operational" means active, anything else inactive.
- Upsert by `mfl_code`. New facilities and services start UNVERIFIED; re-imports never wipe verification. Row errors are reported by line; good rows are still imported; a dry run rolls everything back. Maximum 5,000 rows and 1 MB in the UI.

Run: `php artisan directory:import path/to/file.csv [--dry-run]`, or Admin > Facilities > Import.

## Not done
No HL7 v2, no OpenHIM mediator, no national patient or provider registry lookup, no SHA/Taifa Care integration, no KHIS indicator feed. Each needs partner access and approval.
