# AM252 — Identify Original vs Repeat Complaint ROT and lock No Customer Charge

## Verified live STAGING data (JC #11)
Read-only diagnostic, 2026-11-12:
- **Original completed ROT**: `ROT261103163521SVOX8`, Kapil (staff ID 2), labour #108, `billing_included=1`. This is the original customer work, do not reverse or modify it when doing complaint rework.
- **Repeat complaint corrective ROT**: `ROT261112204503A70BG`, Firajul (staff ID 3; AM7887), labour #156, status Assigned, `labour_rate=0`, `gst_percent=0`, `total=0`, `billing_included=0`, `billing_reason` names Complaint #1. Complaint assignment status `ROT_LINKED`.
- Job Card #11 `labour_total=12897.40` equals billable Labour sum `12897.40`. Invoice creation in `AdminJobCardLifecyclePageController` filters `billing_included=1` and invoice print snapshot filters similarly.

## AM252 changes
- In the existing Job Card Labour ROT cards, show a **ORIGINAL WORK · COMPLAINT #1** link on original ROT, and **REPEAT COMPLAINT REWORK #1** plus **₹0 INTERNAL NO CUSTOMER CHARGE** labels on the actual corrective ROT. Labels come from exact string ROT IDs in `am_complaint_rework_links` rather than comparing ROT names.
- If any corrective ROT unexpectedly becomes billable or nonzero, visually display an urgent BILLING ALERT instead of claiming no charge.
- Add server-side refusal to convert a linked complaint corrective ROT into customer billable or reprice it via **AdminRotBillingController**, **JobCardRotAlterationController**, or **AdminWorkshopControlPageController::updateJobLine**. Keep original work editable only under its pre-existing controls.
- Preserve existing invoice creation filtering `billing_included=1`; do not modify invoice issuing or snapshot code.
- No data or migration changes during deployment, no staff working timers, original Completed ROT, complaint decision, customer financial totals, payroll or historical invoice changes.
- Guarded installer `check|apply|verify|rollback` verifies actual source, Blade compilation, patched PHP lint and read-only rendering of original and corrective cards and real DB no-charge fields; backup originals and restore on installer failure.

## Testing
GitHub Actions deploy; authenticated browser acceptance: open `/erp/job-cards/11`, expect distinct original and rework badges, new complaint ROT zero charge, no old ROT changes. Customer invoice MUST NOT include corrective labour #156 (non-billable). Real POST billing security test is not run against live data; source and guards are verified.
