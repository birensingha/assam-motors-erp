# AM124 — Alteration Reason dropdown visible in Admin Full ROT Correction

**Context:** Main Job Card's AM116 modal uses `<dialog>` (`showModal` / top layer) with `overflow:auto`. In the submitted screenshot, the browser-native Alteration Reason dropdown is shown below the dialog and beneath the dark backdrop. Raw CSS pasted into SSH was interpreted as Bash commands, giving `command not found`; those commands did not edit Laravel CSS.

## Change

AM124 appends an `@once` Blade style/script to the existing Staging `resources/views/job-cards/rot-alteration.blade.php`. Scoped exclusively to `#am116-rot-dialog`, it retains the exact original `<select name="alteration_reason">` but displays a compact searchable reason picker rendered **inside the modal's top layer**. The picker chooses above or below depending on available dialog space, supports keyboard/filtering, and dispatches `input` and `change` on the original select.

- Original CSRF, select `name`, option values, reason validation, Admin Unlock, audit and save POST action remain unchanged.
- The required select is visually hidden but remains in the form. If validation fails it opens the custom picker.
- The existing standalone Amount Editor is unaffected because the replacement is scoped to AM116.
- No routes, controllers, job card data, attendance/GPS or FCM records change.

## Deploy

Hostinger Staging: `scripts/AM124_FIX_REASON_DROPDOWN_STAGING.sh`. It runs a read-only source preflight first, copies a timestamped backup of `rot-alteration.blade.php`, applies the Blade fragment, clears/compiles views, and checks compiled PHP. On failure it restores the backup and clears the view cache.

## QA

1. Admin Main JC -> Completed ROT -> Admin Full ROT Correction.
2. Select **Alteration Reason** near bottom of dialog; list should open **inside** the modal, not behind backdrop or footer.
3. Type to filter choices; choose one; existing select value and unlock behavior should work.
4. Try keyboard (Tab, Enter, Escape). Verify reason required validation.
5. Reopen another ROT: option value must be independent and correct; no duplicate picker.
6. Verify no edits to financial or mechanic history unless Admin deliberately submits the original form.

**This release is a dropdown UI patch only.** AM123 Full ROT Integration browser validation and Qty/Discount Amount backend parity are separate tasks. No successful browser QA has been claimed merely on PHP compilation.
