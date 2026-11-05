# AM488 — STAGING Native Finance GET route deployment checklist

**Status:** implementation package and synthetic regression tests are green.
**NOT DEPLOYED on Hostinger.** This checklist is intentionally safe-by-default;
all commands below belong on an independently confirmed STAGING Laravel root,
not in the production ERP, and require an operator review of the route group.

## Supported read-only endpoints

- `GET /api/v1/admin/mobile/finance/v1/payees?type=STAFF`
- `GET /api/v1/admin/mobile/finance/v1/open-references?payee_type=STAFF&payee_id=...`
- `GET /api/v1/admin/mobile/finance/v1/master-items?type=PART|LABOUR|OSL&q=...&limit=40`

Other payee types, Master Ledger transactions, financial registers, and ALL
financial POST/PUT/PATCH/DELETE requests remain unsupported/fail-closed.

## Before using the staging scripts

Verify all of the following on the remote environment, without printing
secrets: the actual STAGING document root, `routes/api.php`,
`app/Models/ErpAuthUser.php` authorization rules, the existing authenticated
v1 middleware group, any Laravel API prefix applied by `bootstrap/app.php`,
`app/Services/StaffMultiExpensePaymentService.php` and actual Staff/Expense
ledger table+column names. The scripts refuse an absent Laravel root and require
`.env` to identify `APP_ENV` as a non-production environment and `APP_URL`
as HTTPS `staging.assammotors.com`. **Do not modify a production .env to
pass this gate.**

The source package in this GitHub PR is under
`integration/am450-native-finance-v1/`. It is not automatically deployed by
opening/merging a PR. Copy the reviewed integration package into a separate,
operator-controlled location outside the Laravel public document root.

## Source-only STAGING placement (no enabled routes)

From the package directory on STAGING:

```bash
python3 stage-staging.py check "$STAGING_LARAVEL_ROOT"
python3 stage-staging.py stage "$STAGING_LARAVEL_ROOT"
```

This stages five PHP files **without overwriting existing files** and writes an
SHA-256 manifest. It does not edit `routes/api.php`, enable routes, change a
record, pay a vendor or create an invoice. Stop on any `AM463_BLOCKED`.

## Explicit route-group review — essential

Inspect the real `routes/api.php` and `bootstrap/app.php` in the STAGING
Laravel installation. Find the existing authenticated API v1 route group that
already serves protected mobile ERP JSON routes. Its middleware must identify
the correct `ErpAuthUser` principal; do not guess a new guard.

**Only after verification**, put this standalone marker **inside that group's
closure** in `routes/api.php`:

```php
// AM483_AUTHENTICATED_V1_INSERTION_REVIEWED
```

The activator does not guess route nesting. The marker is an explicit human
assertion that the existing route's authentication and prefix were examined.
The controller performs a second server-side `active && isAdmin()` check.

Then inspect and optionally activate:

```bash
python3 activate-finance-routes.py check "$STAGING_LARAVEL_ROOT"
# Record AM485_REVIEWED_ROUTE_SHA256 from the check output.
python3 activate-finance-routes.py activate "$STAGING_LARAVEL_ROOT" \
  --expected-sha256 "$AM485_REVIEWED_ROUTE_SHA256" --reviewed
```

The activator uses byte-verified atomic replacement of **only**
`routes/api.php`, keeps a backup under `storage/app/am-backups/`, and records
the original/active checksums. It refuses concurrent changes or a second
activation. A manually changed route after activation blocks automatic rollback
rather than erasing the new work. Note: it does NOT touch database tables,
vendors, expenses, jobs, payment vouchers, invoices, or Android production.

## Real HTTP acceptance (still REQUIRED)

On STAGING only, check `php artisan route:list --path=admin/mobile/finance/v1 -v`
and verify:
- exactly three GET routes, no POST;
- actual `api/v1` prefix (not accidentally `api/api/v1` or missing v1);
- existing authenticated middleware, plus controller's active-Admin check;
- anonymous GET denied (401/403), genuine non-admin Staff bearer denied;
- approved Admin bearer returns `am-finance-v1`, 100 or fewer Staff payees;
- PART/LABOUR/OSL query responses match verified Master schema;
- selected Staff unpaid Expense references match the original Staff ledger
  including active direct and multi-bill allocations; no cross-Staff data;
- unsupported VENDOR, CUSTOMER and OSL_VENDOR response remains blocked;
- no error logs indicating missing models/tables, 500 or unexpected redirects.

After route caching is reviewed by the STAGING administrator, use the
project's normal STAGING route-cache invalidation procedure if required.
Do not clear production caches.

A protected GET-only live smoke checker is supplied:

```bash
# Use secure environment injection; never put bearer tokens in shell history.
python3 verify-staging-api.py --staff-id "$VERIFIED_STAFF_DB_ID"
```

It requires `AM_STAGING_ADMIN_TOKEN`; optional
`AM_STAGING_STAFF_TOKEN`. Don't store/export real tokens into GitHub,
logs or unencrypted notes. It never prints Staff names or financial balances.

**A green synthetic CI result is not live STAGING acceptance.** Do not mark
these APIs deployed or ship Android finance features until checks pass.

## Rollback on STAGING

If the new routes fail, run from the same package directory:

```bash
python3 activate-finance-routes.py rollback "$STAGING_LARAVEL_ROOT"
```

It restores the exact original route bytes **only if** the current route
still matches the activation manifest, and leaves the backup for audit.
If another administrator has modified routes, it refuses automatic rollback.
After that, `stage-staging.py rollback` removes only unchanged staged files.

## Build and device gate

Separately, Android PR #58 still needs a real `:app:testDebugUnitTest
:app:assembleDebug` build, installable original-release-key signed QA APK,
native Admin/HR/Staff login and Finance GET smoke tests on a fresh test phone.
Do not install a debug-key APK over production signed 6.3.20 and do not
declare a successful native release from syntax/static checks.
