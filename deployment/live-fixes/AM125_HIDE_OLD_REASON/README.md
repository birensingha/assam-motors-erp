# AM125 — Remove Duplicate Alteration Reason Select

**Staging only.** After AM124, the Main Job Card Admin Full ROT Correction popup had **two** visible Reason selectors: the custom searchable trigger and the older native select, which still opened outside the modal and underneath the backdrop.

AM125 applies a tightly guarded replacement to the already installed `resources/views/job-cards/rot-alteration.blade.php` AM124 code: the original `<select name="alteration_reason">` is hidden using `display:none!important` once AM124 builds its visible searchable trigger. There is **one** visible Reason control.

**Critical protections:** The original `<select>` is **not deleted, disabled, or renamed**. AM124 selection updates it and fires `change`; form submission still includes the original named field and Laravel's existing mandatory Reason/Note validation and audit are unchanged. Because hidden required fields cannot be focused by the browser, AM125 replaces its native required HTML5 validation with capture-phase checks on Save and Unlock. Blank reason opens the custom picker without submitting. Server validation remains mandatory.

Installation script: `scripts/AM125_HIDE_OLD_REASON_STAGING.sh`. It performs read-only source guards, creates backup in `storage/app/am-backups`, patches the Blade code in-place, compiles/caches views and checks the compiled PHP. Failure restores the exact original view and clears compiled cache. No migrations or any database writes.

Acceptance: Admin -> Main Job Card -> Admin Full ROT Correction -> Reason. Exactly one control should be shown, its options should appear inside modal, typing should filter, and choosing Reason + filling Admin Note should allow existing Unlock logic. Test without saving real customer entries. If the existing installed view has drifted, installer halts safely; no regex guessing and no forced overwrite.
