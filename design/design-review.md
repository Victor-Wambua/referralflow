# Design Review (against spec 09 and STUDIO.md Phase A)

| Requirement | Status | Where |
|---|---|---|
| A Facility directory with timestamp + verification | Designed + prototyped | directory, facility detail |
| B Referral creation, minimum necessary, consent | Designed + prototyped | new referral steps; supporting documents NOT designed (see gaps) |
| C All nine statuses + reasons for incomplete | Designed + prototyped | tracker, reason dialogs |
| D Communication | Prototyped (messages, request info) | referral detail |
| E Dashboard | Prototyped with caution notes | dashboard |
| F Interoperability | Research only; FHIR-shaped export is a Phase B task | product-brief section 3 |
| G Privacy: RBAC, consent, audit, masking, break-glass, MFA, timeout, retention | Prototyped | login, reveal, break-glass, access log, admin |
| Offline draft + sync UX | Prototyped (simulated) | settings, new referral, home |
| Synthetic data only, no photos | Yes | data.js |

## Honest gaps
1. Supporting document upload/secure viewing not prototyped.
2. Real offline behaviour (service worker, encrypted local store, conflict resolution) is only simulated.
3. Not usability-tested with clinicians; the "under 2 minutes" target is unmeasured (the prototype timer is illustrative).
4. Research limits: KMHFL, Kenya Law/ODPC PDFs and HL7 pages could not be fetched directly (connection refused / 404); legal points come from search summaries and must be verified by counsel. DHA regulations status unconfirmed. No verifiable data on Hello Doctor / Jacaranda Health.
5. Field-level encryption, key management, auditing guarantees are design intent only.
6. Emergency/implied consent wording needs legal review. Swahili localisation not done. Notifications only previewed (no SMS/push delivery design for low-literacy settings).
7. Patient identity matching (duplicates, no national ID) is not designed; Phase B must decide (e.g., Huduma/SHA ID use is a legal question).
8. Accessibility verified by script only.

## Open questions for the founder/counsel
- Does the Digital Health Act require referral data to be held/exchanged through the Digital Health Agency, and what retention applies? (confirm with counsel)
- Is a DPIA and ODPC registration required before a pilot? (confirm with counsel)
- Which clinical roles may see clinical notes at which stage?
- Pilot partner: which county/network and which HMIS do they run?
