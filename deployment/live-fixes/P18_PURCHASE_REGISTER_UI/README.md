# P18 — Purchase Register Android-inspired UI

Staging-only, frontend-only visual change (14 October 2026).

## User-visible changes

- New OSL Purchase (green) and New Parts Purchase (blue) in one shared horizontal action row.
- Android-inspired Purchase Register header with rounded cards, softer background, accessible focus and touch-sized controls.
- More readable Purchase search field, type filter, Search and Reset.
- Purchase history table retains original rows, amounts, status badges, View/Edit routes; responsive horizontal table scroll on small screens.
- OSL/Parts buttons remain side-by-side using a two-column grid on narrow mobile layouts.

## Preserved behavior

The installer edits only `resources/views/purchases/index.blade.php`. The following are untouched:
- Purchase CRUD logic, search query and filters
- Purchase data, stock movements, OSL/Parts allocations
- GST/amount calculations, payments, vendor/customer ledger
- Existing routes: `erp.purchases.osl.create`, `erp.purchases.create`, `erp.purchases.index`, `erp.purchases.show`, `erp.purchases.edit`.

## Verification

GitHub Actions staging deployment run `38522748874`:
- `P18_COMPILED_BLADE_SYNTAX_PASS`
- `P18_REAL_ADMIN_PAGE_RENDER_PASS`
- `P18_BOTH_CREATE_BUTTONS_SAME_ROW_PASS`
- `P18_RESPONSIVE_STYLE_MARKERS_PASS`
- `P18_STAGING_PURCHASE_REGISTER_UI_PASS`

Guarded original source backup at `storage/app/p18_purchase_register_backup_20261014_120213_3235084/index.blade.php`. Deployment aborts on unexpected anchors or concurrent source modifications. Rollback runs on QA failure.

Staging page: `https://staging.assammotors.com/erp/purchases`

**Pending:** end-user visual confirmation of desktop/mobile appearance. No screenshot-driven color calibration was performed after deployment.
