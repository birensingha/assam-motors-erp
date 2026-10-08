# Phase 4 — OSL Purchase Multi-line Entry

Goal: make OSL Purchase behave like the Parts Purchase fast-entry screen.

## Required result

- Purchase header entered once.
- Multiple OSL lines can be entered in one document.
- Job Card / Vehicle allocation is line-wise.
- OSL search works by code or description.
- Totals calculate line-wise and document-wise.
- Save runs in one DB transaction.
- Existing Parts Purchase visual language should be reused.

## Screen order

1. Vendor / Purchase header
2. Multi-line OSL grid
3. Tax / totals summary
4. Notes / attachment / save actions

See `OSL_PURCHASE_FLOW.md` and `osl_purchase_reference.html`.

## Important

The live ERP purchase PHP/Laravel source is not present in this GitHub repository.
This patch therefore provides the implementation contract and a standalone reference
form instead of pretending the staging page has already been modified.
