# Screen Specifications (route, purpose, content, states, rules)

| Screen | Route | Spec |
|---|---|---|
| Sign in | #/login | Account + password; inline error; step 2 six-digit MFA (all roles). Error: focus moves to field, aria-live. Timeout message variant. |
| Home (clinician) | #/home | Greeting, facility, large New referral, outbox banner (Sync now), Needs attention (Declined, info requested), Recent. Empty: "Nothing waiting". |
| New referral | #/new | Steps: 1 patient (required), 2 priority + reason (required, 240 chars) + include note toggle, 3 destination with verification pill (required; stale/unverified triggers confirm dialog), 4 what-is-shared review + consent (required) + Send/Save to outbox. Elapsed timer. Draft saved. Result screen with reference. |
| Referrals | #/referrals | Search by reason/ID only (not names), status filter, open-by-ID (leads to locked page if not party), empty state with clear filters. |
| Referral detail | #/referral/:id | Header (id, priority, status), consent banner, tracker, actions by role/status, tabs Overview/Messages/History, masked patient with Reveal (reason required, logged), encrypted clinical note rules, counter-referral card, break-glass lock for non-parties, aggregate-only view for managers. |
| Decline / Cancel / Did not attend | dialog | Required reason list + optional note. Confirm. |
| Request info / Schedule / Send summary | dialog | Required text/date; validated. |
| Inbox | #/inbox | Counts by bucket, priority-sorted list, empty "All clear". Opening a Sent item marks it Received (logged). |
| Directory | #/directory | Search, type, verification filters; rows with verification pill; empty state. |
| Facility | #/facility/:id | Verification banner (by/at, stale warning), contact/hours, referral capabilities, services with availability, Report change, Mark verified (own coordinator or admin). |
| Dashboard | #/dashboard | 4 stat tiles, weekly volume chart, incomplete reasons, facility comparison with caution banner and n<5 suppression, aggregate export (logged). Access: coordinator, manager, admin. |
| Access log | #/access | Own (clinician) or facility log; break-glass highlighted. |
| Admin users | #/admin/users | Table, invite (email validated), edit role, reset MFA, suspend. MFA locked on. |
| Admin facilities | #/admin/facilities | List with verification, register facility (unverified on creation). |
| Audit explorer | #/admin/audit | Search + action filter, export, immutable rows. |
| Retention | #/admin/retention | Numeric fields validated, MFA locked, counsel banner. |
| Notifications | #/notifications | Neutral wording examples. |
| Settings | #/settings | Theme, offline simulation, session timeout demo, sign out. |
| States | #/states | Loading, empty, error, success, offline, forbidden. |
Cross-cutting: session timeout dialog (60 s), no PHI in titles/URLs, role guards ("No access" page), live region for status messages.
