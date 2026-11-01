# AM Payment Voucher — Read-only STAGING audit plan (2026-11-01)

Status: audit only; no deployment and no financial writes authorized.

## Original source verification (server only)
- Confirm current git/worktree version and checksum of:
  - app/Http/Controllers/Web/AdminExpensePaymentPageController.php
  - resources/views/payments/create.blade.php
  - services containing MultiPurchasePaymentService
- Confirm whether AM230/AM231 patches are applied. Back up original files before future edits.
- Inspect exact schema/relations for staff, customer_master, vendors, OSL vendors, payment vouchers, references, outstanding and allocation/ledger tables. No row dumps or personal details.
- Verify role authorization, request idempotency key, transaction boundaries and rollback logic.
- Check existing Expense -> Pay deep links and the vendor multi-bill settlement path.
- Verify staff/customer POST is guarded until their audited ledger posting exists (AM231 specification).

## Acceptance criteria before enabling finance posting
1. Select Staff / Customer / Vendor / OSL Vendor from distinct real master data, searchable.
2. Separate Against Reference, On Account and Advance semantics; prevent cross-party credit use.
3. Vendor/OSL bill list restricted to selected recipient and outstanding status.
4. Permit one payment across three bills; applied allocations sum <= available payment plus eligible prepaid credit.
5. Example: bills total INR 3942; available previously-posted debit INR 942; new cash INR 3000; remaining INR 0; debit consumed exactly once.
6. Partial payment and overpayment produce auditable unapplied credit, not silent write-off.
7. Concurrent submit / retries cannot create duplicate voucher or double-consume credit.
8. Payment reversal creates audit history and restores bill/credit balances; never mutate historical posted rows destructively.
9. Round-off, GST, debit/credit direction and vendor/staff/customer ledgers reconcile after posting.
10. Confirm no regression in expense payout or existing purchase invoice allocation.

## Safety
No migrations, POST requests, payments, voucher edits or production changes during audit.
Block deployment unless actual STAGING files, schema, backup, role mapping and transaction logic have been checked.
