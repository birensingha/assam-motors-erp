# P27 — Estimate Lines Height Matches Purchase

## Change
STAGING Estimate Creation screen now matches the Purchase Entry **360px minimum line-item area height**.

- Added scoped CSS to the Estimate Lines scroll/table area only.
- The box starts at 360px minimum height and grows naturally for multiple Parts/Labour lines (no vertical max-height cap).
- Existing horizontal table scroll is retained on narrow screens; inputs remain usable.
- All estimate line identifiers, JS handlers, item selection, form actions, GST, discount, totals and backend calculations are unchanged.
- Previous P25 undefined-$initial view error fix is preserved.

## Installation and QA
Source: `resources/views/estimates/create.blade.php` only.

Preflight GitHub Actions **38548307532** — PASS.

Guarded staging deployment **38548380960** — PASS:
- `P27_BLADE_COMPILE_SYNTAX_PASS`
- `P27_ESTIMATE_LINES_MIN_HEIGHT_360PX_PASS`
- `P27_PURCHASE_MATCHING_360PX_MINHEIGHT_PASS`
- `P27_INITIAL_AND_WIP_ESTIMATE_ACTUAL_BLADE_RENDER_PASS`
- `P27_REAL_CONTROLLER_OPEN_ESTIMATE_RENDER_PASS`
- `P27_NO_INSPECTION_ESTIMATE_STOCK_FINANCIAL_RECORDS_MUTATED`

Original view backup at `storage/app/p27_estimate_lines_backup_20261014_154636_3762508/create.blade.php`.

End-user browser visual acceptance remains pending. Open a Job Card > Open Estimate, Ctrl+F5, inspect the larger Estimate Lines box.
