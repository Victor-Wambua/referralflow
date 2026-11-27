# Prototype

Self-contained vanilla HTML/CSS/JS in `prototype/` (no CDN, no network calls). Everything is simulated: "Synthetic demonstration data. Simulated design prototype."
Open: `cd tools && node serve.mjs ../design 4460` then http://localhost:4460/prototype/ (hifi gallery `/hifi/`, wireframes `/wireframes/`). Or open `prototype/index.html` directly.
Demo accounts: pick a role on the sign-in screen (Clinician, Facility coordinator, Network manager, Administrator), any 8+ character password, any 6-digit code.
Try: New referral (choose Mlima Mission for the stale warning); Settings > simulate offline then create a referral and Sync; open referral r-7f3a and Reveal; open #/referral/r-91de as clinician for break-glass; coordinator: accept, schedule, decline with reason, send summary; admin: invite, audit filter, retention validation; Settings > Simulate session timeout; dark mode follows the system or Settings.
Files: index.html, styles.css, tokens.css, data.js (synthetic data), app.js. State is in memory (reload resets). Theme preference only is stored in localStorage.
Verification: `tools/clickthrough.mjs` (headless Chromium; 120 checks across mobile/desktop x light/dark: login validation, MFA, wizard validation, stale dialog, consent, send, offline queue and sync, masked/reveal logging, messages, break-glass, directory search/empty, role guard, accept/schedule/decline, admin validation, timeout), results in `screenshots/clickthrough-results.json`.
