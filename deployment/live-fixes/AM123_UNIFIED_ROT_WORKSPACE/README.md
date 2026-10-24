# AM123 — Unified Mechanic & ROT Work Controls on Main Job Card

**Status:** STAGING deployment package; not yet installed or browser-tested. Original Prototype V3 was a local demo and is NOT an ERP implementation.

## Stage 1 — Real audited controls moved to Main JC
- Mount the existing P55 `rot-alteration.blade.php` Admin form using Main Job Card labour lines (`$am83RotLabour`). The AM116 mapping UI uses the *existing* `erp.job-cards.labour.alter` POST route, correction reason, Admin Note, price/timing locks, history and tax checks. This avoids creating an unaudited replacement controller.
- Add **Admin Full ROT Correction** on matching Main JC ROT cards. It opens exactly the matching P55 form. The standalone Amount Editor remains a fallback until browser checks prove parity.
- Expose the *same existing* `am82-finance-form` from each ROT card in a compact inline Billing section (Rate, Discount, GST, server-calculated totals). The existing FIX90 Edit button can still move that form to its dialog; no cloned form, no new billing endpoint.
- Preserve existing ROT Assign/Change Mechanic, Start/Pause/Resume/Complete, V647 Edit Start/End/Time and Standard Hours, AM117 Delete, AM121 Completed ROT Void, and audit History.
- Installer optionally installs AM122 compact Void-button toolbar if absent. Any source-anchor/preflight mismatch STOPS without changing Main JC, and failed Blade compilation rolls back Main JC source.

## **Critical remaining prototype-to-ERP gaps**
- **Qty remains NOT implemented.** Staging `job_card_labour` columns from the user's October 24 AM121 audit include `standard_hours`, `labour_rate`, `discount_percent`, `discount_amount` and `gst_percent` but **no separate quantity**. Never fake Qty by changing Std Hours.
- Direct editable Discount Amount ₹ requires server-side support/validation. Existing billing form presently supports Discount %; the prototype's linked %/₹ input behavior remains pending unless confirmed in staging.
- P55 form mapping and P55 saves from Main JC must be tested in browser, including **Completed** ROT (Admin unlock), correct line id, role restrictions, historical GST mismatch and approved Estimate preservation.
- After AM121 Void, the additional Reopen/Alteration/Time endpoints still need server-level Void guards before production use; hiding controls is insufficient.
- Separate Amount Editor MUST NOT be removed before all controls are real and tested.

## Required acceptance sequence
1. Install Staging with `scripts/AM123_APPLY_STAGING.sh`; verify source+Blade pass; **no database records change** at install.
2. Admin opens WIP Job Card with two distinct ROTs. Each card should have **Admin Full ROT Correction**, inline Billing, Time Edit, Mechanic, Void/Delete (eligible statuses only), History.
3. Ensure the correction form **for selected ROT** is opened. Authorised test updates of Std Hours and Rate/Discount on a disposable Job Card should persist after refresh with proper audit. Verify actual calculation in `JobCardTaxTotals`, not just visual preview.
4. Test manager/staff permission denial; invoiced/closed JC financial blocks; GST mismatch; duplicate click.
5. If any P55 mapping fails, original Amount Editor remains available and no fake correction save should appear.
6. Final prototype parity will be complete only after separate Qty field and full audited calculation, Discount Amount ₹ validation, and completed Void/reopen cross-service guards are implemented and verified.

**Do not claim the V3 prototype is fully implemented merely because the AM123 source patch compiles.**