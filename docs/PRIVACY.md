# Privacy

All data in this system is SYNTHETIC. Nothing here is legal advice or a compliance claim. Every legal point is marked "confirm with counsel". Kenya's Data Protection Act 2019 and the Digital Health Act 2023 (and draft regulations) are relevant; their requirements for health data, DPIAs, controller/processor registration, custodianship and retention have NOT been verified by counsel.

## Data map
| Data | Where | Encrypted | Who can read it | Retention (placeholder) |
|---|---|---|---|---|
| Patient name, date of birth, identifier (type + value) | `patients` | yes (field level) | referring-facility clinicians and coordinators; receiving facility only after acceptance and via reveal | until the data-subject request, legal hold or retention purge |
| Identifier match keys | `patients.identifier_hash`, `name_dob_hash` | keyed HMAC, not reversible | system only | with the patient row |
| Sex and age in years at creation | `referrals` | no (minimum shown before acceptance) | both facilities after Sent | skeleton kept after purge for aggregates |
| Reason category | `referrals.reason_code` | no | both facilities; used in aggregates | kept |
| Reason text, clinical note, form data | `referrals` | yes | note and form only after acceptance | closed referral period |
| Messages | `messages` | yes | both facilities | message period |
| Counter-referral summary | `counter_referrals` | yes | both facilities | closed referral period |
| Documents | private disk | yes (file contents and name) | referrer; receiver after acceptance | attachment period |
| Consent record | `consents` | witness name encrypted | both facilities (basis and time) | with the referral |
| Status events | `referral_events` | notes encrypted | both facilities | kept |
| Audit entries | `audit_entries` | no PHI by design (ids and codes only) | role-scoped | never purged by the application |
| Staff accounts | `users` | MFA secret encrypted; password Argon2id | admins | until deactivated |
| Notifications and mail | `notifications`, mail | n/a | staff | no PHI inside |

## Minimisation
Referral creation collects the minimum: sex, age, priority, one-line reason, optional note, optional documents, form fields from the active schema. Before the receiving facility accepts, it sees only priority, reason, sex and age. The "what will be shared" review step names exactly what leaves. Patients are owned by the referring facility; there is no cross-facility patient index.

## Consent
A consent record (basis, who recorded it, time, whether the clinical note is included, wording version) is required before send and is stored per referral. Bases: written, verbal witnessed, guardian, and "emergency: patient unable to consent". The emergency basis and the wording are placeholders that need counsel. Consent can be withdrawn by the referring facility: sharing stops (the receiving facility keeps only the reference and status) and an unattended referral is cancelled.

## Masking and reveal
Lists and detail pages show initials and a masked identifier. Reveal needs a reason from a controlled list, is rate limited, expires after five minutes, and writes an audit entry. Names, identifiers and clinical terms never appear in URLs, page titles, notification text, email, application logs or error pages (tests assert this).

## Data-subject workflow
A clinician or coordinator opens an export or deletion request for a patient; the facility admin decides after re-authentication. Export releases a JSON package once and is audited with a flag. Deletion is refused while a legal hold or an open referral exists, otherwise it removes the patient record and clinical content of their referrals (keeping the non-identifying skeleton). Legal duties to keep health records may override erasure: confirm with counsel. A deletion removes the shared referral content for the receiving facility as well; whether a receiving facility must retain its own copy is a legal question.

## Retention
Configurable periods (placeholders: closed referral content 365 days, messages 365, documents 365, unsent drafts 30). `retention:purge` (scheduled daily) removes clinical content from closed referrals past their period, respects legal holds and keeps the skeleton so dashboards stay reproducible. The Digital Health Act may require much longer retention (a figure of 20 years was reported in secondary sources that were not verified).

## Open privacy items (go-live gate)
DPIA; ODPC position and registration; whether referral data must be held by or exchanged with the Digital Health Agency; lawful basis and consent wording; data-sharing agreements between facilities; breach notification process; Kiswahili consent text; cross-border processing position if hosting is outside Kenya.
