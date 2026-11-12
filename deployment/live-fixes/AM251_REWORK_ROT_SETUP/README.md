# AM251 — No-Charge Complaint Rework ROT: Create & Link in One Step

## Use case
For Job Card #11, Complaint #1: original Kapil Rajbongshi ROT `ROT261103163521SVOX8` is **Completed and preserved**; receiving mechanic Firajul Hoque (AM7887) was separately assigned by AM246. Current UI correctly says **New Rework ROT pending**. AM250 displays that status on the Job Card but cannot track productive time until a native ROT exists and is linked.

## Implementation
Inside the existing complaint rework page's Step 2, provide a **Create & Link No-Charge ROT** option. Admin must choose a specific active Labour/ROT Master with positive standard hours, enter a reason and check the no-customer-charge acknowledgement. Nothing is created merely by opening the page.

The POST controller executes a single transaction:
1. Requires active Owner/Admin via the existing ERP `auth:web` and `EnsureErpAdmin` protected route. Lock complaint, assignment and Job Card. Verify same JC, different assigned mechanic, original Completed ROT and unchanged staff, WIP status, no active invoice and no already-linked ROT.
2. Validate chosen Labour Master and active receiving mechanic; block duplicate when an eligible new Assigned ROT already exists.
3. Create a new native `job_card_labour` row **billing_included=0, labour_rate=0, total=0, GST=0**, while preserving positive standard hours for productivity timing.
4. Call the ERP's **actual** `RotAssignmentService::assign`: it creates the real string ROT session ID, staff mechanic segment, assignment audit and attendance/check-in enforcement. It **does not start the timer**. If staff is not checked in, or another validation fails, the complete transaction rolls back (no partial labour line).
5. Atomically link the new native ROT to the complaint, audit it in `am_complaint_rework_audits` and `job_card_change_audits`. Confirm customer billing header values unchanged before commit. No old ROT modification.

After confirmation, receiving staff uses native Check-In, Start, Pause, Resume, Complete, and AM250 Job Card **Complaint Rework Progress** automatically reflects the native status. Completing a ROT alone does not independently certify rectification/QC PASS.

## Safety and scope
- **No automatic new business records during installation.** New ROT only after explicit Admin form submission; not via GitHub Actions.
- New customer charge ₹0 and excluded from billing. Original ROT and financial totals preserved.
- Staff Check-In prerequisite is enforced by the native service, not bypassed.
- No unapproved repair work, payroll incentive, employee punishment, QC bypass or Android build.
- Installer `check|apply|verify|rollback` makes a private backup, validates actual Blade + PHP and existing Admin middleware, verifies rendered JC11 form without POST and restores files on errors.

## Verification
GitHub Actions deploy uses existing Hostinger STAGING SSH secrets; link to run attached to PR after verification. Browser:
- `/erp/rot-work-complaints/1/rework` shows new **Create & Link No-Charge ROT** form above existing manual-link dropdown.
- No ROT created just by browsing.
- Admin chooses genuine Labour/ROT master and submits only when work will be assigned to checked-in receiving staff; if not checked in, no partial entry.
- After successful form POST, linked ROT ID shows on same page, JC11 progress updates on refresh, Staff Portal sees actual native ROT controls.
