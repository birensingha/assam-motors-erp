# Phase 9 — Admin Alert Review, Notes & Excel Export

This phase builds on Phase 8's server-global Staff alert tables.

## Admin capabilities

- View all active / snoozed / escalated / under-review / resolved alerts.
- Filter by date, Staff, alert type, status, Job Card and Vehicle.
- Open a complete alert timeline.
- Mark an escalated alert **Under Review**.
- Add one or more Admin notes without resolving the alert.
- Resolve only after the related business condition is satisfied.
- Keep full audit history of Admin review, note and resolution actions.
- Export **Staff Summary** + **Alert Detail** to Excel.

## No silent dismissal

Admin Review does not hide the alert from Staff.

`UNDER_REVIEW` remains unresolved and remains in the Staff poll response until the actual Attendance / ROT / Inspection requirement is satisfied.

## Excel workbook

Preferred workbook name:

`staff-alert-compliance-YYYY-MM-DD_HHMM.xlsx`

Sheets:

1. **Staff Summary**
2. **Alert Detail**

If the live ERP already has PhpSpreadsheet installed, use the included XLSX example.
If it does not, add the dependency through the project's normal Composer deployment process rather than bundling library source into this patch.

See:
- `ADMIN_ALERT_WORKFLOW.md`
- `admin_alert_api.php.example`
- `ALERT_EXPORT_SCHEMA.md`
- `admin_alert_export_xlsx.php.example`
- `admin_alert_compliance_reference.html`

The live ERP source is not present in this repository, so this package is an integration contract/reference implementation, not a claim of staging deployment.
