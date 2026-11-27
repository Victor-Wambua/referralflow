# Design addenda (Phase B)

Fills the gaps listed in `design-review.md`: (1) supporting documents, (2) patient identity matching and duplicate detection, (3) Swahili copy, (4) offline behaviour. Follows `design-system.md` and `design-tokens.css`: calm, large, one primary action, status never by colour alone, no PHI in titles, URLs or notifications, "Synthetic demonstration data" banner everywhere. No new colours or fonts are introduced.

## A1. Supporting documents

**Where:** step 2 of New referral ("Add documents", optional) and a Documents card on Referral detail (Overview tab).
**Component:** "file row" built from the existing `.row` list pattern: icon, neutral label (type label such as "PDF document", size, "Added by <facility>"), a verification-style pill for scan state ("Scanned" ok, "Not scanned" warn, "Blocked" bad), and a secondary "Open" button (48px).
**Rules shown to the user (hint text under the picker):** PDF, JPEG or PNG, up to 5 MB, up to 5 per referral. "Do not put names in file names." The original file name is stored encrypted and is not shown in lists or used in URLs; rows show "Document 1, 2, ...".
**Review step:** the "what will be shared" list names the number of documents. The receiving facility does not see documents until it accepts (minimum necessary).
**Opening:** "Open" downloads through an authorised, audited endpoint (never a public URL). A banner states "Opening this document is logged".
**States:** empty ("No documents attached"), uploading (button disabled, text "Uploading"), error ("This file type is not accepted. Use PDF, JPEG or PNG."; "File is larger than 5 MB"; "File content does not match its type"), blocked-by-scan.
**Mobile:** the native file picker (camera or files); rows stack, Open is full width.
**Accessibility:** the input is a labelled file control; errors are role=alert under the field.

## A2. Patient identity matching and duplicate detection

**Principle:** there is no national patient identifier we may rely on (using national ID, SHA or Huduma numbers is a legal question: confirm with counsel). ReferralFlow therefore holds NO cross-facility master patient index. Patients are records owned by the referring facility; the receiving facility sees only what the referral shares.
**Identifier:** optional, typed (National ID, SHA number, Facility record number, Birth notification number, Other). Stored encrypted. A keyed hash (HMAC-SHA-256, separate key) is stored per patient for exact matching inside the same facility only.
**Match rules at "Add patient" (step 1), same facility only:**
1. Strong match: same identifier type and value. UI: a banner "A patient with this identifier already exists at your facility" with the masked name (initials) and two buttons: "Use this patient" (primary) and "This is a different person" (secondary; requires a one-line reason, logged).
2. Probable match: same normalised name tokens (order independent), same date of birth and same sex. UI: warning banner "Possible duplicate" with the same two buttons. Name spelling variants are NOT fuzzy-matched in v1 (limitation; transliteration is hard and false merges are a safety hazard).
**Duplicate referral guard (step 3):** an open (non-terminal) referral for the same patient to the same destination within 7 days shows a warning "A referral for this patient to this facility is already open" with "Open it" and "Create anyway" (reason required).
**Merging patients is not built** (limitation): wrong-patient merges are a clinical-safety hazard and need a reviewed procedure.
**Display:** lists show masked initials only. Reveal needs a reason from a list and is logged; revealed values disappear after 5 minutes or on sign-out.
**Age:** the referral stores sex and age in years at creation (minimum necessary shown to the receiving facility before acceptance); full date of birth stays encrypted on the patient record.

## A3. Swahili copy

**Approach:** UI strings are looked up through Laravel JSON translations (`lang/sw.json`); English is the source. A language switch (English / Kiswahili) is in Settings and on the sign-in screen; the choice is stored in the user's session and, once signed in, on the user row. Layout was checked for the longer Swahili strings (labels wrap; buttons keep 48px height).
**Scope in v1:** navigation, statuses, priorities, buttons, form labels, validation messages for core fields, banners, consent wording, notifications. Clinical terms are kept simple and common.
**Quality gate:** this copy was drafted without a native clinical translator. It MUST be reviewed by Kenyan clinicians and a certified translator before any pilot; consent wording in particular needs legal review. A test guarantees every translatable string in the views has a Swahili entry (completeness, not accuracy).
**Not done:** other languages, right-to-left, locale-specific dates (dates stay ISO-like and numeric).

## A4. Offline behaviour

**Honest scope.** Storing patient data in browser storage on shared or lost phones, encrypted with a key the same browser holds, is not a safe design for this product. Therefore **no patient data is ever stored offline**.
**What works offline:**
- A real service worker caches the static application shell (CSS, JS, icons) and an offline page. Authenticated HTML pages are NOT cached.
- The offline page lets a clinician save a **non-PHI draft**: priority, reason category and chosen destination (from a directory snapshot stored when the wizard was last opened online; facility names only, no patient data). Draft is stored in localStorage as a small JSON note and cleared on sign-out.
- When back online, "Resume draft" in the New referral wizard pre-fills priority, reason category and destination; the clinician then enters patient details online. Nothing is queued or sent automatically.
**UI:** an "You are offline" banner (existing banner.warn) with the sentence "Patient details cannot be saved offline. You can prepare the destination and priority now." The home outbox concept from the prototype (queued patient referrals) is intentionally NOT built; this replaces it. Reference-ID idempotent sync is therefore unnecessary in v1.
**Emergency fallback shown on the offline page:** "Emergency: phone the receiving facility now" (number from the last directory snapshot, which holds facility contact numbers; these are facility, not patient, data).
**Known limits:** no background sync; drafts are per device; clearing browser data loses them.
