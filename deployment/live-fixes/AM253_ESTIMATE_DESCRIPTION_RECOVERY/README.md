# AM253 — Long Estimate descriptions, failed-validation row recovery, tab autosave

**Reported:** 2026-11-13 JC-20261113-00054, seventh submitted array item index `items.6.description` longer than 255 characters. A failed Save Draft sent user back to a visually empty Estimate Lines table, risking retyping the entire estimate.

## Root causes grounded in live STAGING
- `AdminEstimatePageController@store` used `items.*.description max:255`.
- `job_estimate_items.description` was VARCHAR(255), with downstream `job_card_labour.description` VARCHAR(255.
- `EstimateService::calculate()` silently used `mb_substr(...,0,255)`.
- Estimate Blade `old('items', $initial)` emitted sparse numeric old form-row keys to JSON. JavaScript `Array.isArray(data.initial)` rejects JSON objects (e.g. rows 0,1,2,3,5,6 after deletion), so it renders only a blank default row. Laravel validation errors were displayed, but original line data not visible.

## Corrective changes
1. Preserve and validate descriptions up to 2,000 characters. Fail on longer values instead of silently cutting them.
2. Add additive, data-preserving migration widening Estimate descriptions and downstream JC/ROT descriptions to TEXT, after **a fresh full STAGING MariaDB backup**.
3. Explicitly `withInput($request->except('_token'))` on validation failure so all line fields return to the Estimate form.
4. Normalize `old('items')` using `array_values()` before encoding into JavaScript so rows with sparse keys are restored.
5. Add 8-hour tab-only sessionStorage autosave after any field edit, including all rows (Type, reference, description, qty, unit, rate, discount, GST) and Estimate header/notes. A visible **Restore Previous Entered Lines** button appears when a tab backup exists; user approves overwriting displayed rows. No network request, no new server DB draft, no LocalStorage permanent financial data.
6. Keep existing GST math, estimate lifecycle, approval and invoice unchanged.

## Deployment
`install.py check|prepare|apply|verify|rollback STAGING_ROOT`, with migration separately following complete private SQL backup. Source backup and file rollback; migration down intentionally no-op to avoid destroying long descriptions. No customer Estimate, invoice, JC or staff record edited during deploy.

## Acceptance
- Enter description from complaint, length greater than 255 and below 2,000; Save Draft succeeds and print retains full text.
- Delete an intermediate row, enter 6+ rows, force a validation error elsewhere, confirm all original rows and numbers restored.
- On same browser tab, type an unsaved row and refresh; use Restore button to recover.
- Rates, GST, round-off and JC transfer totals unchanged.
- Browser **Back** after already failed pre-patch submission may restore data via the browser; old lost data cannot be reconstructed from new code alone. Do not promise recovery of prior unsaved lines not available in browser/session.

## Current acceptance state
Code-only smoke checks on Laravel + PHP syntax and long-description calculation; authenticated real-browser acceptance remains a user-side check.
