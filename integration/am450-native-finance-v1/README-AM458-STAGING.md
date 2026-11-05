# AM458–AM463: Native STAFF expense read API — guarded STAGING integration

**This is implemented PHP source and synthetic regression coverage, NOT a live deployment.**

Assam Motors Android Native Staff/Admin Payment screen needs one reliable
`Payment To: Staff` picker and an `Against Reference` list with each
unpaid Expense Voucher (e.g., 3942.00 outstanding, partial payment 3000.00,
942.00 still outstanding). The read side now reuses the existing audited
**AM245** Staff multi-expense payment ledger rather than recalculating or
writing a second accounting source.

## Prepared API endpoints

Inside the **EXISTING authenticated** `/api/v1/admin/mobile` route group
in Hostinger STAGING's `routes/api.php`, and **only after checking its
actual middleware**, register:

```php
Route::get('finance/v1/payees',
    [\App\Http\Controllers\Api\V1\Am461StaffFinanceReadController::class, 'payees']);
Route::get('finance/v1/open-references',
    [\App\Http\Controllers\Api\V1\Am461StaffFinanceReadController::class, 'outstanding']);
Route::get('finance/v1/master-items',
    [\App\Http\Controllers\Api\V1\Am461StaffFinanceReadController::class, 'masterItems']);
```

The actual normalized routes MUST become:

```text
GET api/v1/admin/mobile/finance/v1/payees?type=STAFF
GET api/v1/admin/mobile/finance/v1/open-references?payee_type=STAFF&payee_id=<StaffID>
GET api/v1/admin/mobile/finance/v1/master-items?type=PART&q=<search>&limit=40
```

The controller independently checks authenticated, active
`App\Models\ErpAuthUser` **Admin**. Staff/HR/customer tokens and anonymous
requests must be denied even when middleware is accidentally weaker.
Route group middleware must nonetheless be verified, not guessed.

## PHP package layout on STAGING (four newly staged files)

| GitHub integration source | Laravel destination |
|---|---|
| `FinanceReadContract.php` | `app/Services/NativeFinanceV1/FinanceReadContract.php` |
| `StaffExpenseFinanceProvider.php` | `app/Services/NativeFinanceV1/StaffExpenseFinanceProvider.php` |
| `LaravelStaffFinanceReadBridge.php` | `app/Services/NativeFinanceV1/LaravelStaffFinanceReadBridge.php` |
| `Am461StaffFinanceReadController.php` | `app/Http/Controllers/Api/V1/Am461StaffFinanceReadController.php` |

The package has a guarded source-only installer (no existing source
overwrites and no route/DB modifications):

```bash
python3 integration/am450-native-finance-v1/stage-staging.py check /path/to/staging-laravel
python3 integration/am450-native-finance-v1/stage-staging.py stage /path/to/staging-laravel
# This does NOT activate routes.
python3 integration/am450-native-finance-v1/stage-staging.py rollback /path/to/staging-laravel
```

Run it only against a **verified staging Laravel root**, never the live
production directory. A route snippet or route activation must be separately
reviewed and backed up; the script intentionally does not alter route files.
If other changes have modified any staged file, rollback refuses to delete
it. No financial migration or ledger transaction is performed.

## Guaranteed contract

`GET payees?type=STAFF` returns:
```json
{"contract":"am-finance-v1","payees":[{"id":"42","name":"Example Staff","type":"STAFF"}]}
```

`GET open-references?payee_type=STAFF&payee_id=42` returns:
```json
{"contract":"am-finance-v1","references":[
  {"id":"101","payee_id":"42","payee_type":"STAFF","reference_no":"EXP-EXAMPLE-A","outstanding_amount":"2000.00"},
  {"id":"102","payee_id":"42","payee_type":"STAFF","reference_no":"EXP-EXAMPLE-B","outstanding_amount":"1942.00"}
]}
```

All examples are synthetic. Native UI rejects any cross-payee item.
The data loader delegates to
`App\Services\StaffMultiExpensePaymentService::outstandingRows`, which
calculates unpaid balances from `workshop_expenses`, ACTIVE direct
`workshop_payment_vouchers`, and ACTIVE multi-bill
`workshop_payment_allocations`. It only queries the chosen Staff ID.
The AM246 audited Master schema mappings are reused for the new read-only
Master search route. Authorized Admin can search `PART` (`parts_master`),
`LABOUR` (`labour_master`) and `OSL` (`workshop_osl_master`) by name
or code. SQL table/column names are hardcoded whitelist values. Each
response has `contract: "am-finance-v1"`, `resource: "master-items"` and
an `items` array of `id`, `type`, `name`, `code`. Missing tables
or wrong columns fail closed. This is **master lookup only**; the
per-item transaction ledger route remains blocked pending verified
snapshot/stock versus invoice billing reconciliation.

The response is capped at 100; overflow is a **failure** rather than an
incomplete list that falsely appears paid. INR amounts are formatted
by strict integer-paise conversion, with no floating-point
rounding in the API adapter.

Unsupported types (`VENDOR`, `OSL_VENDOR`, `CUSTOMER`) and unrelated
Finance-v1 registers/masters remain **unavailable** from this adapter.
Never return fake zero outstanding or invent Staff expense references.
Actual Payment Voucher posting, allocation edits/voids and ledger
reconciliation remain in existing audited ERP workflows; these GETs
do not perform any writes.

## Acceptance gates before native production release

1. PHP syntax check all 4 package files and run
   `php integration/am450-native-finance-v1/test.php` and
   `php integration/am450-native-finance-v1/test-staff-provider.php`.
2. On STAGING, verify active AM245 service class and source schema,
   Staff status values, prior direct/multi payment allocations.
3. Verify `php artisan route:list -v --path=api/v1/admin/mobile/finance`
   shows both GET routes under existing authenticated API middleware.
4. Test anonymous 401/403, Staff/HR tokens 403, Admin token valid,
   inactive Staff, wrong Staff ID, one unpaid expense, two unpaid expenses,
   voided payment, partially paid multiple expenses, >100 references
   failure, and cross-Staff isolation.
5. Compare every returned outstanding balance to actual Staff Expense
   Register and ERP Staff Ledger. No write, no duplicate voucher.
6. Only after Admin Android authenticated GET testing and native APK
   compilation/signing/device QA should these be promoted.

**Current status:** GitHub integration branch prepared and gated;
Hostinger deployment, authentication tests and real bank/ledger
reconciliation NOT performed from this chat.
