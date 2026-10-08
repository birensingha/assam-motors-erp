# /erp/payments/create — Diagnostic Procedure

## 1. Confirm route

On staging deployment:

```bash
php artisan route:list --path=payments
```

Expected create route shape:

```
GET|HEAD  erp/payments/create  -> PaymentController@create
POST      erp/payments         -> PaymentController@store
```

Use the project's actual namespace/controller name.

## 2. Reproduce only in staging

Tail the Laravel log while opening the page:

```bash
tail -f storage/logs/laravel.log
```

Do **not** enable `APP_DEBUG=true` on a public environment.

Capture:
- exception class
- message
- file
- line
- first relevant application stack frame

## 3. Common signatures

### Undefined variable in Blade

Examples:
- `Undefined variable $accounts`
- `Undefined variable $vendors`
- `Undefined variable $paymentModes`

Fix: controller always passes all view variables, even when empty.

### Missing table / column

Examples:
- `Base table or view not found`
- `Unknown column ...`

Fix: map the create lookup query to the real staging schema. Do not add a guessed migration until schema is verified.

### Null property access

Examples:
- `Attempt to read property ... on null`

Fix: optional selected/default account must use null-safe handling. The create page should not assume a default Cash/Bank ledger exists.

### View not found

Example:
- `View [erp.payments.create] not found`

Fix route/controller to the actual Blade path, or restore the missing view.

### Class / method not found

Fix autoload/controller import; then run:

```bash
composer dump-autoload
php artisan optimize:clear
```

## 4. Clear stale Laravel caches after source fix

```bash
php artisan optimize:clear
```

If deployment uses compiled views/config, rebuild only according to the existing deployment process.

## 5. Verify GET before POST

The create page must load successfully even when:
- no vendor is selected;
- no default bank/cash account is configured;
- optional reference lists are empty.

A missing *required system configuration* should display a clear Admin-facing message rather than an unhandled 500.
