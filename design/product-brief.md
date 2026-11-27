# ReferralFlow: Product Brief (Phase A)

Founder: Victor Wambua, Nairobi. Product 9 of 10. Not an EMR: it concentrates on the referral journey between facilities. Requires healthcare partners and regulatory review before any real patient-data pilot. All data in this package is SYNTHETIC. No compliance claim is made anywhere; every legal point is marked "confirm with counsel".

## 1. Problem and one-line proposition
Referrals between Kenyan facilities are fragmented: patients carry paper, receiving facilities are not warned, nobody knows whether the patient arrived, and the referrer rarely hears back. ReferralFlow lets a clinician create a minimum-necessary, consented referral on a phone in under 2 minutes, lets the receiving facility accept/decline/schedule, and closes the loop with a counter-referral summary, while keeping patient identity masked by default and every access logged.

## 2. Research confirmation: Ministry of Health Kenya page (fetched)
URL checked: https://www.health.go.ke/kenya-moves-strengthen-patient-referral-system-through-new-national-policy (fetched successfully).
What it says (as read): dateline Naivasha, 3 March 2026. A **proposed** national referral policy is being **drafted** at a five-day consultative workshop. Work is led by the Division of National Health Referral and Emergency Services under the Directorate of Curative and Nursing Services; technical leadership from Futures Health with named partners (AfyaReach, Johnson & Johnson MedTech, Living Goods, Jhpiego, Amref Health Africa). It aims to address "fragmentation within Kenya's referral system"; the proposal covers governance structures, digital interoperability standards, accountability mechanisms and financing linkages, defining levels of care, forward and backward referral pathways, movement of patients, specimens and expertise, and alignment with Social Health Authority (SHA) entitlements. No numeric figures appear on the page.
Mismatch/clarification vs. the founder spec: the spec says MoH "is developing" a policy: consistent. But the policy is **not adopted**; ReferralFlow must not claim alignment with it. Design stays configurable so it can adapt when the policy is published.

## 3. Research findings (what was actually read; confidence stated)
All legal/regulatory items: **confirm with counsel**. No compliance claim.
| Topic | What I found | Source quality | Design consequence |
|---|---|---|---|
| Digital Health Act, 2023 (No. 15 of 2023) | A web search summary of the Kenya Law text and commentary says it establishes a Digital Health Agency, aims at safe use of technology and privacy/confidentiality/security of health data, makes the Agency custodian of health data, and (reportedly, s.25) requires retention of at least 20 years; s.18 and s.61 tie it to data-protection principles/the Data Protection Act. Draft Digital Health (Health Information Management) Regulations were published by MoH in Nov 2024; I did NOT confirm whether finalised. | Search result summaries of primary and secondary sources; I did not read the Act PDF myself. | Retention settings are configurable and carry a counsel banner (20-year retention NOT assumed). Health-data custodianship and any required exchange with national systems is an open question. |
| Data Protection Act 2019 | Search summaries: s.2 defines health data and lists health status as sensitive personal data; sensitive data processing is gated (consent/other lawful grounds; full list not retrieved); DPIA concept in s.31; ODPC has a Guidance Note on Processing of Health Data (2024) that treats a DPIA as required for health processing. Direct fetch of the ODPC Act page returned 404. | Search summaries only. | Minimum-necessary data, explicit consent capture, DPIA to be done before any pilot (listed in design-review). Section numbers must be verified by counsel. |
| Kenya Master Facility List (KMHFL/KMHFR) | Direct fetch of kmhfl.health.go.ke failed (connection refused). Search results: a public API exists at the MoH KMHFR domain; implementation guide (Jan 2020) says facility KEPH level is 2-6 (gazette notice or licence) and a services catalogue exists; a third-party pipeline describes ~20,000 facility records with code, KEPH level, type, ownership, location, service flags; docs are unofficial and dated. | Secondary; endpoint paths unverified. | Directory is designed with an MFL code field and an adapter boundary; services availability and "verified by/at" are ReferralFlow-owned because the registry publishes facility attributes, not live availability (as far as read). |
| KHIS / DHIS2 | KHIS is DHIS2-based; one 2026 report (secondary) says it is used in ~95% of facilities. | Secondary. | Dashboard aggregates could later feed KHIS-style indicators; not designed now. |
| Interoperability: Kenya HIE, FHIR | Secondary sources: FHIR is the stated direction (National eHealth Policy 2016-2030 reportedly mandates global standards incl. FHIR; NHIIF is a Digital Health Agency responsibility); a Kenya HIE / Health Information Mediator on OpenHIE architecture has been piloted; maturity assessments show interoperability at nascent/emerging stage; a vendor-published Kenya Patient Summary FHIR IG exists. | Secondary, vendor-influenced. | Phase B builds a FHIR-shaped ServiceRequest/Task export, labelled "unverified against national systems". No access to national systems is assumed. (My direct fetch of the HL7 ReferralRequest page returned 404, so I rely on prior knowledge that FHIR R4 uses ServiceRequest for referrals: to verify.) |
| SHA / Taifa Care / Afya Yangu | Search results: SHA claims moved from a Provider Portal to the Taifa Care HMIS (report dated mid-2026); Afya Yangu is a patient-facing platform for registration/cover; Taifa Care Support Centre (toll-free 147). No source found on how referrals move through these. | Secondary news. | ReferralFlow does not touch claims. Referral reasons may later map to SHA entitlements (open question). |
| Referral forms / MOH 100 | **MOH 100 is the Community Referral Form** (community health volunteer to facility, with feedback back), not a facility-to-facility form. Fields as publicly copied: date/time, name, sex, age, community unit, reason, treatment given, comments, link facility, referring CHV details, receiving officer, feedback section B. A MoH-linked assessment says most facility referral forms capture client name/ID, age, sex, receiving facility, date, reason. No official template read; copies vary. | Copies on third-party sites. | Referral form fields are a configurable schema, not a copy of any form. Spec brief's idea of "MOH 100" as the referral form is partly mismatched: the counter-referral "Section B" concept is the useful pattern. |
| Peers | OpenHIE community discusses a referral registry/workflow; a Tanzanian referral app built on OpenSRP with OpenMRS backend (wiki session page); Medic is prototyping loss-to-follow-up with MoH Kenya over OpenHIM/FHIR; OpenMRS FHIR2 module exposes Patient/Encounter/Observation. I found no published evaluation quantifying lost referrals in an African e-referral system, and nothing verifiable on Hello Doctor/Jacaranda Health. | Mixed; partial. | Differentiator: facility-to-facility closed loop, verified directory, privacy UX. We claim no market statistic. |
| Not fetched / not found | Kenya Law Act PDFs, Kenya Health Information Systems Policy text, DHA regulations final status, KMHFL live API, HL7 pages. | n/a | Listed as gaps; verify before Phase B spec. |

