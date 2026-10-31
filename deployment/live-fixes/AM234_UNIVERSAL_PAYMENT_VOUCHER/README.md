# AM234 Universal Payment Voucher — staging implementation track

## Owner-approved design
**One existing Payment Voucher module** with six business features (not six independent voucher tables). Tally-style accounting controls + compact SAP-style input.

1. Vendor Payment — Against Reference (one or multiple purchase bills), New Reference, On Account.
2. Customer Payment / Refund — approved credit-note balance or customer advance refund with ledger audit.
3. Staff Salary Advance — employee master linked and recoverable from payroll, not an expense.
4. Staff Expense Reimbursement — expenses (Tour / Petrol / Lunch / Staff Lubricash Oil Coupon) linked to employee and optional Job Card.
5. Direct OSL Payment — Job Card + OSL service + vendor + approved cost / purchase reference.
6. OSL Vendor Payment — one or multiple linked OSL purchase payables with partial settlement.

## Current committed work (view only)
- `feature-ui.js` adds an accessible, compact six-feature selector above the existing Payment To and Reference fields.
- `apply.py check` is read-only; checks exact Laravel/Blade anchors, original form inputs and posting controllers, compiles candidate Blade and lints JavaScript.
- `apply.py apply` applies only the payment creation Blade view after writing a rollback backup; `rollback` restores it.
- Existing Vendor and OSL Vendor referenced payments retain their **existing** backend functionality. The patch does not create a new accounting route or alter amounts or allocations.
- Customer, Staff Salary Advance, Staff Expense, Direct OSL **posting remains disabled** until audited settlement services are implemented. This is intentional fail-closed behavior, not completion of those features.
- **The patch is not deployed to STAGING by opening this PR.** A separate verified deployment process is necessary.

## Backend implementation before enabling 6/6 routes

### Data boundaries
- Common voucher header: voucher number/date, payee type/id, purpose, creator, approver, status, request UUID, reversal pointer.
- Immutable settlement lines: Cash / UPI / Bank account, signed amount, clearing transaction reference; total must match allocated+unapplied.
- Allocation lines: payment voucher ID, source type, source ID, per-bill allocated amount, source payee, JC/vehicle snapshot as relevant.
- Never use free-text payee to link to supplier, staff or customer ledgers. Use real immutable IDs; support historical display name snapshots.
- Map journal debit and credit accounts to existing ERP conventions after inspecting deployed `ExpensePaymentService` and `MultiPurchasePaymentService`, not from assumptions.

### Processing
- In DB transaction, lock outstanding payables in a stable order; prevent cross-payee or overpayment.
- Compare sum of allocation lines plus explicitly unapplied amount to sum of tender lines (in minor currency units).
- Validate CASH/UPI/BANK account eligibility, mode reference, role and approval threshold.
- Idempotent request UUID; retries return same voucher, never double-post.
- Posted vouchers immutable; correction through authorized reversal with reason and audit; no hard deletes.
- Customer refunds require an approved liability/credit-note reason and proper debit/credit mapping; do not reduce tax invoice amount without the required document.
- Salary advances recorded as staff recoverable balance and payroll deduction history.
- Staff expense payouts settle approved staff payable instead of booking same cost again.
- OSL direct payment must not duplicate an already-created OSL supplier payable. Keep JC costs/expenses and supplier payable consistent.
- Lubricash: staff+JC+coupon company+coupon number must trace from expense to payment(s) and reports, see #140.

### Test matrix (all required)
- Vendor two purchase bills full + partial, mixed OSL/parts for same payee, 1 payee per voucher.
- Vendor advance and on-account later allocation.
- Customer refund with authorization and full/partial/void.
- Staff advance with payroll recovery.
- Staff expense Lubricash partial+full payment via one employee+JC source.
- Direct OSL payment linked to cost without double expense/posting.
- OSL vendor multi-bill payment and outstanding reconciliation.
- Split Cash+UPI+Bank settlement (including zero lines, bank refs and rejected totals).
- Concurrent duplicate attempts and stale outstanding balances.
- Posted voucher reversal and audit with full ledger recalculation.
- UI: searchable pop-up dropdowns, compact height, mobile usability, all six feature switching states, single voucher register and printable output.

## Deployment safety
- **Current change is a UI preview with real verified vendor paths only.** No backend code, database migration or accounting data is changed by `apply.py`.
- Run workflow `qa-am234-universal-payment-preflight.yml` before considering apply.
- Existing staging view must include AM230 + AM231 markers. Preflight aborts if expected anchors changed.
- Keep backups and review the active source services before writing the separate AM235 accounting migration.
