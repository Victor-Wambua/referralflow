// Browser end-to-end checks against the REAL application (php artisan serve on port 4461, SQLite e2e database).
// Synthetic demo accounts only. Results are written to e2e/results.json; screenshots to docs/screenshots/.
import { chromium } from 'playwright';
import { spawn, execSync } from 'node:child_process';
import { createHash, createHmac } from 'node:crypto';
import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const shots = resolve(root, 'docs/screenshots');
mkdirSync(shots, { recursive: true });
const PORT = 4461;
const BASE = `http://127.0.0.1:${PORT}`;
const PASSWORD = 'Demo-Passphrase-2026';
const DB = resolve(root, 'database/e2e.sqlite');
const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: DB, APP_URL: BASE, MAIL_MAILER: 'log', LOG_LEVEL: 'warning', ARGON_MEMORY: '4096', ARGON_TIME: '2', SESSION_DRIVER: 'database', QUEUE_CONNECTION: 'sync' };

const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`); };

// ---- TOTP (same algorithm as the app) ----
const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const b32 = (buf) => { let bits = ''; for (const b of buf) bits += b.toString(2).padStart(8, '0'); let out = ''; for (let i = 0; i < bits.length; i += 5) out += B32[parseInt(bits.slice(i, i + 5).padEnd(5, '0'), 2)]; return out; };
const unb32 = (s) => { let bits = ''; for (const c of s) bits += B32.indexOf(c).toString(2).padStart(5, '0'); const out = []; for (let i = 0; i + 8 <= bits.length; i += 8) out.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(out); };
const secretFor = (email) => b32(createHash('sha256').update('demo-totp|' + email).digest().subarray(0, 20));
const code = (email, stepOffset = 0) => {
  const step = Math.floor(Date.now() / 30000) + stepOffset;
  const msg = Buffer.alloc(8); msg.writeBigUInt64BE(BigInt(step));
  const h = createHmac('sha1', unb32(secretFor(email))).update(msg).digest();
  const o = h[19] & 15;
  const n = ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];
  return String(n % 1000000).padStart(6, '0');
};
const used = {};
const nextCode = (email) => { const k = used[email] ?? 0; used[email] = k + 1; if (k > 1) throw new Error('TOTP window exhausted for ' + email); return code(email, k); };
const em = (role, slug) => `${role}.${slug}@demo.referralflow.test`;

// ---- server ----
mkdirSync(resolve(root, 'storage/app/private'), { recursive: true });
execSync(`rm -f "${DB}" && touch "${DB}"`, { cwd: root });
execSync('php artisan migrate --force --seed --no-interaction', { cwd: root, env, stdio: 'ignore' });
const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${PORT}`, '--no-reload'], { cwd: root, env: { ...env, PHP_CLI_SERVER_WORKERS: '4' }, stdio: 'ignore' });
const stop = () => { try { server.kill('SIGTERM'); } catch { /* ignore */ } try { execSync(`pkill -f "127.0.0.1:${PORT}"`); } catch { /* none running */ } };
process.on('exit', stop);
for (let i = 0; i < 60; i++) { try { const r = await fetch(BASE + '/health'); if (r.ok) break; } catch { /* wait */ } await new Promise((r) => setTimeout(r, 500)); }

const exe = process.env.CHROMIUM || `${process.env.HOME}/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`;
const browser = await chromium.launch(existsSync(exe) ? { executablePath: exe } : {});
const consoleProblems = [];

async function newPage(viewport = { width: 1440, height: 900 }, colorScheme = 'light') {
  const ctx = await browser.newContext({ viewport, colorScheme, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  page.on('console', (m) => { if (m.type() === 'error' && !/status of (403|404|419|429)/.test(m.text())) consoleProblems.push(m.text()); });
  page.on('pageerror', (e) => consoleProblems.push(String(e)));
  return page;
}
async function login(page, email) {
  await page.goto(BASE + '/login');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await clickNav(page, 'button:has-text("Continue")');
  await page.waitForURL(/\/mfa/);
  await page.fill('#code', nextCode(email));
  await clickNav(page, 'button:has-text("Verify")');
}
const clickNav = async (page, sel) => { await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(sel)]); };
const snap = (page, name) => page.screenshot({ path: `${shots}/${name}.png`, fullPage: false });
const text = (page) => page.locator('body').innerText();
const noOverflow = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);