## 4. Clinical-safety risks and design responses
- Lost referral (sent but never seen): statuses Sent/Received are distinct; unacknowledged Emergency/Urgent surface at the top of the inbox; dashboard shows pending age. Phone escalation prompt for Emergency.
- Wrong facility or service unavailable: verified-at timestamps, stale warnings, mandatory confirm-by-phone step for stale/unverified facilities.
- Delay: priority levels, turnaround metrics, no silent states.
- Patient did not attend / no feedback: required reasons for every incomplete outcome; counter-referral summary closes the loop.
- Wrong patient: masked-by-default lists, reveal needed only when necessary; patient ID shown on confirm.
- Over-sharing: minimum-necessary form, explicit "what will be shared" review, clinical note optional and encrypted.
- Offline duplicate/lost: encrypted device outbox, visible queue, reference IDs; idempotent sync (Phase B).
- Alarm fatigue/PHI leakage: neutral notifications.
- Misuse of metrics: small-n suppression, caution notes, no ranking language.

## 5. Users
Referring clinician (nurse/clinical officer/doctor at low-level facility; phone/tablet, intermittent network); receiving facility coordinator (desk, desktop or tablet); network/county manager (aggregates); administrator (users, facilities, audit, retention). Patients are data subjects, not users in v1.

## 6. Scope (MVP design) and non-goals
In scope: A-G of the spec (directory, creation, tracking, communication, dashboard, FHIR-shaped export plan, privacy). Non-goals: EMR features, billing/claims, patient app, real national integrations, real patient data.

## 7. Success metrics (draft, to be measured in a future pilot, no baseline claimed)
Time to create referral (target under 2 minutes in usability test), share of referrals with a recorded outcome, share closed with counter-referral, median time to accept, directory entries verified in last 30 days, reveal/break-glass rates.

## 8. Commercial
Facility subscriptions; hospital network contracts; NGO/public-health deployments; paid integration services once approvals exist.

## 9. Brand identity
Name: ReferralFlow. Mark: a filled dot connected by a returning curve to a ring: a referral that goes out and comes back (loop closure). Palette: dusk indigo on warm paper (not the green/teal default of health apps), amber and red reserved for warnings. Type: Iowan/Palatino/Georgia display for calm authority, system sans for UI. Voice: plain, short, reassuring.
