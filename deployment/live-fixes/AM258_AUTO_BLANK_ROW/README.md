# AM258 — Continuous blank row in Estimate, Purchase and Sales

## Requirement (16 November 2026)
Keep a blank item row ready in Estimate, Purchase and Sales. Tabbing through fields continues into that row. Typing or selecting a Master item in the last row makes the next blank row appear automatically. Saving a valid document ignores any unused blank line, without failing the server validation.

## Implemented
Shared JavaScript asset loaded in ERP layout handles the Estimate form, Purchase Create/Edit form and Counter/Direct Parts Sales form. It uses the existing row builders, watches user input and Master item selection, preserves current keyboard focus, excludes Remove buttons from Tab navigation, and reuses the existing blank for + Add Line.

The script distinguishes genuinely empty rows from partially entered rows. Native required fields on a completely blank row do not block saving; blank row controls are omitted from the posted form while real entered rows continue through existing Laravel validation. Unused visual rows return if a submission is cancelled or validation blocks navigation.

Existing Enter/Tab highlighted-item selection from AM257 is unchanged. Estimate, Purchase and Counter Sale autocomplete events notify the row controller when a Master ID is selected, including an empty-search arrow-key selection.

## Safeguards
There is no new database migration, no automatic Save, no stock issue, no payment or invoice creation, and no changes to tax, rate, GST, discount or ledger code. Existing AM253/AM254 browser drafts remain enabled. Source originals are backed up for rollback. Purchase supports up to 100 actual lines plus one ready blank row.

## Verification
Automated STAGING GitHub Actions SUCCESS:
https://github.com/birensingha/assam-motors-erp/actions/runs/43828224558

Installed and verified: AM258_CONTINUOUS_BLANK_LINE_ENTRY_INSTALLED, AM258_ALWAYS_BLANK_ROW_AND_SAVE_IGNORE_EMPTY_SOURCE_VERIFIED, AM258_ERP_BLADE_COMPILE_AND_PHP_LINT_PASS, plus Node syntax checks for the actual deployed Estimate/Purchase JS and new shared module.

## Actual browser acceptance steps
- JC-20261116-00060 Estimate: select Throttle Body Cleaner via Arrow/Enter or Tab. One ready blank row should appear.
- Tab through Description, Qty, Unit, Rate, Discount and GST; cursor should continue into ready next row without clicking Add Line.
- Search in that last blank row to generate the next blank.
- Save Draft with entered lines plus an empty last row; confirm no blank-line validation error and no empty saved transaction.
- Repeat on Purchase Create/Edit and Counter/Direct Parts Sale. Existing stock validation must still reject an invalid or insufficient-stock selected Part.

Authenticated browser submission and persistence of a real invoice/purchase/estimate are not exercised by GitHub Actions, and require user-side acceptance.
