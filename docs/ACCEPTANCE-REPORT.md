# Acceptance report

Scored honestly: **met** (works and was verified by an executed test or browser run), **partially** (works with a stated gap), **not met**. Verification was done on the development machine on 2026-11-27 with SYNTHETIC data. Not production ready. No compliance claim.

## Executed quality gates (real numbers)
| Gate | Result |
|---|---|
| Pest (unit + feature) | **133 tests, 10,811 assertions, all passed** (about 5 s). Files: Auth 19, Privacy 12, Platform 12 (production guard, go-live gate, offline, Kiswahili), Workflow 11, Interop 9, Attachment 9, Retention 9, Tenancy 8, BreakGlass 8, Totp 8, Admin 7, Metrics 7, Audit 5, Authz matrix 5, StatusMachine 3, KeyRotation 1 |
| Authorization matrix | 39 endpoint families x 5 roles (195 cells), plus signed-out and pre-MFA sweeps of every family, plus a re-authentication sweep of every sensitive POST |
| Cross-facility isolation | every one of 11 users x 9 referral states (99 cells) for access level, list-vs-level consistency, HTTP content, cross-facility writes, aggregate-only roles |
| Status machine | all 81 from/to pairs asserted; role/side rules; reasons; completion needs a summary |
| Browser e2e (Playwright, Chromium, real app on port 4461) | **55 of 55 checks passed** (`e2e/results.json`): sign-in with MFA, create with consent, stale-facility phone confirmation, send, receive, accept, schedule, attended, complete with feedback, decline path, break-glass and lead review, audit chain, mobile (390) and tablet (768) layout, dark mode, service worker, no console errors |
| Pint | passed |
| Larastan | level 5, no errors (higher levels not attempted; level 6 reports about 250 missing-type notes) |
| `composer audit` / `npm audit` | no advisories / 0 vulnerabilities (run on the day; must be repeated in CI) |
| Coverage | **not measured**: no coverage driver (pcov/xdebug) is installed on the dev machine and system extensions were not to be installed. CI is configured to produce it. No figure is claimed for critical rules or overall. |
| CI | workflow written (`.github/workflows/ci.yml`), NOT pushed or run |
| Docker/Postgres | Dockerfile, compose and nginx written; NOT built or run (no Docker/Postgres here) |

Defects found and fixed during QA by these tests: service-worker background fetch polluted Laravel's "previous URL" (sign-in failures redirected to the offline page); the global security-headers middleware overrode the sandbox CSP on document downloads; the framework's local-disk serve route was exposed (disabled); an unauthorised "accept" attempt could change a referral to Received; validation ran before authorisation on some actions; deleting a patient failed on foreign keys; hidden banners were visible because of CSS specificity; the demo seeder produced zero-minute durations.

## Product acceptance (spec 09)
| Criterion | Status | Evidence |
|---|---|---|
| Facilities register | Met | Admin register + KMHFL-style CSV import with row errors and dry run (`AdminTest`, `InteropTest`) |
| Authorised providers create referrals | Met | `WorkflowTest`, e2e create/send; role and facility enforced (`AuthzMatrixTest`) |
| Secure transmission | Partially | Referrals move only inside the application behind MFA, tenancy and field encryption; TLS and secure cookies are deployment configuration and were not exercised (no TLS locally) |
| Receiving facilities accept / decline | Met | `WorkflowTest`, e2e accept and decline with required reason |
| Status changes tracked | Met | all nine statuses, event history tab, audit entries (`StatusMachineTest`, `WorkflowTest`, e2e history check) |
| Completion recorded | Met | completion requires outcome + summary (counter-referral); referrer sees it (e2e) |
| Relevant dashboards | Met | created, sent, accepted, pending, completed, median acknowledge/decision/completion, incomplete rate and reasons, facility table with suppression; hand-computed fixture (`MetricsTest`) |
| Unauthorised users cannot access patient info | Met | `TenancyTest`, `AuthzMatrixTest`, `BreakGlassTest`, `PrivacyTest` |
| Audit trails complete | Met for the enumerated paths | every read and write path listed in `AuditTest` writes an entry; chain verified; tamper detection tested. Not an exhaustive proof; chain head is not anchored outside the database |
| Testable with synthetic data | Met | seeded network of 10 facilities, 32 users, 200 referrals in all statuses; every patient `is_synthetic` |

## Functional areas A-G
| Area | Status | Notes |
|---|---|---|
| A Facility directory | Met | verification by/at, stale thresholds, import, search, report a change |
| B Referral creation | Met | configurable versioned form schema (not labelled MOH 100), priority, minimum necessary, consent before send, documents (validated, encrypted) |
| C Status machine | Met | nine statuses, reasons, SLA reminders and escalation (never auto-cancel) |
| D Communication | Met | two-way messages, information requests, counter-referral closes the loop |
| E Dashboards | Met | reproducible, small-cell suppression and caution |
| F Interoperability | Met as scoped | FHIR-shaped export with schema tests; labelled unvalidated; no national system access. Official FHIR validator not run |
| G Privacy | Met, offline partially | RBAC, MFA for all, re-authentication, Argon2id, field encryption (AES-256-GCM) with key rotation command, masking + logged reveal, break-glass with review, consent, retention + purge + legal holds, export/deletion workflow, no PHI in URLs/titles/notifications/logs/errors. Offline: real service worker, but only non-PHI drafts by design (no patient data stored on device) |

## Universal acceptance list
| Item | Status | Evidence |
|---|---|---|
| Critical journeys work | Met | e2e 55/55 |
| Data persists | Met | database-backed; seed and tests |
| Business logic tested | Met | status machine, tenancy, consent gating, metrics, escalation, retention |
| Authn + authz work | Met | `AuthTest`, `AuthzMatrixTest` |
| Critical data protected | Partially | encryption, masking, tenancy tested; no independent security assurance |
| Error handling | Met | validation errors, neutral 403/404/419/429/500/503 pages, illegal-transition messages |
| Reports from actual data | Met | dashboards computed from referral rows; fixture and seeded data |
| APIs documented | Met | `docs/API.md`, `docs/openapi.yaml` |
| Tests executed | Met | numbers above |
| No unresolved critical security issues | Partially | none known after the tests above, but no penetration test; unresolved risks listed in `SECURITY.md` |
| Deployable to staging | Partially | local run verified; Docker/compose/CI written but never built or run |
| Docs complete | Met | ARCHITECTURE, SECURITY, PRIVACY, INTEROPERABILITY, API, DEPLOYMENT, GO-LIVE-GATE, PILOT, USER-GUIDE, KNOWN-LIMITATIONS, DESIGN-FIDELITY |
| Known limitations disclosed | Met | `KNOWN-LIMITATIONS.md` |
| Pilot plan exists | Met | `PILOT.md` (nothing claimed without a pilot) |
| Business + impact metrics defined | Met | `PILOT.md` sections 7-8 (hypotheses, not validated) |

## Outstanding external dependencies
Legal review (Data Protection Act, Digital Health Act), ODPC position and DPIA, facility agreements, MoH engagement (policy is only proposed), clinical-safety review, independent penetration test, real KMHFL access and schema, FHIR validator/HIE integration approval, real antivirus, key management service, Kiswahili clinical review, usability and accessibility testing.

## Not production ready
This build must not be used with real patient data. See `GO-LIVE-GATE.md`.
