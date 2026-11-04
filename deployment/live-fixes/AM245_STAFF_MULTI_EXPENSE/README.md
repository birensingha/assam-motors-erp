# AM245 — Pay Multiple Staff Expenses in ONE Payment Voucher

User request: **Staff ko multiple unpaid Expense Vouchers ka payment karna ho to Vendor multiple-bill jaise ek Payment Voucher mein selection, partial allocation aur balance tracking hona chahiye.**

## Included feature
- Link within **Universal Payment Voucher → Staff** to **Pay Multiple Staff Expense Vouchers — One Payment**.
- Dedicated staff multi-expense screen, using the existing Payment Voucher financial register and layout.
- Staff selection from Staff Master; only active outstanding expense references from the same Staff appear.
- Amount Paid first; select one/multiple expense references; auto-allocate FIFO up to outstanding or manually adjust line-wise amounts.
- Strict amount=sum(allocations) and individual allocation <= outstanding.
- One `workshop_payment_vouchers` row (`payee_type=STAFF`, `source_type=EXPENSE_VOUCHER`, `source_reference=STAFF_EXPENSE_MULTI`).
- Existing `workshop_payment_allocations` stores immutable per-reference shares `EXPENSE_VOUCHER::<id>`.
- Original Expense Voucher is not recreated; Expense paid/partial/unpaid state is synced from active single+multi payments.
- Single-expense payment and Expense Void now include multi-allocation balance checks, preventing overpayment and orphan reversals.
- Existing generic Payment edit and void are blocked for multi allocations. Dedicated audited multi-Void restores all affected outstanding balances atomically.
- Expense page Payment History shows the individual expense share, not the full multi-voucher amount. Payment Voucher page resolves expense numbers for readability.
- All posting runs within a DB transaction under locked Staff and Expense rows, with request-key idempotency.

## Audit and safety gates
`apply.py check` validates anchored source before changing anything; `apply.py apply` saves source backups, patches PHP views/controllers/services, registers routes, and adds service/controller/view; `apply.py rollback` restores all 5 original files and removes new files.

**Important:** Creating this code package does not post any staff payment. Browser QA is deliberately non-posting. Real financial posting/void integration QA must be separately verified before calling the financial write path fully proven; code install should not be equated with end-to-end accounting correctness.

## Validation
- Static: Python installer syntax, PHP services/controllers syntax, Laravel view:cache, named route registrations.
- Browser: new Payment Voucher Staff link, staff filter, outstanding EXP-20261104-000016 for Firajul, amount-first partial allocation and over-allocation prevention.
- No actual payment created or voided during the UI test.

[Staging QA workflow](https://github.com/birensingha/assam-motors-erp/actions/workflows/am245-staff-multi-staging.yml)

## Not included
- Salary advances/provision still use separate Staff Financial Ledger rules.
- Customer payouts and vendor payments remain on their own audited paths.
- Payment Voucher direct editing of a posted multi-share allocation is intentionally blocked; Void and re-post with reason is required.

## Staging verification completed — 4 November 2026

- [Guarded staging deployment + Chrome browser verification PASS](https://github.com/birensingha/assam-motors-erp/actions/runs/41868528406). Source backup created, PHP lint, Laravel routes and Blade compilation passed. Browser verified actual staff selector and real unpaid state; the selected staff had no currently outstanding references during the test. A **browser-only three-reference fixture** verified ₹420 allocated across three expenses, ₹300 partial allocation, and rejection of over-allocation. No payment was posted by Chrome.
- [InnoDB rollback-only accounting integration PASS](https://github.com/birensingha/assam-motors-erp/actions/runs/41869262532). The actual posting service generated ONE Payment Voucher with TWO linked expenses, validated ₹100 PAID + ₹150 PARTIAL against a ₹250 allocation, then audited Void restored both UNPAID amounts. **All synthetic business rows were rolled back** by an outer transaction; no real staff payment or expense was committed.

Caveat: These are staging integration checks, not evidence that every possible historic ledger edge case or browser workflow has been tested. In particular, a zero-outstanding Staff will correctly show an empty list.
