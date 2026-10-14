# P17 — Separate Admin Approval Desk

Dedicated per–Job Card approval page deployed to STAGING ERP on 2026-10-14.

## Paths

- Reversal Centre: `/erp/job-cards/{job}/reversal` (five-point completion tracker and linked records).
- Owner/Admin approval page: `/erp/job-cards/{job}/reversal/approvals`.
- Existing audited decision POST: `/erp/job-cards/{job}/reversal/reviews/{review}/decision`.

Reversal Centre now has a link to the dedicated approval page, with counts of awaiting decisions and approved reviews. Approval form no longer appears on Reversal Centre. Decision POST returns to dedicated page, maintaining request state.

The approval page reuses the existing protected controller data and P16 approval rules. It shows current requests per scope, statuses, reason, and supports Approve for Reconciliation/Reject with explicit confirmation and immutable audit.

**Approval does not execute Estimate, stock, vendor, ledger or invoice reversal.** No decisions were auto-submitted.

## Deployment verification

GitHub Actions run `38511711171`:
- `P17_PHP_AND_BOTH_COMPILED_BLADE_CHECK=PASS`
- `P17_DRY_RUN_NO_SOURCE_OR_DATA_WRITES`
- `P17_SEPARATE_ADMIN_PAGE_RENDER_PASS`
- `P17_REVERSAL_PAGE_LINK_ONLY_PASS`
- `P17_ACTIVE_ADMIN_PERMISSION_PASS`
- `P17_NO_APPROVAL_ACTION_OR_BUSINESS_DATA_MUTATED`
- `P17_STAGING_SEPARATE_APPROVAL_DESK_PASS`

Guarded source backup created under `storage/app/p17_separate_desk_backup_...`; original source and business data unchanged except source files and the new Blade page.

Note: P17 is per Job Card; a global, cross–Job Card approval inbox is not yet part of this release.
