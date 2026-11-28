// Keyboard-only walkthrough (Tab, Shift+Tab, Enter, Space, arrows, Escape; no mouse) of the main journeys.
// Checks: skip link, visible focus on every stop, no traps, logical order, dialogs trap and restore focus.
// Synthetic demo accounts only. Own port and database (4467).
import { startApp, login, ROLES } from './lib.mjs';

const { BASE, browser, stop } = await startApp(4467, 'e2e-keyboard');
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  - ' + detail : ''}`); };

const active = (page) => page.evaluate(() => {
  const a = document.activeElement;
  if (!a || a === document.body) return null;
  const cs = getComputedStyle(a);
  const r = a.getBoundingClientRect();
  return { tag: a.tagName, id: a.id, text: (a.innerText || a.value || a.getAttribute('aria-label') || a.name || '').toString().trim().slice(0, 30), outline: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0, shadow: cs.boxShadow !== 'none', inDialog: !!a.closest('dialog'), inMain: !!a.closest('main'), visible: r.width > 0 && r.height > 0 };
});
async function tabTo(page, sel, max = 120) {
  if (await page.evaluate((s) => document.activeElement?.matches(s) ?? false, sel)) return 0;
  for (let i = 0; i < max; i++) {
    await page.keyboard.press('Tab');
    if (await page.evaluate((s) => document.activeElement?.matches(s) ?? false, sel)) return i + 1;
  }
  throw new Error('could not reach ' + sel + ' by Tab');
}
async function sweep(page, label, max = 90) {
  // Tab through the whole page; every stop must show a focus indicator; the cycle must come back to the start (no trap).
  await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
  const seen = []; const invisible = []; let first = null; let looped = false;
  for (let i = 0; i < max; i++) {
    await page.keyboard.press('Tab');
    const a = await active(page);
    if (!a) { looped = true; break; } // focus left the document (browser chrome) = cycle completed
    const key = a.tag + '#' + a.id + ':' + a.text;
    if (first === null) first = key; else if (key === first && i > 2) { looped = true; break; }
    seen.push(key);
    if (!a.visible || !(a.outline || a.shadow)) invisible.push(key);
  }
  check(`${label}: ${seen.length} focus stops all show a focus indicator`, invisible.length === 0, invisible.slice(0, 4).join(' | '));
  check(`${label}: Tab cycle completes (no keyboard trap)`, looped);
  return seen;
}

