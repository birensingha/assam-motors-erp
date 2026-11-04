# AM235 — Parts Purchase Reconciliation Register (STAGING)

**Deployed and browser-verified:** 4 November 2026.

- Read-only register: `GET /erp/purchases/reconciliation`
- Links from **Purchase Register** and **New Parts Purchase**.
- Backend controller, route, and Blade table deployed with guarded backups.
- Corporate compact styling separately corrected and verified.
- Existing JC picker **AM234** preserved and browser-tested.
- No business/financial data created, edited, issued, invoiced, or transferred.

## Verified workflow results

- [Register + search / filter](https://github.com/birensingha/assam-motors-erp/actions/runs/41802529939): PASS, no purchase posting.
- [Corporate table styling and full browser smoke](https://github.com/birensingha/assam-motors-erp/actions/runs/41803099868): PASS.
- Browser evidence is available in workflow artifacts.

## Source-based logic

**STOCK purchase**:
- Existing `PurchaseService::savePartBatch` sets `job_card_id=NULL`.
- Inserts `workshop_purchases` row and increases `parts_master.stock_qty`.
- Adds `inventory_movements.PURCHASE_IN` with `job_card_id=NULL`.
- Register compares aggregate active purchased quantity with matching receipt movement quantity, reporting either `Stock Receipt Matched` or `Stock Ledger Review`.

**JOB purchase**:
- Existing Purchase Save rejects a missing/nonexistent/cancelled JC ID.
- It creates a `PURCHASE_IN` movement bearing the JC ID.
- Job Card Parts issue is a **separate** `JOB_ISSUE` movement; fitting is a separate state.
- Register groups all active purchases by JC + Part, compares purchased/received/issued/fitted quantities and reports pending issue, partial issue, due billing, fitting review, or invoice linked.

**Important limitation:** An active invoice presently references the JC and reads current `job_card_parts`. Item-wise frozen billing allocation to each individual purchase line is not demonstrated by this schema. `Invoice Linked / Verify` is **not** proof of the individual part being billed. Quantity summaries use all-time records for groups selected by filter dates, not only filtered-period quantities.

## Status of strict mandatory reconciliation

**Not implemented in AM235.** Final JC closure/invoice must not be blocked on an inferred aggregate without first adding audited exceptions for unused/returned/transferred parts, authoritative quantity allocation, and source-level tests of `JobCardPartsAutoClose` plus invoice generation. These are separate backend workflows. Do not call JC financial reconciliation fully complete or record an invoice as proven billed based on this report alone.

## Deployment safety

- `apply.py check` checks exact route/view anchors and AM234 picker presence without writing.
- `apply.py apply` backs up affected files, creates new read-only route/controller/view, and prints backup path.
- `rollback.py` restores original routes/views and deletes only the new report controller/view.
- `polish.py` corrected the stylesheet for the actual Laravel ERP layout; the checked-in view now retains that inline style.
- All browser QA is non-posting; do **not** use real stock purchases for automated tests without an isolated sandbox.

## Next technical gate

1. Audit `JobCardPartsAutoClose` and stock issue / transfer / returns code with actual reconciliation authority.
2. Create immutable per-purchase allocation journal with audited reasons and source item references.
3. Add safe JC Close / Invoice guard using **reconciled allocations**, with approved exceptions.
4. Verify an isolated transaction rollback test then real user-accepted staging purchase workflow.

