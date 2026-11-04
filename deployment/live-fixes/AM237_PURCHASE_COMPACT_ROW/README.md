# AM237 — Compact aligned Parts Purchase Entry row

**STAGING deployed and browser-verified on 2026-11-04.**

Source package contains guarded style-only patch; Laravel source and stock/purchase accounting logic unchanged.

## User request
- Shorten Parts Purchase line boxes.
- Line up Part Search, Qty, Rate, Disc %, GST %, Against, JC / Vehicle and Preview in one parallel row.
- Keep existing AM60 Against custom searchable control; preserve JC dropdown AM234 and Part Search.

## Implementation
- CSS height at 100% view: **30px**; 115%: **32px**; 125%: **34px**.
- Every *visible* control aligns to the same vertical center. Do not use the hidden `select.allocation.am60-native` to measure; the actual control is `input.am60-input`.
- Reduced empty line-table panel min-height from 360px to 198px.
- Existing column widths and table responsive rules preserved.
- No controller, service, database, purchase posting, GST or JavaScript modifications.

## Browser proof
[Automated GitHub Actions run #41807764095](https://github.com/birensingha/assam-motors-erp/actions/runs/41807764095):
- 100%, 115%, 125% control-height and horizontal-center measurements PASS.
- AM60 allocation control remains visible and aligned.
- JC-2026-0011 selection still submits linked Job Card ID 11.
- Add Line and original Parts Search dropdown still work.
- No real purchase saved and no financial data changed.
- Screenshot artifact includes `compact-one-line.png`, `compact-jc-selected.png`, `part-dropdown-intact.png`.

Prior failed browser checks automatically rolled back view CSS. Final workflow run succeeded without rollback.

## Delivery / protection
- Current page: https://staging.assammotors.com/erp/purchases/create
- `apply.py check` verifies existing AM234 search and target Blade markup.
- `apply.py apply` modifies only `resources/views/purchases/create.blade.php` after backup.
- `apply.py rollback` restores exact backup on failure.
- The previous current staging backup is stored under `storage/app/am-backups/AM237_PURCHASE_ROW_...`.

## Separate scope
- Mandatory purchase-to-JC issue/fitting/item billing close guards remain unimplemented in AM237.
- The AM235 read-only reconciliation register is not an automatic transaction blocker.
- Multi-JC duplicate-part allowance in the same supplier bill requires a separate audited backend change.
