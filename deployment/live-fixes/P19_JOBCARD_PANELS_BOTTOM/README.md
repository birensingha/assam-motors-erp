# P19 — Job Card Admin Controls at Bottom

## Requested change

Move these two existing panels to the **very bottom** of Job Card details, below all operational sections:

1. Job Card Reversal / Cancel Centre
2. Delete Empty Job Card

They retain their original internal HTML, conditional display, POST/DELETE routes, CSRF protection, reason requirement and server-side deletion checks. No other sections have been reordered.

## Source

- Only `resources/views/job-cards/show.blade.php` is modified.
- The guarded PHP patch moves the original exact blocks from just below the Lifecycle panel to immediately before the closing `@endsection` statement.
- Prior source hash, unique panel markers, unchanged form body, compiled Blade syntax, verified backup and post-deployment rendered ordering are all checked.
- Existing job card / purchase / stock / accounting tables are unchanged.

## STAGING verification

GitHub Actions run `38527054504` passed:

- `P19_BOTH_PANELS_MOVED_BEFORE_ENDSECTION=PASS`
- `P19_COMPILED_BLADE_SYNTAX_PASS`
- `P19_REAL_ADMIN_OPEN_JOBCARD_RENDER_PASS`
- `P19_LABOUR_THEN_REVERSAL_THEN_DELETE_ORDER_PASS`
- `P19_SAME_DELETE_AND_REVERSAL_ROUTES_PASS`
- `P19_STAGING_JOBCARD_ADMIN_FOOTER_PASS`

Backup stored in staging at `storage/app/p19_jobcard_footer_backup_20261014_124458_1423590/show.blade.php`.

## Manual acceptance

Open a Job Card and hard-refresh with Ctrl+F5, scroll to bottom and verify that Reversal/Cancel is followed by Delete Empty Job Card (only visible in the allowed open state). Confirm none of the panels remain directly below Workflow & Billing.
