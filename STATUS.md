# ReferralFlow: STATUS
Phase A (design): complete (see design/). Phase B (build): complete as a STAGING / SYNTHETIC-DATA build; NOT production ready; no compliance claim; no real patient data; nothing deployed, pushed or paid for.
Code and tests: Laravel 13 app. Pest 133 tests (10,811 assertions) pass; Playwright e2e 55/55; Pint pass; Larastan level 5 clean; composer/npm audit clean. Coverage not measured (no driver). Docker/Postgres/CI written, not run.
Ports 4460-4479 (app 4461, prototype 4460).
Run: see README.md. Evidence and gaps: docs/ACCEPTANCE-REPORT.md, docs/KNOWN-LIMITATIONS.md. Go-live blockers: docs/GO-LIVE-GATE.md (healthcare partner, legal review/DPIA/ODPC, clinical-safety review, pentest, MoH engagement, real KMHFL access).
