# Pilot plan

Nothing in this document is a result. No pilot has taken place, no figure below has been measured, and no impact is claimed. A pilot may only start after `GO-LIVE-GATE.md` is satisfied. Until then the system is run with synthetic data in staging (a "dry-run pilot" with made-up patients is allowed).

## 1. Pilot guide
- Scope: one referral pathway between a small number of facilities (for example 3-5 lower-level facilities referring to one higher-level facility) in one county, chosen with a healthcare partner.
- Duration: 8-12 weeks plus a 2-week preparation and a 2-week review.
- In scope: referral creation, acceptance/decline, scheduling, attendance, counter-referral, messages, directory verification. Out of scope: records of care, billing, claims, patient-facing features, national integrations.
- Roles per facility: at least 2 clinicians, 1 coordinator (plus a backup), 1 facility admin. The platform operator never gets clinical access.
- Governance: weekly 30-minute review of incidents, escalations, break-glass reviews and feedback; a clinical lead who can pause the pilot.
- Downtime procedure: paper referral and a phone call are always allowed; ReferralFlow supplements, never replaces, calling ahead for emergencies. Facilities record paper-fallback use.

## 2. Onboarding
1. Agreements and DPIA outcomes in place (gate). 2. Facility admin created by the platform admin; facility profile and services entered and verified. 3. Staff invited (temporary password, forced change, TOTP setup with recovery codes stored safely). 4. 60-minute training: create a referral with a synthetic patient, send, accept, decline with reason, break-glass and reveal rules, what never to type (names in free text). 5. Supervised first week; a champion per facility. 6. Offline and downtime drill.

## 3. Support workflow
- First line: facility champion; second line: product support (shared inbox, 1 working-day response, urgent same day); third line: engineering.
- Support never asks for patient details: reference ids (RF-XXXXXXXX) only. Screenshots must be masked. Support cannot read referral content.
- Incident classes: P1 (possible patient harm or data exposure: act immediately, pause if needed, follow the incident procedure), P2 (workflow blocked), P3 (cosmetic).
- Account recovery: facility admin resets MFA/password (logged); platform admin for admins.

## 4. Success metrics (to be measured in the pilot, no baseline claimed)
| Metric | Definition | Source |
|---|---|---|
| Time to create a referral | usability test median, target under 2 minutes | observed sessions (not measured yet) |
| Acknowledgement time | median minutes Sent to Received | dashboard |
| Decision time | median minutes Sent to Accepted/Declined | dashboard |
| Loop closure | share of Sent referrals reaching Completed, Declined or Cancelled with a reason | dashboard |
| Counter-referral rate | share of Completed referrals with a summary (100% by design) and time from Attended to Completed | dashboard |
| Incomplete rate and reasons | (declined + cancelled) / terminal; reasons breakdown | dashboard |
| Directory freshness | share of facilities verified in the last 7 and 30 days | directory |
| Escalations | referrals reaching escalation level 1, 2, 3 | audit and dashboard |
| Privacy hygiene | reveals per referral, break-glass count and share judged appropriate | audit |
| Paper fallback | referrals handled on paper while ReferralFlow was available | facility log |

## 5. Feedback collection
Weekly 15-minute check-ins with champions; in-person observation of 5 referral sessions per facility; a short monthly survey (ease of use, time saved or lost, trust in the directory, privacy comfort); a standing "report a change" button for directory errors; every decline reason code reviewed for patterns. Feedback is stored without patient identifiers.

## 6. Risk register
| # | Risk | Likelihood | Impact | Mitigation | Owner |
|---|---|---|---|---|---|
| R1 | Real patient data entered before the gate | medium | severe | production refusal, synthetic confirmation, training, banner | product + partner |
| R2 | Legal position unresolved (DPA/DHA/ODPC) | high | blocks pilot | counsel, DPIA, regulator engagement | founder |
| R3 | Low adoption (staff prefer phone/paper) | high | high | champions, 2-minute flow, phone fallback | partner |
| R4 | Connectivity gaps | high | medium | small payload, no data offline by design (so connectivity still needed to send), phone fallback | product |
| R5 | Directory goes stale | high | medium | verification thresholds, stale warnings, mandatory phone confirm | facility admins |
| R6 | Security incident | low | severe | controls in SECURITY.md, pentest, incident plan | engineering |
| R7 | Misuse of dashboards to rank facilities | medium | medium | small-n suppression, caution, alphabetical order, governance | partner |
| R8 | Dependence on a single operator | medium | medium | documentation, runbooks, backups | founder |
| R9 | Kiswahili copy errors | medium | medium | clinician and translator review before use | partner |

### Clinical-safety hazard log (needs review by a qualified clinician; not reviewed)
| Hazard | Cause | Design control | Residual |
|---|---|---|---|
| H1 Lost referral: sent but never seen | no one watches the inbox | distinct Sent/Received, inbox sorted by priority, SLA reminders and escalation, overdue flag with "consider phoning or referring elsewhere" | reliance on staff checking; no SMS/phone alert is built |
| H2 Wrong facility or service unavailable | stale directory | verification timestamps, stale/unverified warnings, mandatory phone confirmation | verification is manual |
| H3 Delay in emergencies | tool slower than phone | emergency priority, "Call ahead" prompt, paper/phone fallback | no emergency auto-dial |
| H4 Wrong patient | duplicate or look-alike patients | same-facility duplicate detection, masked lists, patient shown on confirm, reveal | no cross-facility matching; no merge tool; spelling variants not matched |
| H5 Patient did not attend, nobody follows up | no feedback loop | required reasons, attended step, counter-referral closes the loop, referrer sees outcome | patient follow-up outside the system |
| H6 Missing clinical information | minimum necessary vs usefulness | optional clinical note and documents, info request messages | clinicians may under-share |
| H7 Over-sharing | too much in free text | explicit "what will be shared" step, notes only after acceptance, warnings against names in text | free text cannot be policed |
| H8 Information changed after sending | patient condition changes | messages on the referral, cancel/withdraw | no structured update mechanism |
| H9 Alarm fatigue or leakage via notifications | noisy or revealing alerts | neutral text, few notification kinds | thresholds not tuned |
| H10 Misreading statuses | colour-only cues | text + shape, plain language | not tested with users |
| H11 System unavailable | outage | paper fallback, health endpoint | no uptime evidence |
| H12 Unauthorised access | weak identity | MFA, tenancy, audit, break-glass review | reviews depend on humans |

## 7. Business model and commercial plan (hypotheses, not validated)
- Facility subscription (per facility per month, tiered by facility level).
- Hospital or county network contract (platform fee plus per-facility onboarding).
- NGO and public-health deployments (project-funded set-up, training and reporting).
- Paid integration and customisation services once approvals exist (for example mapping to an official referral form, HIE adapters).
- No prices are proposed because none have been tested; a willingness-to-pay study with pilot partners is a prerequisite. Costs to model: hosting, support, SMS (if added), security assurance, compliance work.
- Risks: public-sector procurement cycles, competing national systems (policy is still proposed), data-protection compliance cost.

## 8. Impact metrics (to be measured; no claim)
Share of referrals with a recorded outcome; time from referral to first contact; share of referred patients known to have attended; reduction in unacknowledged referrals; clinician-reported time saved; patient-reported waiting (only with ethics review and consent). A before-and-after or comparison design must be agreed with the partner; the pilot is not powered to prove health outcomes.
