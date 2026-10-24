# AM120 — Full Admin ROT Correction / Reset inside Main Job Card

## Decision / scope

Do **not** retire the separate Job Card Amount Editor (P55) before all of its confirmed controls work from the **Main Job Card > Mechanic & ROT Work Controls** for both PLANNED and COMPLETED ROTs. The earlier AM116 / AM117 / AM118 patches did **not** establish feature parity: AM116 runs where the P55 view is rendered, AM117 handles unstarted cancellation, and AM118 only enhances the billing modal.

## Target UI in each ROT card

- **Assign / Change Mechanic**: searchable, supports planning for multiple vehicles without pretending a simultaneously running ROT is permitted. Follow Check-In and role guards.
- **Admin Full Correction / Reset**: one workflow with Admin Unlock, required alteration reason, required Admin Note, field-specific correction of ROT selection/identity (where safe), Mechanic, Start, End, Std Hours, Labour Rate, Discount.
- **Reopen Completed + Reset End**: separate, audited operation for true COMPLETED-to-reopened transition, if allowed by live backend state. Not equivalent to unlocking a completed ROT for billing/date corrections.
- **Delete ROT**: only never-started, unassigned, unlinked, WIP, invoice-free ROT via AM117 transactional audit; otherwise review/reversal.
- **History**: original before/after audit, assignment changes, ROT start/pause/resume/complete records, and time correction records. Never erase productive-time history to fake a reset.

## Backend guard and financial invariants

- Reuse the existing `erp.job-cards.labour.alter` controller and existing work-status routes; do not create an unaudited save path.
- Correct financial values only when taxable/GST basis reconciles; previous historical GST mismatch guard must remain.
- Approved Estimate original data never overwritten by JC correction; preserve source_estimate_item_id, change audit and reason.
- Original session/segment/event timestamps remain immutable in history; authorized correction can modify operational summary values with before/after records.
- Invoiced / Closed job requires proper Credit Note and financial reopen before modification; preserve Money Receipt and ledger history.
- Never allow completed ROT reset to start a new work timer retroactively without explicit service validation.

## Required acceptance tests before retiring page

1. PLANNED unassigned ROT: Assign, Change, Edit Std Hours, Rate, Discount, refresh persists, audit correct.
2. COMPLETED ROT: unlock with reason and admin note, edit authorized dates/mechanic/rate/discount, verify audit and TAT with original sessions unchanged.
3. True Reopen/Reset End Time: completed state moves to a valid unlocked state, cannot start before vehicle inward, and no previous time event lost.
4. Negative tests: invoice-linked, closed JC, unauthorized user, already-running ROT, missing reason, historical GST mismatch, linked purchase, duplicate submit.
5. Main JC desktop/mobile controls complete. Only then remove all separate Amount Editor links and old GET navigation, with redirect/rollback. Keep old controller/route while referenced.

## Next implementation gate

Run `scripts/AM120_COMPLETE_ROT_CORRECTION_READONLY.sh` on staging. It collects only route, view and controller *source excerpts* for exact field names / variable scope, without DB queries. After checking the result, implement guarded per-card P55 parity rather than merely displaying a shortcut or an unrelated Edit Labour Billing popup.