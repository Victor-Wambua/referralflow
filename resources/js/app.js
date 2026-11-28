// ReferralFlow front-end. Vanilla JS, no network calls except same-origin keepalive and directory snapshot.
// PRIVACY: nothing about a patient is ever written to browser storage. Only non-PHI draft fields
// (priority, reason category, destination id) and a facility directory snapshot (see design/addenda.md A4).
document.documentElement.classList.add('js');

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const store = {
    get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* storage unavailable */ } },
    del(k) { try { localStorage.removeItem(k); } catch { /* ignore */ } },
};
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const live = (msg) => { const l = $('#live'); if (l) { l.textContent = ''; setTimeout(() => (l.textContent = msg), 30); } };

// theme
try { const t = localStorage.getItem('rf_theme'); if (t) document.documentElement.dataset.theme = t; } catch { /* ignore */ }
$$('[data-theme-set]').forEach((b) => b.addEventListener('click', () => {
    const v = b.dataset.themeSet;
    if (v === 'auto') { delete document.documentElement.dataset.theme; try { localStorage.removeItem('rf_theme'); } catch { /* */ } } else { document.documentElement.dataset.theme = v; try { localStorage.setItem('rf_theme', v); } catch { /* */ } }
}));

// dialogs
document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-dialog]');
    if (o) { e.preventDefault(); const d = document.getElementById(o.dataset.dialog); if (d?.showModal) d.showModal(); }
    const c = e.target.closest('[data-close]');
    if (c) { e.preventDefault(); c.closest('dialog')?.close(); }
});
$$('dialog').forEach((d) => { if (d.hasAttribute('data-open-on-load') || d.querySelector('.err')) d.showModal(); });

// sign-out clears any non-PHI local draft
$$('form[data-signout]').forEach((f) => f.addEventListener('submit', () => { store.del('rf_draft'); store.del('rf_dir'); }));

// idle timeout warning (the server enforces the real limit)
const sess = $('body[data-idle]');
if (sess) {
    const idle = Number(sess.dataset.idle) * 1000;
    const warn = Number(sess.dataset.warn) * 1000;
    let last = Date.now();
    const dlg = $('#idle-dialog');
    const cd = $('#idle-count');
    const reset = () => { last = Date.now(); };
    ['keydown', 'pointerdown', 'scroll'].forEach((ev) => document.addEventListener(ev, () => { if (!dlg?.open) reset(); }, { passive: true }));
    setInterval(() => {
        const left = idle - (Date.now() - last);
        if (left <= 0) { location.href = '/login'; return; }
        if (left <= warn && dlg && !dlg.open) dlg.showModal();
        if (dlg?.open && cd) cd.textContent = Math.ceil(left / 1000);
    }, 1000);
    $('#idle-stay')?.addEventListener('click', async () => {
        try { await fetch('/session/keepalive', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() } }); } catch { /* offline */ }
        reset(); dlg.close();
    });
}

