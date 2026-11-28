# UX review

## Method
Ran the app locally on synthetic seed data and captured every major screen as clinician, coordinator, facility admin, network analyst and platform admin at 1280x800 and 375x812 (light), plus 1280x800 dark. Reviewed against the product design system, WCAG 2.2 AA, landmarks and keyboard use, forms, mobile data density, privacy messaging and Kiswahili coverage. Screenshots: `docs/screenshots/ux-review/`.

Focus areas: referral creation speed, status clarity for both facilities, privacy and break-glass clarity, directory verification timestamps, English/Kiswahili strings.

## Findings
| Severity | Screen | Problem | Fix | Status |
|---|---|---|---|---|
| High | Referral detail (receiver) | Overdue banner told the receiving facility to "phone the facility", which is advice for the referrer | Receiver now sees "Waiting for your response" with accept/decline guidance; referrer text unchanged | Fixed |
| Medium | Referral detail | No send time on the page, so neither side could judge waiting time | Added "Sent 2 days ago · date UTC" under the facility line | Fixed |
| Medium | Directory, new-referral, facility | Verification shown only as "1 month ago" | Wrapped in `<time>` with exact UTC timestamp as tooltip and datetime attribute | Fixed |
| Medium | New referral, step 3 | Long facility list with no search slows a busy clinician | Added client-side filter (hides options only; selection logic untouched) | Fixed |
| Low | New referral | Step heading took focus with a ring on first load | Heading focus only on step change | Fixed |
| Low | Access log, dashboard (mobile) | Scrollable tables not keyboard reachable | `role="region"`, `tabindex="0"`, label on the scroll container | Fixed |
| Low | Kiswahili | New strings lacked translations | Added to `lang/sw.json` | Fixed |

Already good and left alone: synthetic-data banner, masked identity with logged reveal, minimum-necessary banner before accept, break-glass form (reason, 60 minute limit, lead review), stale-facility phone confirmation, status track.

## Not fixed / not reviewed
- (Round 1) Access log raw codes: resolved in Round 2.
- Dashboard facility table is cramped on mobile (scrolls inside its container).
- The wizard is four steps; collapsing steps would change behaviour, so not done.
- Print styles, loading and offline states, and the Kiswahili rendering of every screen were not visually reviewed.
- Static prototype in `design/prototype/` not updated: these changes are small and prototype drift is noted here.
- Contrast was checked visually plus the existing `tools/contrast.mjs`; no automated axe run.


## Round 2

Method: new Playwright suites in `e2e/` (own ports and throwaway databases): `a11y.mjs` (axe-core, a dev-only e2e dependency), `keyboard.mjs`, `print-perf.mjs`; plus the existing `run.mjs` and the Pest suite. Every screen was visited as clinician, coordinator, facility admin, network analyst and platform admin (and the public pages), at 375 and 1280 px, light and dark: 232 screen renders per run. Screenshots: `docs/screenshots/ux-review/round2-*`.

| Item | What was done | Evidence |
|---|---|---|
| Access log codes | Plain-language labels for all 64 audit action codes (`audit_labels` in config, Kiswahili strings added); the exact code is shown under each label and the audit export is unchanged. Mobile shows stacked cards instead of sideways scrolling. | `AccessLogLabelsTest` fails if any recorded code lacks a label or Kiswahili string |
| Dashboard facility table | Cards on phones (same data, same suppression of small groups). Users, facilities and audit explorer tables use the same pattern. | screenshots, axe |
| Wizard | Measured: 9 required inputs plus 3 Continue and 1 Send = 13 actions minimum, at most 4 required fields per step. Steps kept at four: step 4 is the consent and "what will be shared" gate, so merging steps would not save required input. Friction fixes that drop nothing: Enter in a text field now goes to the next step (before it submitted a draft early because the first submit button is Save draft); Escape on the phone-confirmation dialog now clears the unconfirmed stale facility (before it stayed selected and failed on the server); "Step n of 4" wording. | `keyboard.mjs` |
| Loading and offline | Submit buttons show a busy state and block double submits; submitting while offline is stopped with a plain message instead of a browser error page. | manual and `run.mjs` flows still pass |
| axe-core | Before: 232 moderate (`region`, demo notice outside a landmark) and 4 serious (`scrollable-region-focusable`, audit table on mobile). After: 0 of any impact, best-practice rules included. | `a11y.mjs` fails on serious or critical |
| Keyboard-only | 40 checks: skip link, sign-in, full referral created and sent with keys only, radios by arrow keys, dialogs trap and restore focus, visible focus on every stop and no trap on 14 page/viewport combinations. | `keyboard.mjs` |
| Print | Navigation, session strip and buttons hidden; the synthetic-data notice kept; tables print as tables with repeated headers; checked for dashboard, access log, referral, referral list, audit explorer, directory. PDFs were rendered and two were inspected by eye. | `print-perf.mjs` |
| Contrast | axe `color-contrast` found no failures in light or dark on all screens (computed from rendered colours); `tools/contrast.mjs` token checks unchanged. | `a11y.mjs` |
| Performance | Emulated, local server, cold cache, 375 px: 5 requests, 8 to 24 KB transferred and CLS 0 on all 7 pages measured (heaviest: new referral 24 KB, referral detail 13 KB, audit explorer 12 KB). No change needed. | `print-perf.mjs` |
| Kiswahili | Every `__()` string in views and app code has a `sw.json` entry (0 missing of 483). No horizontal overflow at 375 px on the main screens in Kiswahili. | script check and `print-perf.mjs` |
| Prototype | Stacked cards for the facility comparison and access log tables on phones, plus print rules, in `design/prototype/`. Still labelled as a simulated prototype. | |

### Still open
- Kiswahili strings, including all new ones, are machine-assisted and have NOT been reviewed by a native speaker. Native review is still required.
- Real screen-reader testing (VoiceOver, NVDA), real low-end devices and real throttled networks were not done and cannot be done here. The performance numbers above are local and unthrottled.
- Stacked table cards use CSS display changes, which some screen readers may announce as plain text rather than a table. Not tested with a screen reader.
- Dialogs were scanned by axe only when closed; their focus behaviour is covered by the keyboard suite for the stale-facility and reveal dialogs, not every dialog.
- The audit explorer (platform admin) keeps raw codes by design, as a technical view.
