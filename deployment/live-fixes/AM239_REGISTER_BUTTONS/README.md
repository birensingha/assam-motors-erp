# AM239 — Purchase Register Three Buttons in One Row

**Applied to Assam Motors STAGING, 4 Nov 2026.**
Page: https://staging.assammotors.com/erp/purchases

## User request
Replace the long white technical link
`AM235_PURCHASE_RECONCILIATION · Parts Purchase Reconciliation`
with a normal coloured button, beside:
- New OSL Purchase (green)
- New Parts Purchase (blue)

## Delivered
- Same-sized red gradient `Parts Reconciliation` button in the same horizontal three-column action grid on desktop.
- Existing green / blue actions unchanged in function.
- Removed implementation code name from the visible text.
- Compact, responsive layout, including the current admin UI at 1440, 1180 and 760 px.
- Existing report route `erp.purchases.reconciliation` retained.

## Safety
Only `resources/views/purchases/index.blade.php` is modified.
`apply.py check` validates exact current source, `apply.py apply` creates backup,
and `apply.py rollback` restores the original view.
No purchase, stock, vendor, GST, invoice, JC or business data changed.

## Verified
[AM239 automated browser QA PASS](https://github.com/birensingha/assam-motors-erp/actions/runs/41821942910)

- Three coloured buttons in one horizontal row at 1440, 1180 and 760 px.
- Equal heights, correct order, correct routes.
- Red action links successfully to the Reconciliation Register.
- Screenshot artifact includes desktop/tablet Purchase Register views.
- No purchase posted.
