// Print-style check (report/log/export pages) and page-weight / request-count / layout-shift measurement.
// Synthetic demo accounts only. Own port and database (4468). Set PERF_OUT to also write the numbers as JSON.
import { writeFileSync } from 'node:fs';
import { startApp, login, ROLES } from './lib.mjs';

const { BASE, browser, stop } = await startApp(4468, 'e2e-perf');
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  - ' + detail : ''}`); };
const perf = [];

try {
  const states = {};
  for (const role of ['clinician', 'coordinator', 'platform']) {
    const c = await browser.newContext(); const p = await c.newPage();
    await login(p, BASE, ROLES[role]); states[role] = await c.storageState(); await c.close();
  }
  const first = async (role, listPath, re) => {
    const c = await browser.newContext({ storageState: states[role] }); const p = await c.newPage();
    await p.goto(BASE + listPath);
    const h = await p.evaluate((src) => [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).find((x) => new RegExp(src).test(x)), re);
    await c.close(); return new URL(h, BASE).pathname;
  };
  const refPath = await first('clinician', '/referrals', '/referrals/[0-9a-z]{26}$');

  // ---------- print styles ----------
  const printPages = [['coordinator', '/dashboard'], ['coordinator', '/access'], ['clinician', refPath], ['clinician', '/referrals'], ['platform', '/admin/audit'], ['clinician', '/directory']];
  for (const [role, path] of printPages) {
    const c = await browser.newContext({ storageState: states[role], viewport: { width: 1280, height: 800 } }); const p = await c.newPage();
    await p.goto(BASE + path);
    await p.emulateMedia({ media: 'print' });
    const r = await p.evaluate(() => {
      const hidden = (s) => { const e = document.querySelector(s); return !e || getComputedStyle(e).display === 'none'; };
      const th = document.querySelector('th'); const tbl = document.querySelector('table');
      return {
        chromeHidden: hidden('.side') && hidden('.tabs') && hidden('.strip'),
        notice: !!document.querySelector('.demo') && getComputedStyle(document.querySelector('.demo')).display !== 'none',
        overflow: document.documentElement.scrollWidth - window.innerWidth,
        tableOk: !tbl || (getComputedStyle(tbl).display === 'table' && getComputedStyle(th).display !== 'none'),
        bg: getComputedStyle(document.body).backgroundColor,
      };
    });
    check(`print ${path.replace(/[0-9a-z]{26}/, ':id')}: navigation and session strip hidden, synthetic-data notice kept`, r.chromeHidden && r.notice);
    check(`print ${path.replace(/[0-9a-z]{26}/, ':id')}: no horizontal overflow, tables print as tables, white background`, r.overflow <= 1 && r.tableOk && r.bg === 'rgb(255, 255, 255)', JSON.stringify(r));
    if (process.env.PRINT_DIR) await p.pdf({ path: `${process.env.PRINT_DIR}/print-${path.replace(/[^a-z]/g, '') || 'home'}.pdf`, format: 'A4' });
    await c.close();
  }

  // ---------- Kiswahili at 375 px: no horizontal overflow, no untranslated fragments from the string table ----------
  for (const [role, paths] of [['clinician', ['/', '/referrals/new', '/referrals', '/directory', '/access', '/settings']], ['coordinator', ['/inbox', '/dashboard', '/access']]]) {
    const c = await browser.newContext({ storageState: states[role], viewport: { width: 375, height: 812 } }); const p = await c.newPage();
    await p.goto(BASE + '/settings'); await p.check('input[name=locale][value=sw]');
    await Promise.all([p.waitForNavigation(), p.click('main form.card button:not([type=button])')]);
    const bad = [];
    for (const path of paths) {
      await p.goto(BASE + path);
      const o = await p.evaluate(() => ({ lang: document.documentElement.lang, over: document.documentElement.scrollWidth - window.innerWidth }));
      if (o.lang !== 'sw' || o.over > 1) bad.push(`${path}:${o.lang}:${o.over}`);
    }
    check(`Kiswahili ${role}: pages render in sw with no horizontal overflow at 375px`, bad.length === 0, bad.join(' '));
    await p.goto(BASE + '/settings'); await p.check('input[name=locale][value=en]');
    await Promise.all([p.waitForNavigation(), p.click('main form.card button:not([type=button])')]);
    await c.close();
  }

  // ---------- performance (emulated desktop broadband, cold cache) ----------
  const perfPages = [['clinician', '/referrals/new'], ['coordinator', '/dashboard'], ['coordinator', '/inbox'], ['clinician', refPath], ['clinician', '/directory'], ['coordinator', '/access'], ['platform', '/admin/audit']];
  for (const [role, path] of perfPages) {
    const c = await browser.newContext({ storageState: states[role], viewport: { width: 375, height: 812 } }); const p = await c.newPage();
    let bytes = 0; let reqs = 0;
    p.on('response', async (res) => { reqs++; try { const l = res.headers()['content-length']; bytes += l ? Number(l) : (await res.body()).length; } catch { /* redirect */ } });
    await p.addInitScript(() => { window.__cls = 0; new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
    await p.goto(BASE + path, { waitUntil: 'load' });
    await p.waitForTimeout(600);
    const m = await p.evaluate(() => ({ cls: window.__cls, dcl: Math.round(performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd), load: Math.round(performance.getEntriesByType('navigation')[0].loadEventEnd), dom: document.getElementsByTagName('*').length }));
    perf.push({ page: path.replace(/[0-9a-z]{26}/, ':id'), role, requests: reqs, transfer_kb: Math.round(bytes / 1024), cls: +m.cls.toFixed(4), dom_nodes: m.dom, dcl_ms: m.dcl, load_ms: m.load });
    await c.close();
  }
  console.table(perf);
  const worst = [...perf].sort((a, b) => b.transfer_kb - a.transfer_kb).slice(0, 3);
  check('perf: every measured page has a layout shift (CLS) under 0.1', perf.every((x) => x.cls < 0.1), perf.map((x) => x.cls).join(','));
  check('perf: every measured page transfers under 300 KB and under 15 requests (cold cache)', perf.every((x) => x.transfer_kb < 300 && x.requests < 15), worst.map((w) => `${w.page}=${w.transfer_kb}KB/${w.requests}req`).join(' '));
  if (process.env.PERF_OUT) writeFileSync(process.env.PERF_OUT, JSON.stringify(perf, null, 1));
} catch (e) {
  check('print/perf run completed without exception', false, String(e).slice(0, 300));
} finally {
  await browser.close();
  stop();
  const pass = results.filter((r) => r.ok).length;
  console.log(`\n${pass}/${results.length} print/perf checks passed`);
  process.exit(pass === results.length ? 0 : 1);
}
