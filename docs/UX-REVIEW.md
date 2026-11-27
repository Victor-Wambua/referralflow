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
- Access log shows raw action codes (`patient.list`) and numeric actor ids; mapping to plain language needs product decisions. Mobile view scrolls horizontally.
- Dashboard facility table is cramped on mobile (scrolls inside its container).
- The wizard is four steps; collapsing steps would change behaviour, so not done.
- Print styles, loading and offline states, and the Kiswahili rendering of every screen were not visually reviewed.
- Static prototype in `design/prototype/` not updated: these changes are small and prototype drift is noted here.
- Contrast was checked visually plus the existing `tools/contrast.mjs`; no automated axe run.
