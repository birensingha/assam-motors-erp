# AM241 — Full OSL Supplier Purchase Edit + Audited Bill Void

**Staging code installed 4 November 2026.**

## User request
Like the Parts Purchase editor, the OSL Purchase editor must open as a supplier **bill-level** edit screen with **all lines** and a visible **Delete** button. The previous `OSL Cost Editor` covered only single-line quantity/rate/discount/GST and had no bill-level Delete action.

## Delivered on staging

- Purchase Register OSL **✏ Edit** and Purchase View **✏ Edit OSL Purchase** now both open:
  `GET /erp/purchases/osl-batches/{purchase}/edit`
- Multi-line supplier bill form with header **Vendor / Purchase Date / Supplier Invoice**.
- Existing OSL lines shown in one table with linked JC/Vehicle, OSL Master, Work Description, Qty, Vendor Rate, Discount %, Supplier GST Basis, GST %, read-only linked JC customer pricing and purchase preview.
- New OSL lines can be added by selecting a **pre-existing, unpurchased JC OSL billing line**. This avoids creating duplicate customer billing from supplier inward.
- Remove Line soft-deletes the selected existing purchase line on audited Save.
- Narration, mandatory Correction Reason, GST preview, Before Round Off, Round Off and Grand Total.
- Separate **🗑 Delete / Void Purchase** form at bottom, visible as soon as the page opens, requiring an explicit reason and confirmation.
- Revisions keep the existing supplier purchase numbers and batch number and write before/after snapshots to `purchase_audit_logs`.
- A whole-bill Void soft-deletes active purchase lines, flags the supplier bill batch DELETED, and retains audit history. **It does not silently delete JC customer OSL billing lines**.

### Explicit financial safeguards
- Block changes if linked Payment Voucher / payment allocations exist.
- Block changes if linked Job Card is Closed, Invoiced, Cancelled or has active invoice.
- Require admin access, mandatory correction/void reason and expected supplier line totals for stale-concurrency protection.
- Preserve the one-purchase-ever rule for JC OSL billing lines, including reversed purchases.
- Do **not** edit JC customer billing prices from the supplier purchase form; those changes require the JC audited billing workflow to avoid corrupting customer amounts/invoice.
- Existing purchase line JC linkage remains locked to avoid cross-Job Card billing mismatches. New lines link to existing JC OSL labour lines.
- Standalone legacy OSL records without batch IDs cannot add multiple lines to an old single-line bill.

## Verified
[AM241 guarded staging deploy + authenticated Chrome browser PASS](https://github.com/birensingha/assam-motors-erp/actions/runs/41827164002)

- PHP syntax on controller and routes PASS.
- Laravel Blade `view:cache` compilation PASS.
- All 3 OSL bill Edit/Update/Void routes registered.
- Purchase Register OSL Edit button opens new form.
- Vendor/date/invoice fields and existing lines render.
- Add and unsaved Remove controls tested in browser.
- GST summary and Delete/Void form with required reason visible.
- Existing Part Purchase Edit preserved.
- **No real supplier purchase updated or voided during QA.** Browser QA is non-posting. Financial write-path unit/integration testing remains a separate gate before calling financial posting fully validated.

## Files and rollback
`apply.py check` checks exact route and link anchors without writes.
`apply.py apply` backs up existing `routes/web.php`, Purchase Register and Purchase View, and installs controller/view.
`apply.py rollback` restores original files and removes AM241 controller/view if newly created.
The staging backup was printed by the GitHub deploy job.

## Known scope boundary
The full supplier document is editable for cost-side values, but customer billing changes, transfers of already-linked JC OSL lines, corrections after voucher allocation, and invoice reversal are **not** silently applied. A complete end-to-end financial reversal/reposting flow requires separate audited reconciliation with Money Receipt, Vendor Ledger and Credit Note.