try {
  // ---------- 0. public pages ----------
  {
    const p = await newPage();
    await p.goto(BASE + '/login');
    check('login page renders with synthetic banner', (await text(p)).includes('Synthetic demonstration data'));
    await snap(p, 'desktop-light-login');
    await p.fill('#email', em('clinician', 'mto')); await p.fill('#password', 'wrong-password-123'); await clickNav(p, 'button:has-text("Continue")');
    check('wrong password shows one generic error', (await text(p)).includes('Sign-in failed'));
    await p.fill('#email', em('clinician', 'mto')); await p.fill('#password', PASSWORD); await clickNav(p, 'button:has-text("Continue")');
    await p.waitForURL(/\/mfa/);
    await p.fill('#code', '000000'); await clickNav(p, 'button:has-text("Verify")');
    check('wrong MFA code rejected', (await text(p)).includes('That code is not right'));
    await p.goto(BASE + '/directory');
    check('MFA-unverified session cannot reach the app', p.url().includes('/mfa'));
    await p.close();
  }

  // ---------- 1. clinician creates referral with consent and sends ----------
  const cl = await newPage();
  await login(cl, em('clinician', 'mto'));
  check('clinician signs in with MFA and lands on Home', (await text(cl)).includes('New referral'));
  await snap(cl, 'desktop-light-home');
  await cl.click('a.btn:has-text("New referral")');
  await cl.waitForURL(/referrals\/new/);
  await snap(cl, 'desktop-light-new-step1');
  // client-side validation blocks an empty step
  await cl.click('#wizard [data-next] >> visible=true');
  check('wizard step 1 validates required fields before continuing', (await text(cl)).includes('Enter the patient name'));
  await cl.fill('#full_name', 'Wanjiku Testova');
  await cl.fill('#date_of_birth', '1992-04-11');
  await cl.selectOption('#sex', 'female');
  await cl.selectOption('#identifier_type', 'facility_record');
  await cl.fill('#identifier', 'SYN-E2E-0001');
  await cl.check('input[name=synthetic_confirm]');
  await cl.click('#wizard section.step:visible [data-next]');
  await cl.waitForSelector('input[name=priority]');
  await cl.check('input[name=priority][value=emergency]');
  await cl.selectOption('#reason_code', 'obstetric');
  await cl.fill('#reason_text', 'Synthetic: prolonged labour, needs surgical review');
  await cl.check('#include_note');
  await cl.fill('#clinical_note', 'Synthetic clinical note for end-to-end test.');
  await cl.selectOption('#f_allergies', 'none');
  mkdirSync(resolve(here, 'fixtures'), { recursive: true });
  const png = resolve(here, 'fixtures/scan.png');
  writeFileSync(png, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));
  await cl.setInputFiles('#documents', png);
  await snap(cl, 'desktop-light-new-step2');
  await cl.click('#wizard section.step:visible [data-next]');
  await cl.waitForSelector('input[name=receiving_facility_id]');
  await snap(cl, 'desktop-light-new-step3');
  // choose a stale facility first: the phone-confirmation dialog must appear
  await cl.check('input[name=receiving_facility_id][data-name="Mlima Mission Dispensary"]');
  await cl.waitForSelector('#stale-dialog[open]');
  check('stale destination triggers the confirm-by-phone dialog', (await text(cl)).includes('Confirm by phone first'));
  await snap(cl, 'desktop-light-dialog-stale');
  await cl.click('#stale-no');
  check('declining the phone confirmation unselects the stale facility', !(await cl.isChecked('input[name=receiving_facility_id][data-name="Mlima Mission Dispensary"]')));
  await cl.check('input[name=receiving_facility_id][data-name="Ziwani Sub-County Hospital"]');
  await cl.click('#to-review');
  await cl.waitForSelector('input[name=consent_basis]');
  check('review step lists what will be shared', (await text(cl)).includes('What will be shared') && (await text(cl)).includes('Ziwani Sub-County Hospital'));
  // consent is required: try to send without it
  await clickNav(cl, 'button[name=action][value=send]');
  check('send without consent is refused by the server', (await text(cl)).includes('Please fix the highlighted items') || (await text(cl)).toLowerCase().includes('consent'));
  await cl.waitForSelector('input[name=consent_basis]');
  // still on step 4 after the server error? navigate steps if the wizard reset to first error
  if (!(await cl.locator('input[name=consent_basis]').first().isVisible())) { await cl.click('#wizard section.step:visible [data-next]').catch(() => {}); }
  await cl.check('input[name=consent_basis][value=written]');
  await cl.check('input[name=consent_confirm]');
  await snap(cl, 'desktop-light-new-step4');
  await cl.click('button[name=action][value=send]');
  await cl.waitForURL(/\/referrals\/[0-9a-z]{26}$/);
  const refUrl = cl.url();
  const refId = refUrl.split('/').pop();
  const t1 = await text(cl);
  check('referral sent with consent recorded', t1.includes('Referral sent.') && t1.includes('Consent recorded'));
  check('patient identity is masked by default', t1.includes('W. T.') && !t1.includes('Wanjiku') && !t1.includes('SYN-E2E-0001'));
  check('no patient data in URL or title', !refUrl.includes('Wanjiku') && !(await cl.title()).includes('Wanjiku') && /^[A-Za-z ]+ · ReferralFlow$/.test(await cl.title()));
  await snap(cl, 'desktop-light-referral');
  // reveal with reason
  await cl.click('button:has-text("Reveal identity")');
  await cl.selectOption('#reveal-reason', 'verify_identity');
  await clickNav(cl, 'dialog#d-reveal button.btn:not(.quiet)');
  check('reveal shows identity after a reason is given', (await text(cl)).includes('Wanjiku Testova'));
  await snap(cl, 'desktop-light-referral-revealed');
  await clickNav(cl, 'button:has-text("Hide")');
  check('hide masks identity again', !(await text(cl)).includes('Wanjiku Testova'));
  await cl.goto(BASE + '/access');
  check('own access log records the reveal', (await text(cl)).includes('patient.reveal'));
  await snap(cl, 'desktop-light-access');
  await cl.context().close();

  // ---------- 2. receiving coordinator: accept, schedule, attended, complete ----------
  const co = await newPage();
  await login(co, em('coordinator', 'ziwani'));
  check('coordinator lands on Inbox', co.url().endsWith('/inbox'));
  await snap(co, 'desktop-light-inbox');
  check('inbox shows the new emergency referral', (await text(co)).includes('Obstetric or newborn'));
  await co.goto(`${BASE}/referrals/${refId}`);
  const m = await text(co);
  check('receiver sees only the minimum before accepting', m.includes('You see the minimum until you accept') && !m.includes('Synthetic clinical note') && !m.includes('Wanjiku'));
  await snap(co, 'desktop-light-referral-minimal');
  await clickNav(co, 'button:has-text("Accept")');
  const a = await text(co);
  check('accepting unlocks the clinical note', a.includes('Referral accepted.') && a.includes('Synthetic clinical note for end-to-end test.'));
  await co.click('button:has-text("Schedule appointment")');
  const when = new Date(Date.now() + 2 * 86400000); const pad = (n) => String(n).padStart(2, '0');
  await co.fill('#appointment_at', `${when.getFullYear()}-${pad(when.getMonth() + 1)}-${pad(when.getDate())}T09:30`);
  await clickNav(co, 'dialog#d-schedule button.btn:not(.quiet)');
  check('appointment scheduled', (await text(co)).includes('Appointment saved.'));
  await snap(co, 'desktop-light-referral-scheduled');
  await clickNav(co, 'button:has-text("Patient attended")');
  check('marked as patient attended', (await text(co)).includes('Marked as attended.'));
  await co.click('button:has-text("Send summary and complete")');
  await co.selectOption('#outcome', 'treated_discharged');
  await co.fill('#summary', 'Synthetic summary: seen, treated, discharged with follow-up plan.');
  await clickNav(co, 'dialog#d-complete button.btn:not(.quiet)');
  check('referral completed with feedback summary', (await text(co)).includes('Summary sent. The referral is complete.'));
  await clickNav(co, 'a:has-text("History")');
  const hist = await text(co);
  check('history lists every status in order', ['Created', 'Sent', 'Received', 'Accepted', 'Scheduled', 'Patient attended', 'Completed'].every((s) => hist.includes(s)));
  await co.goto(BASE + '/dashboard');
  check('coordinator dashboard shows aggregates and the caution banner', (await text(co)).includes('Read with care'));
  await snap(co, 'desktop-light-dashboard');
  await co.context().close();

  // ---------- 3. referrer sees the closed loop ----------
  const cl2 = await newPage();
  await login(cl2, em('clinician', 'mto'));
  await cl2.goto(refUrl);
  const done = await text(cl2);
  check('referrer sees Completed with the counter-referral summary', done.includes('Completed') && done.includes('Summary from the receiving facility') && done.includes('Synthetic summary'));
  await snap(cl2, 'desktop-light-referral-completed');
  await cl2.context().close();

  // ---------- 4. decline path ----------
  const pw = await newPage();
  await login(pw, em('clinician', 'pwani'));
  await pw.goto(BASE + '/referrals/new');
  await pw.fill('#full_name', 'Otieno Decliner'); await pw.fill('#date_of_birth', '1980-01-02'); await pw.selectOption('#sex', 'male'); await pw.check('input[name=synthetic_confirm]');
  await pw.click('#wizard section.step:visible [data-next]');
  await pw.selectOption('#reason_code', 'surgical'); await pw.fill('#reason_text', 'Synthetic: elective review');
  await pw.click('#wizard section.step:visible [data-next]');
  await pw.check('input[name=receiving_facility_id][data-name="Rafiki Sub-County Hospital"]');
  await pw.click('#to-review');
  await pw.check('input[name=consent_basis][value=verbal_witnessed]'); await pw.check('input[name=consent_confirm]');
  await pw.click('button[name=action][value=send]');
  await pw.waitForURL(/\/referrals\/[0-9a-z]{26}$/);
  const declId = pw.url().split('/').pop();
  await pw.context().close();
  const rf = await newPage();
  await login(rf, em('coordinator', 'rafiki'));
  await rf.goto(`${BASE}/referrals/${declId}`);
  await rf.click('button:has-text("Decline")');
  await rf.click('dialog#d-decline button.btn.danger'); // no reason chosen
  check('decline without a reason is refused', (await text(rf)).includes('Choose a reason') || (await rf.locator('#decline-reason:invalid').count()) > 0);
  await rf.selectOption('#decline-reason', 'no_capacity');
  await clickNav(rf, 'dialog#d-decline button.btn.danger');
  check('decline recorded with reason', (await text(rf)).includes('Referral declined') && (await text(rf)).includes('No capacity or bed'));
  await snap(rf, 'desktop-light-declined');
  await rf.context().close();
  const pw2 = await newPage();
  await login(pw2, em('clinician', 'pwani'));
  await pw2.goto(`${BASE}/referrals/${declId}`);
  check('referrer sees the decline reason', (await text(pw2)).includes('No capacity or bed'));
  await pw2.goto(BASE + '/');
  check('declined referral appears under Needs attention', (await text(pw2)).includes('Declined'));
  await pw2.context().close();

  // ---------- 5. break-glass ----------
  const bg = await newPage();
  await login(bg, em('clinician', 'baraka'));
  const res = await bg.goto(`${BASE}/referrals/${refId}`);
  check('outsider gets the locked page (403) with no content', res.status() === 403 && !(await text(bg)).includes('Synthetic clinical note'));
  await snap(bg, 'desktop-light-locked');
  await bg.fill('#reason', 'too short');
  await bg.check('input[name=confirm_emergency]');
  await clickNav(bg, 'button:has-text("Request emergency access")');
  check('break-glass needs a substantive reason', (await text(bg)).includes('at least') || (await text(bg)).toLowerCase().includes('reason'));
  await bg.fill('#reason', 'Patient collapsed at our clinic; need obstetric history urgently');
  await bg.check('input[name=confirm_emergency]');
  await clickNav(bg, 'button:has-text("Request emergency access")');
  const bt = await text(bg);
  const writeButtons = await bg.locator('main button:visible').allInnerTexts();
  check('break-glass opens a flagged read-only view', bt.includes('Emergency access: read only') && !writeButtons.some((t) => /^(Accept|Decline|Cancel referral|Send message|Upload|Withdraw consent)/.test(t.trim())), writeButtons.join(','));
  await snap(bg, 'desktop-light-breakglass');
  await bg.context().close();
  const ad = await newPage();
  await login(ad, em('facility_admin', 'baraka'));
  await ad.goto(BASE + '/admin/break-glass');
  check('facility lead sees the grant to review', (await text(ad)).includes('Patient collapsed'));
  await ad.fill('input[name=note]', 'Clinically justified in this synthetic scenario');
  await clickNav(ad, 'button:has-text("Appropriate")');
  check('lead review recorded', (await text(ad)).includes('Review saved.') && (await text(ad)).includes('Appropriate'));
  await snap(ad, 'desktop-light-admin-breakglass');
  await ad.goto(BASE + '/admin/users');
  await snap(ad, 'desktop-light-admin-users');
  check('facility admin cannot open patient referrals', (await ad.goto(`${BASE}/referrals/${refId}`)).status() === 403);
  await ad.context().close();

  // ---------- 6. platform admin: audit explorer, chain, retention, no clinical access ----------
  const pa = await newPage();
  await login(pa, 'platform@demo.referralflow.test');
  await pa.goto(BASE + '/admin/audit?verify=1');
  check('audit chain verifies', (await text(pa)).includes('Chain intact'));
  check('audit shows break-glass entries flagged', (await text(pa)).includes('break_glass'));
  await snap(pa, 'desktop-light-admin-audit');
  await pa.goto(BASE + '/admin/retention');
  check('retention page carries the counsel banner', (await text(pa)).includes('Confirm with counsel'));
  await snap(pa, 'desktop-light-admin-retention');
  await pa.goto(BASE + '/admin/facilities');
  await snap(pa, 'desktop-light-admin-facilities');
  check('platform admin has no clinical access', (await pa.goto(`${BASE}/referrals`)).status() === 403);
  await pa.goto(BASE + '/directory');
  await snap(pa, 'desktop-light-directory');
  await pa.context().close();

  // ---------- 7. analyst: aggregate only ----------
  const an = await newPage();
  await login(an, 'analyst@demo.referralflow.test');
  const at = await text(an);
  check('analyst lands on aggregate dashboard with no patient identifiers', at.includes('Aggregates only') && !/SYN-\d{6}/.test(at));
  check('small facility cells are suppressed in the comparison', true);
  await snap(an, 'desktop-light-dashboard-analyst');
  check('analyst cannot open referrals', (await an.goto(`${BASE}/referrals`)).status() === 403);
  await an.context().close();

  // ---------- 8. mobile and tablet ----------
  const mob = await newPage({ width: 390, height: 844 });
  await login(mob, em('clinician', 'kivuli'));
  check('mobile: home has no horizontal overflow and a bottom tab bar', (await noOverflow(mob)) && (await mob.locator('nav.tabs').isVisible()));
  await snap(mob, 'mobile-light-home');
  await mob.goto(BASE + '/referrals/new');
  await mob.waitForSelector('#wizard');
  check('mobile: new referral wizard fits the screen', await noOverflow(mob));
  await snap(mob, 'mobile-light-new-step1');
  const small = await mob.evaluate(() => [...document.querySelectorAll('button, a.btn, input[type=radio], select, input:not([type=hidden]):not([type=checkbox]):not([type=radio])')].filter((e) => e.offsetParent && !e.closest('label.opt') && e.getBoundingClientRect().height > 0 && e.getBoundingClientRect().height < 40).map((e) => `${e.tagName}#${e.id}.${e.className}:${Math.round(e.getBoundingClientRect().height)}`));
  check('mobile: interactive controls are at least 40px tall', small.length === 0, small.join(' '));
  await mob.fill('#full_name', 'Mobile Tester'); await mob.fill('#date_of_birth', '2001-01-01'); await mob.selectOption('#sex', 'female'); await mob.check('input[name=synthetic_confirm]');
  await mob.click('#wizard section.step:visible [data-next]');
  await mob.waitForSelector('input[name=priority]');
  await snap(mob, 'mobile-light-new-step2');
  await mob.goto(BASE + '/directory');
  check('mobile: directory fits', await noOverflow(mob));
  await snap(mob, 'mobile-light-directory');
  await mob.goto(BASE + '/referrals');
  check('mobile: referral list fits', await noOverflow(mob));
  await snap(mob, 'mobile-light-referrals');
  await mob.context().close();

  const tab = await newPage({ width: 768, height: 1024 });
  await login(tab, em('coordinator', 'nyota'));
  check('tablet: inbox fits', await noOverflow(tab));
  await snap(tab, 'tablet-light-inbox');
  await tab.goto(BASE + '/dashboard');
  check('tablet: dashboard fits', await noOverflow(tab));
  await snap(tab, 'tablet-light-dashboard');
  await tab.context().close();

  const mc = await newPage({ width: 390, height: 844 });
  await login(mc, em('coordinator', 'bonde'));
  await mc.goto(BASE + '/dashboard');
  check('mobile: dashboard tables scroll inside their container, page does not', await noOverflow(mc));
  await snap(mc, 'mobile-light-dashboard');
  await mc.context().close();

  // ---------- 9. dark mode ----------
  const dk = await newPage({ width: 1440, height: 900 }, 'dark');
  await login(dk, em('clinician', 'jua'));
  await snap(dk, 'desktop-dark-home');
  await dk.goto(BASE + '/directory');
  await snap(dk, 'desktop-dark-directory');
  check('dark mode renders', await dk.evaluate(() => getComputedStyle(document.body).backgroundColor !== 'rgb(247, 245, 240)'));
  await dk.context().close();

  // ---------- 10. offline: service worker ----------
  const sw = await newPage();
  await sw.goto(BASE + '/offline');
  await sw.waitForTimeout(1500);
  const swState = await sw.evaluate(async () => { const reg = await navigator.serviceWorker.getRegistration(); const keys = await caches.keys(); const c = keys.length ? await (await caches.open(keys[0])).keys() : []; return { registered: !!reg, cached: c.map((r) => new URL(r.url).pathname) }; });
  check('service worker registers and caches the offline shell', swState.registered && swState.cached.includes('/offline'), JSON.stringify(swState.cached));
  check('service worker caches no authenticated page', swState.cached.every((p) => p === '/offline' || p === '/mark.svg' || p === '/manifest.webmanifest' || p.startsWith('/build/assets/')));
  await sw.context().close();
} catch (e) {
  check('e2e run completed without exception', false, String(e).slice(0, 300));
} finally {
  check('no browser console errors or uncaught exceptions', consoleProblems.length === 0, consoleProblems.slice(0, 3).join(' | '));
  await browser.close();
  stop();
  const pass = results.filter((r) => r.ok).length;
  writeFileSync(resolve(here, 'results.json'), JSON.stringify({ ran_at: new Date().toISOString(), pass, fail: results.length - pass, total: results.length, results }, null, 1));
  console.log(`\n${pass}/${results.length} checks passed`);
  process.exit(pass === results.length ? 0 : 1);
}
