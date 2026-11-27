# User Journeys (synthetic personas)

## J1: Clinician creates an emergency referral (Amina Wekesa, nurse, Mto Mkuu Health Centre; phone, patchy network)
1. Opens app (MFA-verified session). Home shows one dominant button. 2. Taps New referral. 3. Picks patient (name, sex, age only). 4. Priority Emergency, writes one-line reason, leaves clinical note included. 5. Chooses Ziwani (Verified 1 day ago). If she picks Mlima (stale), a dialog asks her to confirm by phone first. 6. Review screen lists exactly what is shared and what is not; she records verbal consent. 7. Send. Confirmation with reference and elapsed time. Pain points addressed: re-typing, no confirmation, unknown availability.
Offline variant: step 7 reads "Save to outbox". Home shows "1 waiting". When connection returns she taps Sync now (or it syncs automatically in Phase B). Failure: sync conflict if the facility was marked unavailable meanwhile: she is asked to re-confirm.

## J2: Receiving coordinator responds (Joseph Otieno, Ziwani SCH)
Inbox sorted by priority. Opens a Sent referral (marks Received, logged). Reads reason and consent; clinical note unlocks after Accept. Accepts; later schedules an appointment; when the patient arrives marks attended; after care sends a summary back, which completes the loop. Alternate: Decline requires a reason from a list and may suggest an alternative; Request info sends a message and keeps the referral open.

## J3: Referrer follows up
Sees Declined with reason and "Refer elsewhere". Sees Info requested and replies in Messages. Sees Completed with counter-referral summary.

## J4: Privacy-sensitive moments
Reveal identifiers: reason dialog, logged. Break-glass: user outside the referral gets locked page; confirms emergency reason; banner shown; flagged for lead review. Session timeout: 60-second warning, then sign-out; drafts remain encrypted on device.

## J5: Manager reads the network dashboard
Volumes, pending, median response, incomplete reasons. Facility comparison carries caution; small numbers hidden. Exports are aggregate and logged.

## J6: Admin
Invites a user (email, role, MFA required before first use); changes a role; resets MFA; explores the audit log with filters; edits retention placeholders after counsel advice; registers a facility as unverified.

## J7: Directory verification
Coordinator marks facility information verified (by/at recorded). Directory flags verification due after 7 days and stale after 30 days (thresholds configurable).
