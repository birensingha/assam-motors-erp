# P25 — Open Estimate Server Error 500 (STAGING)

## Incident

After completing Vehicle Inspection and opening **Open Estimate**, STAGING ERP returned HTTP 500. Laravel's timestamped error log repeatedly showed `Undefined variable $initial` in `resources/views/estimates/create.blade.php`.

## Confirmed root cause

The WIP flag was declared by a malformed inline Blade directive `@php($amEstimateWip=...)`. Blade compiled this to `<?php(` and left following directives and the assignment to `$initial` as inert page source. Later PHP attempted to use the undefined variable in `old('items', $initial)`.

The fix replaces only that line with a correctly compiled multiline PHP block while preserving the initial/revised/supplementary Estimate form, search items, taxes, discounts and all server-side accounting.

## Safety

- A hash guard requires the exact audited original Estimate form.
- A private backup is copied and hash-verified before source replace.
- No migrations, financial rows, stock, Job Cards, inspection or Estimate data are changed.
- PHP token parser and compiled Blade checks run before writes.
- Deployment rolls back the view to its original backup if post-install QA fails.
- Only `resources/views/estimates/create.blade.php` was modified.

## Verified

Read-only audits: Actions runs #38538507306, #38538589753, #38538677138.

Preflight corrected and passed: #38539121303.

Guarded staging installation and real render Actions run **#38539208559** PASS:
- `P25_COMPILED_PHP_TOKEN_PARSE_PASS`
- `P25_INITIAL_DECLARED_BEFORE_USE_PASS`
- `P25_DIRECTIVE_COMPILATION_PASS`
- `P25_INITIAL_AND_WIP_ESTIMATE_ACTUAL_BLADE_RENDER_PASS`
- `P25_REAL_CONTROLLER_OPEN_ESTIMATE_RENDER_PASS`
- `P25_NO_INSPECTION_ESTIMATE_STOCK_FINANCIAL_RECORDS_MUTATED`
- `P25_STAGING_ESTIMATE_500_DEPLOY_PASS`

Staging private backup: `storage/app/p25_estimate_500_backup_20261014_143105_3632934/create.blade.php`.

## User acceptance

Open the original Job Card, Ctrl+F5, then **Open Estimate**. Confirm parts/labour selectors work. The user should not need to re-complete inspection or diagnosis.

Note: This fix does not contain the separately requested Android Admin/Manager Check-In/Check-Out and Inspection UI; that work remains pending.
