# AM120 — Full Admin ROT Correction / Reset inside Main Job Card

## Decision / scope

Do **not** retire the separate Job Card Amount Editor (P55) before all of its confirmed controls work from the **Main Job Card > Mechanic & ROT Work Controls** for both PLANNED and COMPLETED ROTs. The earlier AM116 / AM117 / AM118 patches did **not** establish feature parity: AM116 runs where the P55 view is rendered, AM117 handles unstarted cancellation, and AM118 only enhances the billing modal.

## Target UI in each ROT card

- **Assign / Change Mechanic**: searchable, supports planning for multiple vehicles without pretending a simultaneously running ROT is permitted. Follow Check-In and role guards.
- **Admin Full Correction / Reset**: one workflow with Admin Unlock, required alteration reason, required Admin Note, field-specific correction of ROT selection/identity (where safe), Mechanic, Start, End, Std Hours, **Labour Qty, Labour Rate, Discount % / Discount Amount, GST %, GST Amount, Taxable and Final Amount**. Preserve master-driven SAC/tax basis.
- **Reopen Completed + Reset End**: separate, audited operation for true COMPLETED-to-reopened transition, if allowed by live backend state. Not equivalent to unlocking a completed ROT for billing/date corrections.
- **Delete ROT**: only never-started, unassigned, unlinked, WIP, invoice-free ROT via AM117 transactional audit; otherwise review/reversal.
- **History**: original before/after audit (including Qty, Rate, Discount and GST), assignment changes, ROT start/pause/resume/complete records, and time correction records. Never erase productive-time history to fake a reset.
- **Unified billing row in the SAME ROT card**: `ROT / Labour | Mechanic | Start | End | Std Hrs | Qty | Rate | Disc % | Disc ₹ | Taxable ₹ | GST % | GST ₹ | Final ₹ | Alter | Delete | History`. Display totals live, save only through validated authorized backend; compact and responsive.

## Backend guard and financial invariants

- Reuse the existing `erp.job-cards.labour.alter` controller and existing work-status routes; do not create an unaudited save path.
- Correct financial values only when taxable/GST basis reconciles; previous historical GST mismatch guard must remain.
- **Billing math**: determine the existing line's unit/rate basis from its authoritative master/service: if price is per hour, base = Qty × billable Std Hrs × Rate per hour; if fixed per service, base = Qty × Rate per service. Default Qty=1 only where an explicit unit field is introduced; do not silently multiply Std Hrs twice or reinterpret historical rates. Discount ₹ is either entered with Disc % derived or calculated from Disc %; `Taxable = base - Discount`, `GST ₹ = Taxable × GST %`, `Final = Taxable + GST` for exclusive basis. For inclusive lines, derive tax correctly from inclusive price and preserve existing configured basis. Never stack an additional GST on already inclusive totals. Recompute server-side, including rounding; the browser preview is not financial authority.
- Qty requires schema/service/API support and migration/backfill review if current labour rows have no quantity column; do not invent a field that silently fails to persist. Check tax totals, approved estimate price basis, linked invoices and old ROT records before enabling editing.
- Admin correction of GST rate / amount is permitted only where legally applicable, master/HSN-SAC and existing invoice status allow it, with reason and before/after audit; no arbitrary manual GST override bypassing tax rules.
- Approved Estimate original data never overwritten by JC correction; preserve source_estimate_item_id, change audit and reason.
- Original session/segment/event timestamps remain immutable in history; authorized correction can modify operational summary values with before/after records.
- Invoiced / Closed job requires proper Credit Note and financial reopen before modification; preserve Money Receipt and ledger history.
- Never allow completed ROT reset to start a new work timer retroactively without explicit service validation.

## Required acceptance tests before retiring page

1. PLANNED unassigned ROT: Assign, Change, edit Std Hours, Qty, Rate, Discount %/₹, GST %, review Taxable/GST ₹/Final ₹, save, refresh persists, audit correct and totals recalculate.
2. COMPLETED ROT: unlock with reason and admin note, edit authorized dates/mechanic/hours/Qty/Rate/Discount/GST, verify audit, tax totals and TAT with original sessions unchanged.
3. True Reopen/Reset End Time: completed state moves to a valid unlocked state, cannot start before vehicle inward, and no previous time event lost.
4. Negative tests: invoice-linked, closed JC, unauthorized user, already-running ROT, missing reason, invalid Qty/Rate/Discount/GST, double-applied GST, historical GST mismatch, linked purchase, duplicate submit.
5. Main JC desktop/mobile controls complete. Only then remove all separate Amount Editor links and old GET navigation, with redirect/rollback. Keep old controller/route while referenced.

## Next implementation gate

Run `scripts/AM120_COMPLETE_ROT_CORRECTION_READONLY.sh` on staging. It collects only route, view and controller *source excerpts* for exact field names / variable scope, without DB queries. After checking the result, implement guarded per-card P55 parity rather than merely displaying a shortcut or an unrelated Edit Labour Billing popup.