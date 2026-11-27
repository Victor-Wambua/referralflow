# Information Architecture

See `ia-sitemap.svg` for the diagram.

## Sitemap
- Public: Sign in (account + password), MFA step, session-expired notice.
- Clinician: Home, New referral (4 steps), My referrals, Referral detail (Overview, Messages, History), Directory, Facility detail, Access log (own), Settings, Notifications.
- Coordinator: Inbox (Need response / Accepted-scheduled / Awaiting summary), Referrals, Referral detail, Directory, Dashboard, Access log (facility), Settings.
- Network manager: Dashboard, Referrals (aggregate progress only unless party), Directory, Access log, Settings.
- Admin: Users and roles, Facilities, Audit explorer, Retention and security, Dashboard, Settings.
- Utility: Notifications preview, States gallery (prototype only).

## Navigation per role
Desktop: left rail with labelled icons and inbox count. Mobile: bottom bar, 5 items (clinician: Home, New (primary), Referrals, Directory, Settings).

## Objects and relationships
Facility (MFL code, type, KEPH level, hours, services each with verification by/at) 1-n User (role, MFA). Referral (opaque id, from, to, priority, reason, status, consent record) 1-n Events (status, time, actor, reason), 1-n Messages, 0-1 Counter-referral. Patient (minimal identity; shown masked). AuditEntry (who, when, action, record, reason, flag).

## Status model
Created, Sent, Received, Accepted, Scheduled, Patient attended, Completed; terminal exceptions Declined and Cancelled (each with required reason code + optional note; "did not attend" is a Cancelled outcome with reason).

## URL and title rules
Routes use opaque referral ids only. No names, patient IDs or clinical terms in URLs, titles, notifications or analytics.

## Role/permission matrix (summary)
Clinician: create/cancel own facility referrals, view referrals where facility is party, reveal with reason. Coordinator: respond for own facility, verify own directory entry. Manager: aggregate views and progress only. Admin: users, facilities, audit, retention; no routine patient access. Everyone: MFA; break-glass with reason.
