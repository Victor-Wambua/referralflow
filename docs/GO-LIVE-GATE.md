# Go-live gate

ReferralFlow must NOT be used with real patient data until every item below is satisfied and signed off by the right people. The software cannot satisfy the legal and organisational items itself.

## How the software enforces this
- `php artisan golive:gate` lists the prerequisites and exits non-zero while any is unmet. At the time of writing 19 of 21 are unmet (the two met items are technical settings that were true in this environment).
- If `APP_ENV=production` or `REFERRALFLOW_MODE=production`, the application refuses to start (web and artisan) unless `REFERRALFLOW_PRODUCTION_ACK` is set to the exact phrase `I-ACKNOWLEDGE-THE-GO-LIVE-GATE-IS-UNMET`. The error lists the unmet prerequisites. Even when acknowledged, every page carries "NOT CLEARED FOR REAL PATIENT DATA" and patient creation still requires the synthetic-person confirmation. The acknowledgement exists for staging rehearsals, not as a way to go live.

## Prerequisites before ANY real patient-data pilot
| Area | Requirement |
|---|---|
| Legal | Legal review of the Data Protection Act 2019 and Digital Health Act 2023 obligations (including whether referral data must be held by or exchanged with the Digital Health Agency, and the retention period) |
| Legal | Position from the Office of the Data Protection Commissioner: registration as controller/processor, and whether a DPIA must be filed before processing |
| Legal | Data Protection Impact Assessment completed and approved |
| Legal | Consent and emergency-processing wording approved by counsel (English and Kiswahili) |
| Organisation | Signed data-sharing and processing agreements with each participating facility |
| Organisation | Engagement with the Ministry of Health referral division (the national referral policy is only proposed; no alignment is claimed) |
| Organisation | A named healthcare pilot partner and a clinical lead |
| Security | Independent penetration test; critical and high findings fixed |
| Security | Keys in a managed secret store; rotation and restore drill rehearsed |
| Security | Audit chain head anchored outside the database (write-once storage) and reviewed on a schedule |
| Security | Incident response and breach-notification procedure agreed and rehearsed |
| Security (technical) | `APP_DEBUG=false`, AES-256-GCM, Argon2id, secure+encrypted sessions, real antivirus bound with `AV_MODE=required`, PostgreSQL on encrypted volumes |
| Clinical | Clinical safety review of the hazard log in `PILOT.md` by a qualified clinician |
| Clinical | Kiswahili copy reviewed by Kenyan clinicians and a certified translator |
| Clinical | Staff training and a downtime (paper) procedure at each facility |
| Operations | Backup and restore drill completed with encrypted backups |

Also needed (not in the automated list): hosting location decision and cross-border position, support and on-call arrangement, accessibility audit with assistive-technology users, usability testing with clinicians.
