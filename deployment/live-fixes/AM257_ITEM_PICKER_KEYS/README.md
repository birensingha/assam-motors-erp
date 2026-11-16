# AM257 — Tally-style searchable Part/ROT/OSL selection (Enter + Tab)

User screenshot from JC-20261116-00060 Estimate: search "throt", highlight "Throttle Body Cleaner", press Enter; previously Estimate committed the option AND created/focused next line. This is incorrect.

## Behavior
- Type any Part, Labour/ROT or OSL item text; Arrow Up/Down changes highlighted suggestion.
- **Enter** selects exact highlighted Master ID, fills rate, HSN, GST/description etc., closes dropdown and **does not create another row**. Focus stays in the picker; press Tab to advance.
- **Tab** when the suggestions are showing selects highlighted item and continues to **the next input of that same row**. On Purchase Create/Edit where the search result is still loading, Tab waits for results, then selects and focuses Qty.
- Tab when a selected Master is already in the field only navigates; never reselects an unrelated first suggestion. Shift+Tab preserves normal reverse navigation. Escape closes suggestions.
- Existing final-row completion or explicit Add Line actions still work.
- Existing price, GST, discounts, stock quantity checks, voucher save/approval/payment and records remain untouched.

## Coverage
- `public/js/estimate-entry.js`: JC Estimate Part/ROT/Labour/OSL reference.
- `public/js/purchase-entry.js`: new Part purchase item input.
- `public/js/purchase-edit.js`: edited Purchase Part input.
- `resources/views/partials/am-global-selector.blade.php`: enhanced native Master select used by Counter Sales and other forms.

## Rollout
Guarded installer: `check|apply|verify|rollback STAGING_ROOT` source-anchor verification, JS syntax when Node is available, Blade compiled PHP lint, per-file backup and restore on unexpected error. GitHub Actions runs these via Hostinger STAGING SSH. No database/migration edits; no business POST is made.

## Browser acceptance
On Estimate JC-60, type `throt`, ArrowDown to **Throttle Body Cleaner**, Enter: selected item stays on current third row. Tab: Description focuses, no new row. Repeat with Tab directly from highlighted choice.
On Purchase Create/Edit, enter part search, highlight second item, Enter then Tab to Qty; repeat with Tab-only selection. On Counter Direct Sale choose part via ArrowDown+Enter or Tab, then tab into Part Note/Qty, and confirm the selected Part ID and auto price.
Authenticated end-to-end browser testing remains required even if source verification succeeds.
