# Payment Voucher — /erp/payments/create HTTP 500 Fix Package

Reported issue:

`GET /erp/payments/create` → HTTP 500

The live Laravel source and server exception trace are not stored in this repository, so this package is deliberately **diagnostic-first** and avoids inventing a production schema migration.

## Goal

Make the Payment Voucher create screen fail safely and expose the actual dependency/schema error in server logs while preserving normal Admin authentication.

## Most likely failure areas to verify

1. Route points to a missing/renamed controller method.
2. Controller `create()` loads a model/table/column that does not exist on staging.
3. Controller assumes at least one Bank/Cash/Ledger/Vendor record and dereferences NULL.
4. View name/path is missing or renamed.
5. Blade references an undefined variable.
6. A relationship/model accessor throws while building dropdown options.
7. Date/account helper/service is not bound in the container.
8. Legacy/native session middleware redirects incorrectly inside the controller.
9. PHP/Laravel version mismatch around helper syntax.
10. Database permission/query error is being hidden behind generic HTTP 500.

## Safe repair strategy

1. Confirm route + controller method.
2. Wrap only optional lookup-data loading with safe defaults.
3. Keep fatal configuration/schema errors in logs; do not silently swallow them.
4. Return the native Payment Voucher form with empty optional dropdowns where safe.
5. Validate required fields on POST.
6. Save voucher header + lines in one transaction.
7. Do not calculate ledger/account balance from browser input.
8. Do not expose stack traces to the user.

See:
- `PAYMENT_CREATE_DIAGNOSTIC.md`
- `PaymentController.create.php.example`
- `payment_create.blade.php.example`
- `PAYMENT_VOUCHER_CONTRACT.md`
- `ACCEPTANCE_CHECKLIST.md`
