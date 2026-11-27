# ReferralFlow

Referral coordination between health facilities: a minimum-necessary, consented referral that the receiving facility accepts or declines, schedules and completes with a counter-referral summary. Privacy-forward (masked identifiers, logged reveal, break-glass, audit chain, field encryption).

**SYNTHETIC DATA ONLY. NOT PRODUCTION READY. No compliance claim.** It must not be used with real patient data (see `docs/GO-LIVE-GATE.md`; the application refuses to start in production mode).

Product 9 of 10 in the Product Studio (founder: Victor Wambua). Stack: Laravel 13, PHP 8.4, SQLite (dev/test) with Postgres-portable migrations, Blade + vanilla JS (Vite), Pest, Pint, Larastan, Playwright.

## Run it
```
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # 10 synthetic facilities, 32 users, 200 referrals
php artisan serve --port=4461
```
Open http://localhost:4461. Demo password for all seeded accounts: `Demo-Passphrase-2026` (synthetic, demo only). Accounts: `clinician.mto@demo.referralflow.test`, `coordinator.ziwani@demo.referralflow.test`, `facility_admin.baraka@demo.referralflow.test`, `analyst@demo.referralflow.test`, `platform@demo.referralflow.test` (pattern `<role>.<facility>@demo.referralflow.test`; facilities: mto, ziwani, mlima, bonde, kivuli, pwani, rafiki, nyota, jua, baraka). Every sign-in needs a TOTP code: `php artisan demo:totp <email>` prints it (local only).

## Test it
```
./vendor/bin/pest                 # 133 tests
./vendor/bin/pint --test
./vendor/bin/phpstan analyse      # Larastan level 5
composer audit && npm audit
cd e2e && npm install && node run.mjs   # Playwright e2e (starts its own server on 4461, uses database/e2e.sqlite)
php artisan golive:gate           # lists unmet go-live prerequisites (exits non-zero)
```

## Useful commands
`referrals:escalate`, `retention:purge [--dry-run]`, `audit:verify`, `directory:import file.csv [--dry-run]`, `encryption:rotate [--dry-run]`, `golive:gate`, `demo:totp`.

## Documentation
`docs/`: DECISIONS, ARCHITECTURE, SECURITY, PRIVACY, INTEROPERABILITY, API (+ openapi.yaml), DEPLOYMENT, GO-LIVE-GATE, PILOT, USER-GUIDE, KNOWN-LIMITATIONS, DESIGN-FIDELITY, ACCEPTANCE-REPORT. Design package: `design/` (including `design/addenda.md` for document upload, identity matching, Kiswahili and offline behaviour).
