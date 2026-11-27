# API

ReferralFlow is a server-rendered application. Its JSON endpoints are for the browser session (cookie authentication with MFA) and for interoperability demonstrations. There are NO machine-to-machine tokens in this version; adding them would need an MFA-equivalent control and a threat review. All endpoints below require a signed-in, MFA-verified session. A machine-readable description is in `docs/openapi.yaml` (OpenAPI 3.0, hand-written, covering the JSON endpoints).

Errors: JSON endpoints return `401 {"message":"Unauthenticated."}` when signed out, `403` when the role or access level does not allow it, `404` when a referral is not visible to the caller (existence is not revealed), `423 {"message":"Re-authentication required"}` where a fresh password+code is needed, `429` when rate limited.

| Method and path | Roles | Purpose |
|---|---|---|
| `GET /api/v1/directory` | all signed-in roles | Facility snapshot `[ {id,name,type,phone,state} ]` (no patient data) used by the offline draft picker. Rate limit 60/min. |
| `GET /api/v1/referrals/{id}/fhir` | clinician, coordinator (party or break-glass) | FHIR-shaped bundle (see INTEROPERABILITY.md). Audited. |
| `GET /api/v1/referrals/{id}/documents/{doc}` | clinician, coordinator (after acceptance or referrer) | Decrypted document as an attachment download. Audited. |
| `GET /health` (and `/up`) | public | `{status, database, mode, synthetic_data_only}`; no patient data |

The web endpoints (HTML forms) are listed by `php artisan route:list`; every one is covered by the authorization matrix test. Key families: sign-in and MFA (`/login`, `/mfa`, `/reauth`), referrals (`/referrals`, `/referrals/{id}/{accept|decline|schedule|attend|complete|cancel|messages|reveal|documents|break-glass|withdraw-consent}`), inbox, directory, dashboard (`/dashboard`, `POST /dashboard/export`), access log, admin (users, facilities, audit, retention, forms, break-glass reviews), data requests.

## Conventions
- Referral ids are lower-case ULIDs (26 characters). Display references (`RF-XXXXXXXX`) are derived from the id tail and are not secret-bearing.
- Timestamps are ISO 8601 in UTC in exports.
- Money is not used.
- Pagination: server-side, 15 per page for referrals, 30-40 for audit tables.
