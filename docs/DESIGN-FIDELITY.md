# Design fidelity

Real-application screenshots (Playwright, Chromium, synthetic seed data) are in `docs/screenshots/` (35 images: desktop 1440x900 light, mobile 390x844, tablet 768x1024, dark). The approved design screenshots are in `design/screenshots/`. I compared pairs by eye (the app screenshot against the design screenshot of the same screen); there is no automated pixel comparison, so this is a human-style judgement, not a measurement.

## What was reused unchanged
Design tokens (`design/design-tokens.css` is imported as-is), the prototype stylesheet (components: buttons, option cards, status and verification pills, banners, tracker, timeline, message bubbles, stat tiles, tables, bars, dialogs, left rail, bottom tab bar), the logo mark, type stack, 48px tap targets, dusk indigo palette in light and dark.

## Screen by screen
| Screen | Design reference | Real app | Fidelity |
|---|---|---|---|
| Sign in + MFA | desktop-light-login | `desktop-light-login` | Close. Design has a role picker (prototype only); the app uses real accounts. MFA enrolment screen (setup key as text) is new. |
| Home (clinician) | desktop/mobile-light-home | `desktop-light-home`, `mobile-light-home` | Close: greeting, large New referral, Needs attention, Recent. The design's offline outbox banner is intentionally replaced (see addenda A4). |
| New referral steps 1-4 | new-step2/3/4 | `desktop-light-new-step1..4`, `mobile-light-new-step1/2` | Close: stepper bar, option cards, priority order and hints (Routine, Urgent, Emergency), stale-facility dialog, what-is-shared review and consent. Differences: step headings are plainer ("2. Priority and reason" vs "Why are you referring?"); the draft-saved line is "Save draft" buttons instead of auto-save text; character count sits in the hint line. |
| Stale dialog | dialog-stale | `desktop-light-dialog-stale` | Same pattern and wording (phone confirmation). |
| Referral detail | desktop-light-referral | `desktop-light-referral`, `-minimal`, `-scheduled`, `-completed`, `-revealed` | Close: header, priority and status pills, consent banner, tracker, actions, tabs, masked patient with Reveal. Differences: Patient and Referral cards are stacked in one column rather than side by side; the design's "Reveal identifiers" wording is "Reveal identity"; "Messages (0)" count is not shown. |
| Inbox | desktop-light-inbox | `desktop-light-inbox` | Close (three buckets with counts, priority sorted, empty state). |
| Directory + facility | directory, facility-stale | `desktop-light-directory` | Close (verification pills, filters). Facility detail adds a verify form for authorised roles. |
| Dashboard | dashboard | `desktop-light-dashboard`, `-analyst`, `mobile/tablet` | Close: tiles, weekly chart, reasons bars, facility table with caution banner and hidden small cells. Differences: heading is "Dashboard" (design "Network dashboard"); chart has no grid lines; the tile set differs slightly (Created, Sent, Accepted, Pending, Completed, median acknowledge, median decision, incomplete rate). |
| Locked + break-glass | locked, breakglass | `desktop-light-locked`, `-breakglass` | Close; the app adds a required emergency checkbox and re-authentication. |
| Access log | access | `desktop-light-access` | Close (table; break-glass rows tinted). |
| Admin users / facilities / audit / retention | admin-* | `desktop-light-admin-*` | Close in structure; MFA is shown as locked-on text rather than a disabled toggle. |
| Notifications, settings | notifications, settings-offline | not captured in the e2e set | Built; the offline simulation toggle in the design is replaced by a real offline page and service worker. |
| States gallery | states | not built | The prototype's states page is a design artefact, not an application screen. Empty, error and forbidden states exist on the real pages. |
| Dark mode | dark-* | `desktop-dark-home`, `desktop-dark-directory` | Tokens drive it; only two dark screens captured. |
| Mobile/tablet | mobile-light-* | `mobile-light-*`, `tablet-light-*` | Bottom tab bar, sticky actions, tables scroll in a container; no horizontal overflow on any checked screen (checked in e2e). |

## Verification performed
- Layout: e2e asserts no horizontal overflow at 390 and 768 px on the screens above and that interactive controls outside option cards are at least 40 px tall.
- Contrast: the token pairs were verified in Phase A (`design/screenshots/contrast-results.json`). The application re-uses the same tokens, but contrast of the real pages was NOT re-run in Phase B.
- Accessibility structure: skip link, landmarks, labelled inputs, `role=alert` errors, native `<dialog>`, live region, `aria-current`; no screen-reader or keyboard audit with real devices.

## Known deviations
Prototype-only elements (role picker, simulated timeout button, simulated offline switch, states gallery) are not in the app. Kiswahili is new. The wizard is one server-validated form with client-side stepping (works without JavaScript as a single page).
