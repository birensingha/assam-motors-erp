# Assam Motors — Live Staging Source Onboarding Handoff

Purpose: collect enough **read-only, sanitized** information from the actual staging Laravel/PHP application so the prepared ERP patches can be mapped to real routes, controllers, views, APIs and database tables.

This handoff must **not** collect or upload:
- `.env`
- DB passwords
- APP_KEY
- API tokens
- Firebase service-account JSON
- private keys / keystores
- customer/job/payment row data

## 1. Find the real staging application root

On the hosting shell, locate Laravel `artisan` files:

```bash
find ~/domains -maxdepth 7 -type f -name artisan -print 2>/dev/null
```

The correct `APP_ROOT` is the directory containing the staging application's:
- `artisan`
- `app/`
- `routes/`
- `resources/views/`
- `composer.json`

Do not guess from the domain name alone.

## 2. Run repository preflight against the real app

From a checkout of `birensingha/assam-motors-erp` on current `main`:

```bash
APP_ROOT=/path/to/real/staging/app bash deployment/preflight.sh
```

This is read-only.

## 3. Create the sanitized handoff bundle

Without DB schema inspection:

```bash
bash deployment/collect-live-staging.sh /path/to/real/staging/app
```

Recommended, with **schema metadata only**:

```bash
RUN_DB_SCHEMA=1 bash deployment/collect-live-staging.sh /path/to/real/staging/app
```

The script prints the exact archive path, normally:

```text
/tmp/assam-motors-live-handoff-YYYYMMDD_HHMMSS.tar.gz
```

## 4. What the archive contains

- Laravel/framework/PHP version information
- current Git branch/commit if staging is a Git checkout
- `php artisan route:list`
- targeted route subsets
- source **file inventory**
- matched **file paths only** for Workshop/Admin/Staff keywords
- Composer direct package list
- `composer.json` and `package.json` when present
- storage/cache permission summary
- optional database **table/column/index metadata only**

It does **not** copy application source files.

## 5. Optional DB schema probe

When `RUN_DB_SCHEMA=1`, the collector boots the staging Laravel app and uses its existing DB configuration internally.

The probe does not print credentials and does not read business rows.

It only queries `information_schema` for tables matching concepts such as:

- customer
- vendor
- part/item
- labour/labor
- vehicle
- payment/voucher
- job/card
- invoice/estimate
- purchase
- ROT
- reminder/alert
- staff/attendance
- booking/application
- user/product

Output:
- table name
- engine
- column name/type/nullability/key/default presence
- index name/columns/uniqueness

## 6. What to send back

Send/upload **only the generated `.tar.gz` archive**.

Do not send:
- `.env`
- full DB dump
- SQL data export
- service-account JSON
- signing key/JKS/keystore
- passwords copied from hosting panel

Once the archive is available, the Integration Map can be filled with the exact live:
- route
- controller/action
- Blade/view
- API file
- table/column
- dependency
- deployment order

## 7. If the collector reports a secret warning

Do not upload the archive.

Open the collector output directory printed by the script, inspect the named file, remove/redact the sensitive content, then re-run the collector.

## 8. This is discovery, not deployment

Running this handoff does **not**:
- run migrations
- clear caches
- edit source
- restart PHP
- change permissions
- write DB rows
- deploy Android
- publish APK/update metadata

After source mapping is complete, staging deployment remains a separate controlled step using `deployment/STAGING_RUNBOOK.md`.
