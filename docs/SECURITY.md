# Security

Status: STAGING / SYNTHETIC DATA ONLY. No penetration test has been done. No compliance claim is made. "Confirm with counsel" applies to every legal statement.

## Controls implemented (and where they are tested)
| Control | Implementation | Test |
|---|---|---|
| MFA for all users | TOTP (RFC 6238), enrolment forced at first sign-in, recovery codes (hashed), replay protection, lockout | `AuthTest`, `TotpTest` |
| Re-authentication for sensitive actions | password + fresh code within 5 minutes (exports, break-glass, user/role/MFA changes, retention, directory import, form schemas, audit export) | `AuthTest`, `AuthzMatrixTest` |
| Short sessions | 15-minute idle timeout (warning at 60 s in the UI), 8-hour absolute limit, encrypted session store, session regenerated at sign-in | `AuthTest` |
| Password hashing | Argon2id | `AuthTest` |
| RBAC | roles: clinician, coordinator, facility admin, network analyst (aggregates), platform admin (no clinical access) | `AuthzMatrixTest` (every role x 39 endpoint families, plus guest and pre-MFA) |
| Facility tenancy | single query layer, default deny, minimum fields until accepted | `TenancyTest` (every user x referral state) |
| Field-level encryption | Laravel encrypter, AES-256-GCM, for names, dates of birth, identifiers, reason text, clinical notes, form data, messages, summaries, document names, MFA secrets | `PrivacyTest` (raw columns are authenticated-encryption envelopes; tampering is detected) |
| Document storage | private disk, encrypted file contents, served only by an authorised audited controller, opaque names, sandbox CSP, nosniff | `AttachmentTest`, `PrivacyTest` |
| Upload validation | magic bytes, extension and declared type must agree, 5 MB, 5 per referral, PDF active-content rejection, image re-encode (drops EXIF and trailing data), antivirus hook interface | `AttachmentTest` |
| Break-glass | reason (20+ characters), emergency confirmation, re-authentication, 60 minutes, read-only, flagged in audit, facility lead notified and reviews | `BreakGlassTest` |
| Audit log | every read/write of patient data; append-only (model guard and DB triggers on SQLite and PostgreSQL); hash chain with contiguous sequence; `audit:verify` | `AuditTest` |
| CSRF, XSS, SQLi | CSRF middleware on every state-changing route; Blade escaping; Eloquent/query builder bindings; CSP without `unsafe-eval` or inline scripts | `AuthTest`, `AuthzMatrixTest` |
| Rate limiting | login, MFA, reveal, search, sensitive actions, API | `AuthTest` |
| Headers | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy no-referrer, Permissions-Policy, HSTS when HTTPS, `Cache-Control: no-store` for signed-in pages | `AuthTest` |
| No PHI in URLs, titles, notifications, mail, logs, errors | opaque ULIDs, static titles, neutral notification text, custom error pages | `PrivacyTest` |
| Production guard | refuses to boot in production mode without acknowledgement; `golive:gate` lists unmet prerequisites | `PlatformTest` |
| Retention and deletion | purge job, legal holds, export/deletion workflow | `RetentionTest` |

## Threat model (summary)
| Threat | Mitigation | Residual |
|---|---|---|
| Stolen password | Argon2id, MFA, lockout, rate limits | Phishing of password plus live TOTP code is possible; no hardware keys (WebAuthn) yet |
| Curious insider at another facility | tenancy layer, masking, logged reveal, break-glass review | Review depends on a human lead acting; no anomaly detection |
| Curious insider at the same facility | masked by default, reveal needs a reason and is logged | Same-facility staff can legitimately reveal; reasons are free-form categories |
| Platform operator reads patient data | clinical access not granted to platform admin; fields encrypted | An operator with database AND application key access can decrypt; key custody is a deployment matter (see below) |
| Database theft | field-level encryption with authenticated encryption | The key lives in the environment; a stolen environment is a full compromise |
| Audit tampering | triggers, hash chain, verification command | An attacker with database superuser and application code access could rebuild the chain; anchoring the chain head outside the database (write-once storage) is NOT implemented (go-live item) |
| Malicious upload | magic bytes, size, re-encode, PDF script rejection, AV hook | No real antivirus is bound; `AV_MODE=required` refuses uploads until one is |
| Session theft | secure cookie settings (set `SESSION_SECURE_COOKIE=true` under TLS), short timeout, `no-store` | Device-level compromise not addressed |
| Enumeration of referral ids | opaque ULIDs, identical locked page for existing and non-existing ids | Timing differences not analysed |
| Notification leakage | neutral text, no identifiers | Facility names appear nowhere in mail; links carry the opaque id |
| Misuse of dashboards | aggregates only, small cells suppressed (fewer than 5), alphabetical order | Re-identification by differencing is not analysed |

## Unresolved risks (be honest)
1. No independent penetration test or code review.
2. No real antivirus scanner bound.
3. Audit chain head is not anchored outside the database.
4. No hardware-key MFA; recovery codes are the only fallback.
5. Keys are held in the environment; no KMS/HSM integration.
6. Session and CSRF behaviour has been tested in tests and a real browser, not under load or behind a real proxy.
7. The clinical-safety hazards (see `PILOT.md`) have not been reviewed by a clinician.
8. Larastan runs at level 5, not the strictest level.
9. Dependency audits (composer, npm) were clean on the day they were run; they must be repeated in CI.

## Key management and rotation
- `APP_KEY` encrypts every field, document, session and cookie. Generate with `php artisan key:generate`. Keep it out of the repository (`.env` is git-ignored; only `.env.example` is committed).
- Rotation: (1) generate a new key; (2) put the old key in `APP_PREVIOUS_KEYS` (comma separated) and the new key in `APP_KEY`; (3) deploy; (4) run `php artisan encryption:rotate --dry-run`, then `php artisan encryption:rotate` (re-encrypts every column and document and writes an audit entry); (5) verify, then remove the old key from `APP_PREVIOUS_KEYS`. Tested in `KeyRotationTest`.
- `BLIND_INDEX_KEY` (HMAC for same-facility identifier matching) is separate. Changing it requires recomputing the `identifier_hash` and `name_dob_hash` columns (a rebuild command is NOT provided; matching would silently stop finding duplicates until rebuilt).
- Backups of the database are useless without the key and the key must never be stored only with the backups (see `DEPLOYMENT.md`).
- Cipher: AES-256-GCM (`APP_CIPHER`). Do not change the cipher without a data migration.

## Reporting
There is no public disclosure channel yet. Define one before any pilot.
