# Deployment (STAGING ONLY, SYNTHETIC DATA ONLY)

Not deployed anywhere. Nothing here was run on the development machine except the local PHP server. Docker and PostgreSQL are not available on that machine, so `Dockerfile` and `docker-compose.yml` were written but NOT built or run.

## Local run (verified on the dev machine)
```
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan serve --port=4461
```
Demo accounts (synthetic): `clinician.mto@demo.referralflow.test`, `coordinator.ziwani@demo.referralflow.test`, `facility_admin.baraka@demo.referralflow.test`, `analyst@demo.referralflow.test`, `platform@demo.referralflow.test`; password `Demo-Passphrase-2026` (demo only). Every account needs a TOTP code: for demo accounts print the current code with `php artisan demo:totp <email>` (works only in local/testing environments) or compute it from the seeded secret.

## Staging with Docker (written, not run)
1. `cp .env.example .env`; set `APP_KEY` (`php artisan key:generate --show`), `DB_PASSWORD`, `APP_URL`, `SESSION_SECURE_COOKIE=true`; keep `REFERRALFLOW_MODE=staging`, `APP_DEBUG=false`.
2. `docker compose up -d --build`; then `docker compose exec app php artisan migrate --force --seed` (seed only on staging).
3. Put TLS in front (load balancer or reverse proxy), redirect HTTP to HTTPS, enable HSTS (the app sends it over HTTPS).
4. The `scheduler` service runs `schedule:work`: escalation every 5 minutes, retention purge at 02:30, audit verification at 03:00.

## Environment variables
`APP_KEY`, `APP_PREVIOUS_KEYS`, `APP_CIPHER`, `BLIND_INDEX_KEY`, `REFERRALFLOW_MODE`, `REFERRALFLOW_PRODUCTION_ACK`, `AV_MODE`, `HASH_DRIVER`, `ARGON_MEMORY`, `ARGON_TIME`, `DB_*`, `SESSION_LIFETIME` (idle minutes), `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `MAIL_*`, `QUEUE_CONNECTION`, `CACHE_STORE`. See `.env.example`.

## Database
SQLite for development and tests; PostgreSQL 16 for staging. Run `php artisan migrate --force`. Migrations avoid vendor-specific column types; the audit triggers are created for SQLite and PostgreSQL only (MySQL is not supported: the append-only protection would be missing).

## Health endpoint
`GET /health` returns `{status, database, mode, synthetic_data_only}` (no patient data). Use it for load balancer checks.

## Production mode is refused
Setting `APP_ENV=production` or `REFERRALFLOW_MODE=production` makes every artisan and web boot fail unless `REFERRALFLOW_PRODUCTION_ACK` equals the acknowledgement phrase, and even then a permanent "NOT CLEARED FOR REAL PATIENT DATA" banner is shown and `php artisan golive:gate` stays red. See `GO-LIVE-GATE.md`.

## Backup and restore
- Back up: the database (PostgreSQL `pg_dump` or the SQLite file) AND `storage/app/private` (encrypted documents). Both are ciphertext at the field/file level, but the database also holds metadata in clear text.
- Keys: `APP_KEY` (and any `APP_PREVIOUS_KEYS`, `BLIND_INDEX_KEY`) are NOT in the backups and must be stored separately in a secret manager with access control. A backup without its key is unreadable; a key stored beside its backup defeats the encryption.
- Encrypt backups at rest (volume or tool-level encryption) and restrict who can restore.
- Restore: provision an empty database, restore the dump, restore `storage/app/private`, set the SAME `APP_KEY`/`APP_PREVIOUS_KEYS`, run `php artisan migrate --force` (no-op if current), then `php artisan audit:verify` and `php artisan encryption:rotate --dry-run` (the dry run proves every encrypted value and file decrypts with the keys you supplied).
- A restore drill has NOT been performed (go-live item).
- Retention vs backups: purged clinical content remains in older backups until they expire; define backup retention with counsel.

## Logs
Application logs must contain no patient data (tested for the paths exercised). Keep `LOG_LEVEL` at `warning` or above in staging. Do not enable query logging.
