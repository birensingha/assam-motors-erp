# AM250 — Display Complaint Rework Progress on the Job Card

User request: after assigning **Firajul Hoque (AM7887)** to Complaint #1 for Job Card #11 (original completed ROT performed by Kapil Rajbongshi), where can Admin see new mechanic work progress and what does **Step 2 — Link a Separate ROT** mean?

## Problem
AM246 saves a **rework assignment** in `am_complaint_rework_links` and notifies staff; it does *not* create a new `rot_sessions` record. The screenshot shows `ASSIGNED` and `Pending creation / linking`. Therefore the original Completed ROT must remain intact and **no real productive hours are tracked yet**. A second native job ROT must be legitimately created with the assigned receiving mechanic and linked before normal Start/Pause/Resume/Complete timers apply.

## Change
A read-only **Complaint Rework Progress** panel is included inside the existing Admin Job Card lifecycle panel, above Workshop Lifecycle & Billing. For each actual complaint assignment linked to this Job Card, display:
- Complaint number, assigned receiving mechanic name/code
- **ROT PENDING** if no linked native ROT, otherwise its actual status (ASSIGNED, RUNNING, PAUSED, COMPLETED)
- linked ROT session ID, actual corrective instructions and direct Admin complaint link
- callout that a native ROT completion **does not itself certify rectification/QC PASS**.

Only renders when a complaint rework assignment exists for the current Job Card. No new work record or billing line is created; no changes to original mechanic's ROT, invoices, attendance, payroll, QC or staff identity.

## Code
- New `resources/views/work-complaints/rework-jc-panel.blade.php` (read-only).
- Insert exactly one `@include('work-complaints.rework-jc-panel')` above the existing workshop lifecycle card in `resources/views/job-cards/lifecycle-panel.blade.php`.
- Protected `check|apply|verify|rollback` installer backs up original Blade, compiles original and new Blade through the real Laravel compiler and PHP syntax check, and executes a read-only smoke render of JC #11. Stops and restores code on failure.

## Deployment
Use automated GitHub Actions to deploy with preflight on STAGING. Do not run normal ROT assignment/create during deployment. Actual Admin UI acceptance is pending until browser logged-in verification.

## Step 2 still necessary
To track real working time, Admin must create **one separate native assigned ROT for receiving mechanic Firajul** through the Job Card's normal work controls and then link it in the Complaint #1 rework page. **Confirm nonbillable handling with current billing controls**; this display package does not add a billing exemption. Avoid adding customer charge solely for warranty/comeback rework. Link must match Job Card and receiving mechanic, and original ROT remains completed. Real QC verification separately required.
