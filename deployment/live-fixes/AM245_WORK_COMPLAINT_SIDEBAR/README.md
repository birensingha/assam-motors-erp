# AM245 — Admin Sidebar Work Complaint Register

This is the menu-only follow-up to AM240 / AM242 / AM243 / AM244. It adds **WORK COMPLAINT REGISTER** immediately after the existing **JOB CARDS** item in the authenticated NEW/STAGING ERP sidebar (the LIVE WORKSHOP group), linking to the existing named route `erp.rot-work-complaints.register`.

## Scope and safety
- **No database, Job Card, original ROT, complaint, staff, billing, controller or routes changes.**
- Searches known shared Laravel sidebar Blade paths for exactly one `Job Cards` anchor inside `nav.sidebar-nav`. If the installed file structure differs, **preflight STOPs** instead of guessing or editing.
- Clones the native Job Cards link classes and icon to preserve styling, adjusts target route, visible text and active-state check, leaving the original Job Cards link untouched.
- Uses `surrogateescape` to preserve non-UTF-8 source bytes, Laravel Blade compiler + PHP lint on baseline and modified navigation, read-only route verification, backup, source hash and guarded rollback.
- Does not create a rework assignment action. AM241 remains separate and is not safe to deploy until string ROT session IDs and native assignment workflow are reconciled.

## Terminal
Fetch GitHub branch `feature/am245-work-complaint-sidebar-20261110` into a temp directory via git archive and run:
`python3 install.py check ~/domains/assammotors.com/assam-erp-staging`
Then only after preflight succeeds:
`python3 install.py apply ~/domains/assammotors.com/assam-erp-staging`

## Acceptance
- In Admin STAGING, left sidebar under JOB CARDS should show WORK COMPLAINT REGISTER.
- Clicking it opens existing /erp/rot-work-complaints (no redirect to legacy).
- Result #1 and existing Monthly Performance links still open, and Job Cards link still functions.
- No duplicate menu items, no current CSS/JS sidebar scroll regressions.

## Current status
GitHub code prepared; actual Hostinger installation and browser verification have not yet been done.