// New-referral wizard
const wiz = $('#wizard');
if (wiz) {
    const steps = $$('.step', wiz);
    const bars = $$('#stepbar i');
    let cur = 0;
    let shown = false;
    const show = (i) => {
        cur = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach((s, k) => { s.hidden = k !== cur; });
        bars.forEach((b, k) => b.classList.toggle('on', k <= cur));
        $('#step-label').textContent = `${wiz.dataset.stepWord} ${cur + 1} ${wiz.dataset.stepOf || '/'} ${steps.length}`;
        if (shown) { steps[cur].querySelector('h2')?.focus?.({ preventScroll: true }); window.scrollTo({ top: 0 }); }
        live($('#step-label').textContent);
        shown = true;
    };
    const valid = () => {
        let ok = true;
        $$('[data-need]', steps[cur]).forEach((el) => {
            const mode = el.dataset.need;
            if (el.closest('[hidden]')) return;
            let bad = false;
            if (mode === 'text' && !el.value.trim()) bad = true;
            if (mode === 'radio' && !$$(`input[name="${el.dataset.name}"]`, steps[cur]).some((r) => r.checked)) bad = true;
            const msg = el.parentElement.querySelector('.err.js-err') || Object.assign(document.createElement('div'), { className: 'err js-err', role: 'alert' });
            if (bad) { msg.textContent = el.dataset.msg; if (!msg.isConnected) el.parentElement.appendChild(msg); el.setAttribute('aria-invalid', 'true'); if (ok) el.focus?.(); ok = false; } else { msg.remove(); el.removeAttribute('aria-invalid'); }
        });
        return ok;
    };
    // Enter in a single-line field moves to the next step instead of submitting the form early (the first submit button is a draft save)
    wiz.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.target.tagName !== 'INPUT' || ['submit', 'button', 'checkbox', 'radio', 'file'].includes(e.target.type)) return;
        e.preventDefault();
        steps[cur].querySelector('[data-next]')?.click();
    });
    $$('[data-next]', wiz).forEach((b) => b.addEventListener('click', () => { if (valid()) show(cur + 1); }));
    $$('[data-prev]', wiz).forEach((b) => b.addEventListener('click', () => show(cur - 1)));
    // jump to the first step with a server error
    const firstErr = steps.findIndex((s) => s.querySelector('.err:not(.js-err), [aria-invalid=true]'));
    show(firstErr >= 0 ? firstErr : 0);

    // patient mode
    const modeInputs = $$('input[name=patient_mode]', wiz);
    const syncMode = () => {
        const m = modeInputs.find((i) => i.checked)?.value || 'new';
        $('#pt-existing').hidden = m !== 'existing';
        $('#pt-new').hidden = m !== 'new';
        $$('#pt-new [data-need]').forEach((el) => { el.dataset.need = m === 'new' ? el.dataset.need : el.dataset.need; });
    };
    modeInputs.forEach((i) => i.addEventListener('change', syncMode));
    syncMode();

    // stale / unverified destination needs a phone confirmation
    const staleDlg = $('#stale-dialog');
    $$('input[name=receiving_facility_id]', wiz).forEach((r) => r.addEventListener('change', () => {
        const st = r.dataset.state;
        $('#stale_confirmed').value = '0';
        if ((st === 'stale' || st === 'unverified') && staleDlg) { $('#stale-name').textContent = r.dataset.name; staleDlg.showModal(); }
    }));
    // Escape (or any close that is not "I have confirmed") must not leave an unconfirmed stale facility selected
    staleDlg?.addEventListener('close', () => {
        if ($('#stale_confirmed').value !== '1') $$('input[name=receiving_facility_id]', wiz).forEach((r) => { if (r.dataset.state === 'stale' || r.dataset.state === 'unverified') r.checked = false; });
    });
    $('#stale-yes')?.addEventListener('click', () => { $('#stale_confirmed').value = '1'; staleDlg.close(); });
    $('#stale-no')?.addEventListener('click', () => { $$('input[name=receiving_facility_id]', wiz).forEach((r) => (r.checked = false)); staleDlg.close(); });

    const rt = $('#reason_text');
    const rc = $('#reason-count');
    if (rt && rc) { const up = () => { rc.textContent = `${rt.value.length}/240`; }; rt.addEventListener('input', up); up(); }

    // include-note toggle
    const inc = $('#include_note');
    const noteBox = $('#note-box');
    const syncNote = () => { if (noteBox) noteBox.hidden = !inc.checked; };
    inc?.addEventListener('change', syncNote); syncNote();

    // review summary
    $('#to-review')?.addEventListener('click', () => {
        if (!valid()) return;
        const f = new FormData(wiz);
        const prio = f.get('priority');
        const dest = $(`input[name=receiving_facility_id]:checked`, wiz)?.dataset.name || '';
        $('#rv-prio').textContent = $(`input[name=priority][value=${prio}]`)?.dataset.label || '';
        $('#rv-dest').textContent = dest;
        $('#rv-note').textContent = inc?.checked ? wiz.dataset.yes : wiz.dataset.no;
        $('#rv-docs').textContent = String($$('input[type=file]', wiz).reduce((n, i) => n + i.files.length, 0));
    });

    // non-PHI offline draft
    const saveDraft = () => store.set('rf_draft', {
        priority: $('input[name=priority]:checked', wiz)?.value || null,
        reason_code: $('select[name=reason_code]', wiz)?.value || null,
        receiving_facility_id: $('input[name=receiving_facility_id]:checked', wiz)?.value || null,
        saved_at: new Date().toISOString(),
    });
    $('#save-draft')?.addEventListener('click', () => { saveDraft(); live(wiz.dataset.draftSaved); const n = $('#draft-note'); if (n) n.textContent = wiz.dataset.draftSaved; });
    const dr = store.get('rf_draft');
    if (dr && $('#resume-draft')) {
        $('#resume-draft').hidden = false;
        $('#resume-yes').addEventListener('click', () => {
            if (dr.priority) { const r = $(`input[name=priority][value=${dr.priority}]`, wiz); if (r) r.checked = true; }
            if (dr.reason_code) $('select[name=reason_code]', wiz).value = dr.reason_code;
            if (dr.receiving_facility_id) { const r = $(`input[name=receiving_facility_id][value="${dr.receiving_facility_id}"]`, wiz); if (r) r.checked = true; }
            store.del('rf_draft'); $('#resume-draft').hidden = true; live(wiz.dataset.draftResumed);
        });
        $('#resume-no').addEventListener('click', () => { store.del('rf_draft'); $('#resume-draft').hidden = true; });
    }
    // directory snapshot for the offline page (facility data only)
    store.set('rf_dir', $$('input[name=receiving_facility_id]', wiz).map((r) => ({ id: r.value, name: r.dataset.name, phone: r.dataset.phone, state: r.dataset.state })));
}