try {
  const states = {};
  for (const role of ['clinician', 'coordinator']) {
    const c = await browser.newContext(); const p = await c.newPage();
    await login(p, BASE, ROLES[role]); states[role] = await c.storageState(); await c.close();
  }
  const ctxFor = (role, vp = { width: 1280, height: 800 }) => browser.newContext({ storageState: states[role], viewport: vp });

  // ---- A. sign-in with the keyboard only, skip link ----
  {
    const c = await browser.newContext({ viewport: { width: 1280, height: 800 } }); const p = await c.newPage();
    await p.goto(BASE + '/login');
    await p.keyboard.press('Tab');
    const sk = await p.evaluate(() => { const a = document.activeElement; const r = a.getBoundingClientRect(); return { skip: a.classList.contains('skip'), onscreen: r.left >= 0 && r.top >= 0 && r.width > 1 }; });
    check('login: first Tab stop is the skip link and it becomes visible', sk.skip && sk.onscreen);
    await p.keyboard.press('Enter');
    await p.keyboard.press('Tab');
    check('login: skip link moves focus into the main content', (await active(p))?.inMain === true);
    await tabTo(p, '#email'); await p.keyboard.type(ROLES.coordinator);
    await p.keyboard.press('Tab'); await p.keyboard.type('Demo-Passphrase-2026');
    await Promise.all([p.waitForNavigation(), p.keyboard.press('Enter')]);
    check('login: password step submits with Enter and reaches the MFA step', p.url().includes('/mfa'));
    await sweep(p, 'MFA page');
    await c.close();
  }

  // ---- B. clinician: complete a referral with the keyboard only ----
  const cc = await ctxFor('clinician'); const cl = await cc.newPage();
  await cl.goto(BASE + '/');
  const homeStops = await sweep(cl, 'clinician home');
  check('clinician home: skip link is the first stop', homeStops[0]?.includes('Skip'), homeStops[0]);
  await cl.goto(BASE + '/referrals/new');
  await cl.keyboard.press('Tab');
  await tabTo(cl, '#full_name');
  await cl.keyboard.type('Keyboard Tester');
  await cl.keyboard.press('Enter');
  check('wizard: Enter in a text field validates and stays on step 1 (no early submit)', cl.url().endsWith('/referrals/new') && (await cl.locator('body').innerText()).includes('Enter the date of birth'));
  await tabTo(cl, '#date_of_birth'); await cl.keyboard.type('04111992');
  await tabTo(cl, '#sex'); await cl.keyboard.type('f');
  await tabTo(cl, 'input[name=synthetic_confirm]'); await cl.keyboard.press('Space');
  await tabTo(cl, '#wizard section.step:not([hidden]) [data-next]'); await cl.keyboard.press('Enter');
  const h2 = await cl.evaluate(() => document.activeElement?.tagName + ':' + document.activeElement?.textContent.trim().slice(0, 12));
  check('wizard: Continue moves focus to the next step heading', h2.startsWith('H2:2.'), h2);
  await tabTo(cl, 'input[name=priority]');
  await cl.keyboard.press('ArrowRight');
  check('wizard: priority radios work with arrow keys', await cl.evaluate(() => document.querySelector('input[name=priority]:checked')?.value) === 'urgent');
  await tabTo(cl, '#reason_code'); await cl.keyboard.type('Obs');
  await cl.keyboard.press('Tab'); await cl.keyboard.type('Synthetic keyboard-only referral');
  await tabTo(cl, '#wizard section.step:not([hidden]) [data-next]'); await cl.keyboard.press('Enter');
  await tabTo(cl, '#fac-filter'); await cl.keyboard.type('Mlima');
  await cl.keyboard.press('Tab'); await cl.keyboard.press('Space');
  await cl.waitForSelector('#stale-dialog[open]');
  const d1 = await active(cl);
  check('stale-facility dialog: opens with focus inside the dialog', d1?.inDialog === true, JSON.stringify(d1));
  let stayed = true; for (let i = 0; i < 7; i++) { await cl.keyboard.press('Tab'); { const a = await active(cl); if (a && !a.inDialog) stayed = false; } }
  for (let i = 0; i < 3; i++) { await cl.keyboard.press('Shift+Tab'); const a = await active(cl); if (a && !a.inDialog) stayed = false; }
  check('stale-facility dialog: Tab and Shift+Tab never reach page content behind it', stayed);
  await cl.keyboard.press('Escape');
  await cl.waitForTimeout(150);
  check('stale-facility dialog: Escape closes it and clears the unconfirmed selection', !(await cl.locator('#stale-dialog[open]').count()) && !(await cl.locator('input[name=receiving_facility_id]:checked').count()));
  const back = await cl.evaluate(() => document.activeElement?.name || document.activeElement?.id);
  check('stale-facility dialog: focus returns to the facility list after Escape', back === 'receiving_facility_id' || back === 'fac-filter', String(back));
  await cl.focus('#fac-filter'); await cl.fill('#fac-filter', ''); await cl.keyboard.type('Ziwani');
  await cl.keyboard.press('Tab'); await cl.keyboard.press('Space');
  await tabTo(cl, '#to-review'); await cl.keyboard.press('Enter');
  await tabTo(cl, 'input[name=consent_basis]'); await cl.keyboard.press('Space');
  await tabTo(cl, 'input[name=consent_confirm]'); await cl.keyboard.press('Space');
  await tabTo(cl, 'button[name=action][value=send]');
  await Promise.all([cl.waitForURL(/\/referrals\/[0-9a-z]{26}$/), cl.keyboard.press('Enter')]);
  check('wizard: referral created and sent using only the keyboard', (await cl.locator('body').innerText()).includes('Referral sent.'));

  // ---- C. referral page: dialog opens from the keyboard and restores focus ----
  await tabTo(cl, '[data-dialog=d-reveal]'); await cl.keyboard.press('Enter');
  await cl.waitForSelector('#d-reveal[open]');
  const dr = await active(cl);
  check('reveal dialog: opens with focus inside', dr?.inDialog === true, JSON.stringify(dr));
  await cl.keyboard.press('Escape');
  const trig = await cl.evaluate(() => document.activeElement?.getAttribute('data-dialog'));
  check('reveal dialog: Escape closes it and focus returns to the trigger button', trig === 'd-reveal', String(trig));
  await sweep(cl, 'referral detail');
  for (const path of ['/referrals', '/directory', '/access', '/settings']) { await cl.goto(BASE + path); await sweep(cl, 'clinician ' + path); }
  await cc.close();

  // ---- D. coordinator on desktop and on a phone-width viewport (bottom tab bar) ----
  for (const [name, vp] of [['desktop', { width: 1280, height: 800 }], ['mobile 375', { width: 375, height: 812 }]]) {
    const c = await ctxFor('coordinator', vp); const p = await c.newPage();
    for (const path of ['/inbox', '/dashboard', '/access']) { await p.goto(BASE + path); await sweep(p, `coordinator ${name} ${path}`); }
    await c.close();
  }
} catch (e) {
  check('keyboard run completed without exception', false, String(e).slice(0, 300));
} finally {
  await browser.close();
  stop();
  const pass = results.filter((r) => r.ok).length;
  console.log(`\n${pass}/${results.length} keyboard checks passed`);
  process.exit(pass === results.length ? 0 : 1);
}
