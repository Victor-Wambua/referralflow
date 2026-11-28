// Automated accessibility pass: axe-core over every reachable screen, for every role, at 375 and 1280 px, light and dark.
// Fails on any serious or critical violation. Synthetic demo accounts only. Own port and database (4466).
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { startApp, login, ROLES } from './lib.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const axeSrc = readFileSync(resolve(here, 'node_modules/axe-core/axe.min.js'), 'utf8');
const { BASE, browser, stop } = await startApp(4466, 'e2e-a11y');
const VIEWPORTS = [{ name: 'mobile', width: 375, height: 812 }, { name: 'desktop', width: 1280, height: 800 }];
const SCHEMES = ['light', 'dark'];
const summary = { total: 0, byImpact: {}, rules: {} };
const failures = [];
let screens = 0;

try {
  // Guest screens
  const guestPaths = ['/login', '/offline', '/no-such-page'];
  const states = {};
  for (const [role, email] of Object.entries(ROLES)) {
    const c = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const p = await c.newPage();
    await login(p, BASE, email);
    states[role] = await c.storageState();
    await c.close();
  }
  async function discover(role, listPath, re) {
    const c = await browser.newContext({ storageState: states[role] });
    const p = await c.newPage();
    await p.goto(BASE + listPath);
    const href = await p.evaluate((src) => { const a = [...document.querySelectorAll('a[href]')].map((x) => x.getAttribute('href')).find((h) => new RegExp(src).test(h)); return a; }, re.source);
    await c.close();
    return href;
  }
  const refHref = await discover('clinician', '/referrals', /^\/referrals\/[0-9a-z]{26}$|\/referrals\/[0-9a-z]{26}$/);
  const facHref = await discover('clinician', '/directory', /\/directory\/[0-9a-z]+$/);
  const refPath = new URL(refHref, BASE).pathname;
  const facPath = new URL(facHref, BASE).pathname;
  const common = ['/', '/directory', facPath, '/notifications', '/settings', '/password'];
  const perRole = {
    clinician: [...common, '/referrals', '/referrals/new', refPath, '/access', '/data-requests'],
    coordinator: [...common, '/inbox', '/referrals', '/referrals/new', refPath, '/dashboard', '/access', '/data-requests'],
    facility_admin: [...common, '/dashboard', '/access', '/admin/users', '/admin/break-glass', '/data-requests/review'],
    analyst: [...common, '/dashboard'],
    platform: [...common, '/admin/facilities', '/admin/users', '/admin/audit', '/admin/audit?verify=1', '/admin/retention', '/admin/forms', '/admin/break-glass'],
  };

  async function scan(ctxOpts, label, paths) {
    const ctx = await browser.newContext({ ...ctxOpts, bypassCSP: true });
    const page = await ctx.newPage();
    for (const path of paths) {
      const res = await page.goto(BASE + path);
      await page.waitForLoadState('load');
      // open any dialogs that are part of the page? (scanned closed; keyboard suite covers focus handling)
      await page.evaluate(axeSrc);
      const r = await page.evaluate(() => axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] } }));
      screens++;
      for (const v of r.violations) {
        summary.total += v.nodes.length;
        summary.byImpact[v.impact] = (summary.byImpact[v.impact] || 0) + v.nodes.length;
        summary.rules[v.id] = (summary.rules[v.id] || 0) + v.nodes.length;
        if (v.impact === 'serious' || v.impact === 'critical') failures.push({ at: `${label} ${path} [${res?.status()}]`, rule: v.id, impact: v.impact, n: v.nodes.length, sample: v.nodes.slice(0, 2).map((n) => n.target.join(' ') + ' :: ' + (n.any[0]?.message || n.failureSummary || '').slice(0, 140)) });
      }
    }
    await ctx.close();
  }
  for (const vp of VIEWPORTS) for (const scheme of SCHEMES) {
    await scan({ viewport: vp, colorScheme: scheme }, `guest ${vp.name} ${scheme}`, guestPaths);
    for (const [role, paths] of Object.entries(perRole)) await scan({ storageState: states[role], viewport: vp, colorScheme: scheme }, `${role} ${vp.name} ${scheme}`, paths);
  }
} finally {
  await browser.close();
  stop();
}
const out = { screens_scanned: screens, all_violation_nodes: summary.total, by_impact: summary.byImpact, by_rule: summary.rules, serious_or_critical: failures.length };
console.log(JSON.stringify(out, null, 1));
for (const f of failures.slice(0, 40)) console.log('FAIL', JSON.stringify(f));
if (process.env.A11Y_OUT) writeFileSync(process.env.A11Y_OUT, JSON.stringify({ ...out, failures }, null, 1));
console.log(failures.length === 0 ? `PASS  axe: no serious/critical violations on ${screens} screen renders` : `FAIL  axe: ${failures.length} serious/critical findings`);
process.exit(failures.length === 0 ? 0 : 1);
