# AM122 — Compact Void ROT next to Edit ROT Start / End / Time

## What changes
AM122 is **Staging-only, view-only**. In each Main Job Card ROT card with a completed, eligible session, an existing `details.am121-void-panel` is moved next to `details.am-v647-time-editor` in a compact horizontal toolbar after the page loads. The button is shortened to **Void ROT** visually (underlying AM121 typed confirmation and Admin reason remain unchanged). When the editor is open, it expands full width; small screens wrap naturally.

The toolbar also exposes existing **Edit Billing** (opens FIX90/AM118 Labour Billing editor) and **History** (opens the existing time-editor audit history). These buttons are added only when their genuine existing controls exist in the Job Card. **No new financial or authorization endpoints are created.**

## What does not change
- AM121 completed ROT Void controller, invoice/purchase/payment blocking, audit history, and double confirmation.
- V647 time correction and its original time data; no modification to started/paused/completed histories.
- Staff/Mechanic assignment, rate, discount, GST and other backend validation.
- Original approved estimates, current invoices, credit notes, ledgers and all database records.
- Does not retire the standalone Amount Editor yet. Full ROT alteration and QTY editing parity remain **AM120 pending**, not falsely advertised as complete.

## Safe deploy
Run `scripts/AM122_APPLY_STAGING.sh` in Hostinger SSH after AM121 has already passed. It downloads the Blade fragment + guarded Python installer, does a read-only preflight, backs up the current `completed-rot-void.blade.php`, appends UI only, compiles Blade, and rolls back that partial on failure.

## Browser acceptance
1. Admin opens a WIP / invoice-free JC with a COMPLETED ROT.
2. **Void ROT** sits immediately to the right of **Edit ROT Start / End / Time** on the same card. Clicking Void shows all unchanged mandatory fields.
3. **Edit Billing** opens the existing billing correction popup for that same labour row; **History** opens original audit page when available.
4. Mobile view wraps without overlap. Clicking the V647 time editor still shows Start, End, Standard Hours, Productive Time.
5. Refresh and check completed and planned ROTs: the view should not duplicate action panels or delete anything.
6. Any actual Void data operation should be tested ONLY on a disposable JC after verifying AM121's backend and downstream edit/reopen guards.

**If an old deployed view has unexpected markup, the installer stops without writing.** No effect is claimed until the user has run it and verified in browser.
