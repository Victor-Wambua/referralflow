// Shared helpers for the extra e2e suites (a11y, keyboard, print/perf). Synthetic demo accounts only.
import { chromium } from 'playwright';
import { spawn, execSync } from 'node:child_process';
import { createHash, createHmac } from 'node:crypto';
import { mkdirSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
export const root = resolve(here, '..');
export const PASSWORD = 'Demo-Passphrase-2026';

const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const b32 = (buf) => { let bits = ''; for (const b of buf) bits += b.toString(2).padStart(8, '0'); let out = ''; for (let i = 0; i < bits.length; i += 5) out += B32[parseInt(bits.slice(i, i + 5).padEnd(5, '0'), 2)]; return out; };
const unb32 = (s) => { let bits = ''; for (const c of s) bits += B32.indexOf(c).toString(2).padStart(5, '0'); const out = []; for (let i = 0; i + 8 <= bits.length; i += 8) out.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(out); };
const secretFor = (email) => b32(createHash('sha256').update('demo-totp|' + email).digest().subarray(0, 20));
// Deterministic: derived from the documented demo secret and an explicit time step, never from retries.
export const totp = (email, step) => {
  const msg = Buffer.alloc(8); msg.writeBigUInt64BE(BigInt(step));
  const h = createHmac('sha1', unb32(secretFor(email))).update(msg).digest();
  const o = h[19] & 15;
  const n = ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];
  return String(n % 1000000).padStart(6, '0');
};
export const em = (role, slug) => `${role}.${slug}@demo.referralflow.test`;
export const ROLES = {
  clinician: em('clinician', 'mto'),
  coordinator: em('coordinator', 'ziwani'),
  facility_admin: em('facility_admin', 'baraka'),
  analyst: 'analyst@demo.referralflow.test',
  platform: 'platform@demo.referralflow.test',
};

export async function startApp(port, dbName) {
  const BASE = `http://127.0.0.1:${port}`;
  const DB = resolve(root, `database/${dbName}.sqlite`);
  const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: DB, APP_URL: BASE, MAIL_MAILER: 'log', LOG_LEVEL: 'warning', ARGON_MEMORY: '4096', ARGON_TIME: '2', SESSION_DRIVER: 'database', QUEUE_CONNECTION: 'sync' };
  mkdirSync(resolve(root, 'storage/app/private'), { recursive: true });
  execSync(`rm -f "${DB}" && touch "${DB}"`, { cwd: root });
  execSync('php artisan migrate --force --seed --no-interaction', { cwd: root, env, stdio: 'ignore' });
  const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`, '--no-reload'], { cwd: root, env: { ...env, PHP_CLI_SERVER_WORKERS: '4' }, stdio: 'ignore' });
  const stop = () => { try { server.kill('SIGTERM'); } catch { /* ignore */ } try { execSync(`pkill -f "127.0.0.1:${port}"`); } catch { /* none */ } try { execSync(`rm -f "${DB}"`); } catch { /* ignore */ } };
  process.on('exit', stop);
  for (let i = 0; i < 60; i++) { try { const r = await fetch(BASE + '/health'); if (r.ok) break; } catch { /* wait */ } await new Promise((r) => setTimeout(r, 500)); }
  const exe = process.env.CHROMIUM || `${process.env.HOME}/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`;
  const browser = await chromium.launch(existsSync(exe) ? { executablePath: exe } : {});
  return { BASE, browser, stop };
}

// Each login uses a distinct 30 s step (counter increments per call) so a code is never replayed.
let counter = 0;
export async function login(page, BASE, email) {
  await page.goto(BASE + '/login');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('button:has-text("Continue")')]);
  await page.waitForURL(/\/mfa/);
  // try the current step, then neighbours (clock skew / step already consumed by an earlier context)
  const now = Math.floor(Date.now() / 30000);
  for (const off of [0, -1, 1]) {
    await page.fill('#code', totp(email, now + off));
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('button:has-text("Verify")')]);
    if (!page.url().includes('/mfa')) return;
    counter++;
  }
  throw new Error('MFA failed for ' + email);
}
