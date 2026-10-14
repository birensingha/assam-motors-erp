# P16 — Admin Approval Desk for Reversal Reviews

## What was missing

The previous P15 page registered `REVERSAL_REVIEW_ESTIMATE` requests but did not provide a decision endpoint. Two requests had already been registered for JC-2026-0025. Requests by themselves do not reverse Estimates.

## Added

- A new **Admin Approval Desk** section on the existing Job Card Reversal Centre page.
- Latest pending request for each scope, shown with request ID, reason and submission time.
- Only authenticated active `ErpAuthUser` administrators with `isAdmin()` can record approval/rejection decisions.
- Two explicit statuses: approved for reconciliation or rejected, append-only in `job_card_change_audits`. Duplicate/repeated decisions are refused.
- Already-cancelled requests are presented as done without a redundant Cancel action.
- Approval is **NOT** execution of Estimate cancellation/void, purchase/vendor reversal, stock movement, invoice, payment or ledger adjustment.
- Approved Estimate review changes step 3 to **APPROVED — REVERSAL PENDING**, not COMPLETE.

## Staging verification

GitHub Actions staging run 38509845361 passed:

- `P16_COMPILED_BLADE_AND_CONTROLLER_PASS`
- `P16_REAL_ADMIN_REVIEW_RENDER_PASS`
- `P16_REVIEW_QUEUE_COUNT=5`
- `P16_ESTIMATE_REVIEW_PENDING_PASS`
- `P16_NO_REQUEST_DECISION_OR_BUSINESS_DATA_MUTATED`
- `P16_STAGING_APPROVAL_DESK_DEPLOY_PASS`

Staging source backups are recorded in `storage/app/p16_approval_desk_backup_...`. No user actions were auto-submitted.

## Follow-up scope

Actual **approved Estimate reversal execution** remains a separate, protected financial/workshop operation. It must first verify linked labour ROT, stock, invoices, purchase/vendor liability and job card state, then write an immutable audit event and safely update eligible Estimate status without deleting history.
