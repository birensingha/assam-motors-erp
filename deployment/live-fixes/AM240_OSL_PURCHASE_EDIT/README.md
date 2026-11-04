# AM240 — OSL Purchase Edit action in Purchase Register

**STAGING deployed and browser-verified: 4 November 2026.**

## Request
Show an **Edit** action for OSL Purchase rows in the main Purchase Register, exactly like the existing Parts Purchase **Edit** action.

## Verified code path
- Purchase Register view: `resources/views/purchases/index.blade.php`.
- Existing OSL editor route: `erp.fix48.osl-edit`, GET `/erp/workshop-tools/osl/{purchase}/edit`.
- Existing edit implementation: `AdminWorkshopControlPageController::editOsl` and `updateOsl`.
- Existing Purchase View already links to the same OSL Edit route.
- The edit form uses an audit reason and supports supplier cost/quantity/discount/GST corrections where permitted.
- Existing protections block changes to closed/invoiced JCs and some posted/paid source references. Multi-line supplier invoice reference edits require full-batch revision.

## Delivered
- Every active OSL row in the main Purchase Register now shows `✏ Edit` beside `👁 View`.
- Existing Parts Purchase `✏ Edit` button remains unchanged.
- The OSL button routes to the existing audited OSL editor.
- No new financial posting code or schema changes.
- Change is limited to the Purchase Register Blade view and is reversible using `apply.py rollback`.

## QA
[AM240 successful staging browser test](https://github.com/birensingha/assam-motors-erp/actions/runs/41824458006)

Authenticated Chrome:
- Opened the Purchase Register successfully.
- Verified a real active OSL purchase row has an Edit action.
- Verified the OSL Edit link points to the expected route.
- Clicked it and opened the OSL edit form (audit reason + vendor rate fields).
- Existing Part Purchase Edit still present.
- No Save button was pressed, and no business records were changed.

Notes: This is access to the **existing single OSL purchase line editor**, not a new full multi-line batch revision engine. Changing vendor/payments or paid/closed transactions may require separate audited financial correction.