// connectivity banner
const off = $('#offline-banner');
if (off) {
    const pill = $('#online-pill');
    const sync = () => { off.hidden = navigator.onLine; if (pill) { pill.textContent = navigator.onLine ? pill.dataset.on || pill.textContent : pill.dataset.off || pill.textContent; pill.className = 'pill ' + (navigator.onLine ? 'p-ok' : 'p-warn'); } };
    addEventListener('online', sync); addEventListener('offline', sync); sync();
}

// offline page: non-PHI draft only
const offApp = $('#offline-app');
if (offApp) {
    const dir = store.get('rf_dir') || [];
    const sel = $('#od-dest');
    dir.forEach((f) => sel.add(new Option(f.name, f.id)));
    const draft = store.get('rf_draft');
    if (draft) { if (draft.priority) $(`input[name=od-prio][value=${draft.priority}]`).checked = true; if (draft.reason_code) $('#od-reason').value = draft.reason_code; if (draft.receiving_facility_id) sel.value = draft.receiving_facility_id; }
    $('#od-save').addEventListener('click', () => {
        store.set('rf_draft', { priority: $('input[name=od-prio]:checked')?.value || null, reason_code: $('#od-reason').value || null, receiving_facility_id: sel.value || null, saved_at: new Date().toISOString() });
        $('#od-msg').textContent = offApp.dataset.saved;
    });
    const phones = $('#od-phones');
    dir.filter((f) => f.phone).forEach((f) => { const li = document.createElement('li'); li.textContent = `${f.name}: ${f.phone}`; phones.appendChild(li); });
    if (!dir.length) $('#od-nophones').hidden = false;
}

// service worker (static shell + offline page only; no authenticated pages are cached)
if ('serviceWorker' in navigator && location.protocol.startsWith('http')) {
    addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// Auto-submit filter selects (inline handlers are blocked by the CSP)
document.addEventListener("change", (e) => {
  const t = e.target;
  if (t && t.matches && t.matches("[data-autosubmit]") && t.form) { t.form.submit(); }
});

// Receiving-facility filter on the new-referral wizard (hides non-matching options only)
const facFilter = $('#fac-filter');
if (facFilter) {
    const opts = $$('.fac-opt');
    const none = $('#fac-none');
    facFilter.addEventListener('input', () => {
        const q = facFilter.value.trim().toLowerCase();
        let n = 0;
        opts.forEach((o) => { const hit = !q || o.textContent.toLowerCase().includes(q); o.hidden = !hit; if (hit) n++; });
        none.hidden = n > 0;
    });
}

// Loading and offline feedback for every POST form: block double submits, show a busy state, and explain when offline.
const body = document.body;
document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || f.method !== 'post') return;
    if (!navigator.onLine) { e.preventDefault(); live(body.dataset.msgOffline || ''); const b = $('#offline-banner'); if (b) { b.hidden = false; } return; }
    if (f.dataset.busy === '1') { e.preventDefault(); return; }
    f.dataset.busy = '1';
    const btn = e.submitter || f.querySelector('button:not([type=button])');
    if (btn) { btn.classList.add('is-busy'); btn.setAttribute('aria-busy', 'true'); }
    live(body.dataset.msgBusy || '');
});
addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    $$('form[data-busy]').forEach((f) => { f.dataset.busy = ''; });
    $$('.is-busy').forEach((b) => { b.classList.toggle('is-busy', false); b.setAttribute('aria-busy', 'false'); });
});
