# Architecture

ReferralFlow is a modular monolith: one Laravel application, one database, server-rendered Blade pages with a small amount of vanilla JavaScript. It is a staging-ready build for SYNTHETIC data only; it is not production ready.

## Stack (and why)
| Part | Choice | Notes |
|---|---|---|
| Runtime | PHP 8.4, Laravel 13 | Mature framework with a built-in encrypter (AES-256-GCM), sessions, rate limiter, migrations. No deviation from the recommended stack. |
| Database | SQLite (dev/test), PostgreSQL 16 (staging config) | Migrations are portable (no vendor SQL except the audit-table triggers, written for SQLite and PostgreSQL). Postgres config ships in `docker-compose.yml` and was NOT run on the dev machine. |
| UI | Blade + vanilla JS via Vite, no CDN | Design tokens from `design/design-tokens.css`; CSS from the approved prototype. |
| Tests | Pest 4, Pint, Larastan (level 5), Playwright | See `ACCEPTANCE-REPORT.md` for real counts. |
| Interop schema check | `opis/json-schema` | Validates FHIR-shaped output against schemas authored in `app/Interop/schemas`. |
| TOTP | Own RFC 6238 class (`App\Security\Totp`) | Tested against the RFC test vectors; avoids a dependency in the sign-in path. |

## Modules
- `App\Security`: `TenantContext` (who is asking; default deny), `ReferralAccess` (the single decision point for referral visibility), `AuditLog` (hash chain), `Totp`, `BlindIndex`, `Masker`, `ProductionGuard`, `GoLiveGate`.
- `App\Domain`: `StatusMachine` (nine statuses, legal moves, who may move), `ReferralService` (all referral writes), `PatientService`, `AttachmentService`, `Notifier`, `EscalationService`, `MetricsService`, `RetentionService`, `DataRequestService`, `ReferralView` (the only thing Blade sees for a referral), `FormSchemaValidator`.
- `App\Interop`: `FhirExporter`, `FhirSchemas`. `App\Directory`: `DirectoryImporter`, `Verification`.
- `App\Http`: thin controllers; middleware `RequireMfa`, `RecentAuth` (re-authentication), `IdleTimeout`, `RequireRole`, `SecurityHeaders`, `SetLocale`.

## Tenancy: one query layer
`Referral` has a global scope that calls `ReferralAccess::constrain()`. With no authenticated clinical user and no explicit system context the scope returns nothing (default deny). Child records (messages, consents, events, attachments, counter-referrals) are scoped by "their referral is visible". `Patient` is scoped to the user's facility. `ReferralAccess::level()` makes the per-record decision with the same rules; a test asserts that, for every user and referral state, list membership equals "level is not none". Levels: none, minimal (receiving facility after Sent, before Accepted), full, tombstone (consent withdrawn), break-glass (read only). Aggregate metrics are computed by `MetricsService` in an explicit system context selecting only non-identifying columns.

## Status machine
Created, Sent, Received, Accepted, Scheduled, Patient attended, Completed, Declined, Cancelled. Legal moves are a table in `StatusMachine`; every move records the side (referrer or receiver) and roles that may perform it. Declines and cancellations need a reason from a controlled list (cancel includes "patient did not attend"). Completion is only possible together with a counter-referral summary, so the loop closes. Unacknowledged referrals escalate (reminders and flags) but are never changed automatically.

## Data model (tables)
`facilities`, `facility_services` (availability with `verified_at`/`verified_by`), `users`, `patients` (facility-owned, encrypted), `referrals` (ULID ids), `referral_form_schemas` (versioned form definitions), `consents`, `referral_events`, `messages`, `counter_referrals`, `attachments`, `notifications`, `break_glass_grants`, `legal_holds`, `data_requests`, `settings`, `audit_entries` (append-only, hash-chained, protected by database triggers), plus Laravel sessions/cache/jobs.

## Request flow for a sensitive read
auth -> MFA verified -> idle/absolute timeout check -> role check -> `ReferralAccess::locate()` -> `ReferralView::build()` filters fields by level -> audit entry (in the same request) -> Blade renders only the view array.

## Configuration
`config/referralflow.php` holds session limits, SLAs per priority, verification thresholds, reason lists, upload limits and retention placeholders. Operational values (retention days, verification thresholds) can be changed by a platform admin and are stored in `settings`.

## Deviations from the recommendation
None. The only notable choice is the in-house TOTP class (see above).
