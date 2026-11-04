# AM234 — Assam Motors Parts Purchase V2: new Job Card picker

Status: **deployed to Hostinger STAGING on 2026-11-04**. Automated read-only browser search / selection PASS (workflow #41800281485). A real Purchase Save/financial posting has NOT been tested.

## What is verified
- The user's staging Laravel service returned 33 Job Cards via `searchJobs('',200)`.
- `JC-2026-0011 — AS01HC0595 — SUMEN ROY` is present, with database ID 11.
- The existing Blade form renders the real `lines[index][job_card_id]` select.
- Repeated datalist / CSS workarounds on the current page failed to show suggestions.

## Behaviour of replacement
- `AGAINST=STOCK`: JC picker disabled, existing stock posting unchanged.
- `AGAINST=JOB`: immediately shows matching Job Cards.
- Search accepts Job Card No., registration No. and customer **substrings**.
- `sum` returns `JC-2026-0011 — AS01HC0595 — SUMEN ROY`; clicking links **ID 11**.
- Never auto-selects just because text matches; typing after a selection clears stale ID.
- Arrow Up/Down and Enter supported; each purchase line has independent state.
- No changes to Part Search, GST, Qty, Discount, round-off, payment, inventory posting, or `PurchaseService`.

## Deliverables
- `jc-picker-function.js` — self-contained replacement for only `wireJobFilter(row)`.
- `jc-picker.css` — own menu styles, without datalist.
- `prepare.php` — guarded staging-source dry-run/apply with backup and rollback if either write fails.
- `qa/picker-node-test.js` — DOM simulated regression test of selection, lookup and stock toggle.

## Source / deployment limitation
The live Hostinger Laravel application **is not checked into this GitHub repository**.
The actual website serves JS from `public_html/staging/js/purchase-entry.js`, not
`assam-erp-staging/public/js/purchase-entry.js`. The guarded AM234 workflow
applied the new picker to both the Laravel source and the actual served asset,
verified the public HTTP JS marker, then completed browser acceptance without
creating a purchase. Previous failed attempts auto-rolled back. The branch
remains draft because financial posting and reconciliation require separate QA.

## Rebuild after JC selection
The next distinct backend feature is mandatory reconciliation:
- STOCK purchases -> stock receipt, inventory ledger, stock issues/returns.
- JOB purchases -> immutable JC-linked receipt, part allocation/issue/fitting,
  billing, and audited reconciliation before JC financial closure.
This cannot safely be implemented as an isolated UI patch and must be validated
against the live posting schema and invoice/stock guards.

## Acceptance
1. Open Parts Purchase; change Against from STOCK to JOB.
2. Search `sum`, `JC-2026-0011`, or `AS01HC0595`: show SUMEN ROY.
3. Select result: posted `job_card_id` is 11.
4. Change search text: linked ID is cleared; saving should be blocked until valid selection.
5. Switch to STOCK: JC optional; no JC allocation posted.
6. Test with genuine purchase data in staging after approval; verify no duplicate stock,
   vendor ledger, GST or JC billing entries.
