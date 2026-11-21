# AM260 — Common Tally-style keyboard entry throughout Assam Motors ERP

## Request — 19 November 2026
Following AM257 item-picker selection, AM258 always-ready blank item row and AM259 TYPE → REFERENCE Tab focus fix, owner requested the same keyboard data-entry behavior everywhere in the ERP: Enter or Tab to work without a mouse.

## Implementation
A shared `am260-unified-keyboard-entry.js` module attaches as a *second independent component* at the bottom of the **existing authenticated AM254 global autosave script**. AM254 is already loaded in the standard ERP and New Admin layouts plus standalone Staff, HR and workshop forms. It therefore applies to eligible dynamically rendered forms too, without modifying individual financial controllers or master-entry views.

- **Tab / Shift+Tab:** never intercepted; native forward/reverse order preserved, including the AM259 Estimate TYPE → REFERENCE fix.
- **Enter on an ordinary text, number, phone, email, URL or non-picker search field:** prevent accidental form submission, focus the next enabled visible editable input/select/textarea **within the same form**, skipping readonly, disabled, hidden and negative-tabindex controls.
- **Enter on searchable item picker:** do not intercept. AM257 specific handlers commit the highlighted Part/ROT/OSL or enhanced Master select. Tab afterwards advances to the next field.
- **Native select:** preserve browser selection behavior; use Tab to advance.
- **Textarea:** Enter inserts newline; Tab advances normally.
- **Last field:** Enter never triggers a hidden Save, Invoice, Payment or other business transaction.
- **Existing ready blank row:** AM258 remains the mechanism for Estimate, Purchase Create/Edit and Counter/Direct Sales only. Other line-item modules use their existing row builders; generic focus handling is intentionally not allowed to fabricate a new stock/billing line.

## Safety scope
Module operates only when authenticated AM254 bootstrap has a user ID and a valid authenticated page; it skips login/password/OTP, credential forms, GET filter searches, one-field action forms, finalizing/canceling/deleting/approving/voiding/attendance/ROT action routes. It never invokes `submit()`, `requestSubmit()`, `fetch()` or AJAX; never changes prices, GST, discount, stock, attendance, security, passwords, ledgers, invoices, or database columns. Explicit opt out on a sensitive form: `data-am-enter-native` or `data-am-no-keyboard`.

Source-anchored installer `check|apply|verify|rollback` maintains a private backup of the exact old AM254 autosave JS and edits no other file. Previous AM254 draft saving and AM257–259 keyboard behavior preserved.

## Verification
GitHub Actions STAGING SUCCESS: https://github.com/birensingha/assam-motors-erp/actions/runs/44519749011

- Source syntax and no-network/no-auto-submit preflight PASS.
- Synthetic user-event DOM tests PASS: TYPE → REFERENCE via Tab; description → Qty → Rate → Notes via Enter; Shift+Tab; typed Customer fields; excluded custom searchable inputs; no auto Save; GET search/void/password/one-field forms remain unmodified.
- Installer and verified exact deployed combined AM254 + AM260 JavaScript PASS.

## Browser acceptance still required
Authenticated browser testing on real ERP forms remains outstanding. Suggested tests: Job Card create/edit, Estimate, Purchase, Expense, Money Receipt, Payment and Customer edit; check the custom dropdown Enter/Tab and no premature POST. Some third-party autocomplete widgets or forms with their own key handlers may require targeted follow-up correction; do not claim every custom component has been individually tested. The Android native app is not modified by this web ERP patch.
