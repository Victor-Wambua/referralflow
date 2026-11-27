# User guide (synthetic demonstration system)

Every screen says "Synthetic demonstration data. Not for real patients." Use made-up people only.

## Signing in
1. Enter your work email and password. 2. Enter the 6-digit code from your authenticator app (any TOTP app). First time: set up the app with the setup key shown, enter a code, and save the recovery codes (shown once). 3. After 15 minutes without activity you are signed out; a warning appears 60 seconds before. 4. Sensitive actions ask for your password and a fresh code again.
Language: English or Kiswahili (Settings or the sign-in page). The Kiswahili text is a draft and has not been reviewed.

## Clinician: create a referral
Home > New referral. Step 1: patient (tick the synthetic confirmation; if the patient already exists at your facility you are asked to use them or say why it is a different person). Step 2: priority (Routine, Urgent, Emergency), reason category, a one-line reason (no names), optional clinical note, form details, optional documents (PDF/JPEG/PNG up to 5 MB, 5 files, no names in file names). Step 3: choose the facility; stale or unverified facilities need a phone confirmation first. Step 4: review exactly what is shared, record consent, Send. You can save a draft instead. Before the receiving facility accepts, it sees only priority, reason, sex and age.
After sending: follow the tracker; read messages; cancel (reason required); withdraw consent (sharing stops).
Needs attention on Home shows declined referrals, information requests and overdue referrals.
Reveal identity: use "Reveal identity", pick a reason; it is logged and hides again after 5 minutes.
Offline: patient details are never saved on the device. The offline page lets you prepare a draft without patient details (priority, reason category, destination) and shows facility phone numbers saved earlier.

## Coordinator: respond
Inbox (Need response, Accepted and scheduled, Awaiting summary). Opening a Sent referral marks it Received. Accept, or Decline (reason required, optional note such as an alternative facility); Request more information; Schedule appointment (and change it); Patient attended; Send summary and complete (outcome + summary, required to close the loop); Cancel (including "patient did not attend"). Clinical note and documents appear after you accept.

## Directory
Search by name, county or service; filter by type and verification. Each facility shows when and by whom availability was verified (stale after 30 days, due after 7; configurable). Coordinators and facility admins of that facility can verify and add services; anyone can report a change.

## Dashboard (coordinator, facility admin, network analyst, platform admin)
Created, sent, accepted, pending, completed, median times (acknowledge, decision), incomplete rate with reasons, weekly volume, receiving-facility table (groups under 5 are hidden; alphabetical, not ranked). Definitions: cohort = referrals sent in the period; pending = Sent or Received; acceptance rate = accepted / (accepted + declined); incomplete rate = (declined + cancelled) / (completed + declined + cancelled). Exports are logged and need re-authentication. Differences between facilities reflect case mix, capacity and distance as much as performance.

## Emergency access (break-glass)
If you are not part of a referral and a patient's safety depends on it, open the referral link, give a reason (20+ characters), confirm the emergency, and re-authenticate. You get read-only access for 60 minutes. Your facility lead is notified and reviews it.

## Facility admin
Users and roles (invite with a one-time password, change role, suspend, reset MFA or password), emergency access reviews, data requests (approve export or deletion with re-authentication), access log for the facility.

## Platform admin
Facilities (register, import a KMHFL-style CSV), audit explorer (filters, chain verification, export), retention and verification settings (placeholders: confirm with counsel), legal holds, referral form versions. No access to referral content.

## Network analyst
Aggregate dashboard and directory only.

## Data requests
Clinician or coordinator opens an export/deletion request for a patient; the facility admin decides.
