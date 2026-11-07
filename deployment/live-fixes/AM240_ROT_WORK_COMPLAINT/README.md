# AM240 — Completed ROT Work Complaint + Monthly Performance

## Business case
JC-2026-0011 / AS01HC0595 / Kapil Rajbongshi AM9472 / ROT-CUS-0211 — Rear Torsion Beam Axel Assy R&R. The screenshot shows this ROT as COMPLETED. The actual customer complaint has not been supplied; do not invent a fault or populate a complaint automatically.

## Code scope
- New Report Work Complaint action on a Completed ROT card.
- Separate am_rot_work_complaints records and append-only am_rot_work_complaint_audits.
- Staff ID and job-card ID from the existing completed ROT. The reporter cannot reassign blame by editing the form.
- Admin-only authenticated create and investigation. Outcomes: UNDER_REVIEW, WORKMANSHIP_CONFIRMED, PART_FAILURE, CUSTOMER_MISUSE, NOT_WORKMANSHIP, INCONCLUSIVE.
- REPORTED is not a workmanship verdict. Only WORKMANSHIP_CONFIRMED contributes to confirmed monthly count.
- Monthly report grouped by complaint reported month (Asia/Kolkata), separate Reported, Confirmed, Pending columns.
- Original ROT status, times, rates, billing, QC, Job Card and attendance are never modified.

## Safe staging flow
Review the proposed patch on GitHub first. Source checkout: ~/assam-motors-deploy; staging root: ~/domains/assammotors.com/assam-erp-staging.
1. Fetch branch feature/am-work-complaint-rot-monthly-20261107.
2. Checkout the fetched branch to make the installer files available.
3. Run: python3 deployment/live-fixes/AM240_ROT_WORK_COMPLAINT/staging_install.py check ~/domains/assammotors.com/assam-erp-staging
4. Only after successful preflight and reviewer approval, run the same script with apply instead of check.
CHECK is read-only. Installer refuses on unexpected ROT schema, changed Blade action row, missing routes, or source collisions.

## Acceptance checklist
- JC-11 completed ROT-CUS-0211 shows complaint action, retains its original work duration and billing.
- Wrong job-card/ROT combination returns 404; only completed ROT accepts creation.
- Reported case links staff ID, ROT and Job Card; audit is populated.
- Reported/Pending appear in monthly view before supervisor confirmation, while Confirmed stays zero.
- Supervisor decision WORKMANSHIP_CONFIRMED counts as confirmed; another outcome does not.
- Review editing appends audit, does not rewrite original ROT or history.
- QC, Ready for Delivery, Close, Invoice, attendance, and ledger remain unchanged.

## Limitations
- Full current Laravel source is not in this repository; live ROT schema and Blade anchors must be checked on staging.
- Dedicated monthly complaint report is implemented. Embedding in the current HR performance dashboard remains a separate integration task after inspecting its source.
- Staff self-response workflow, rework costs and automatic HR report embedding are not yet implemented.
- No automatic penalty or salary deduction.
