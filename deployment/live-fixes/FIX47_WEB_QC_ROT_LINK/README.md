# FIX47 — Desktop Web QC Failure ↔ Related ROT

Incremental follow-up to **deployed FIX46**. Applies to the current Laravel **staging** web Job Card only.

On QC FAIL, select the related ROT session and responsible Staff. The chosen ROT session must belong to the same Job Card (server-side validated). Record `rot_session_id` together with failed item and staff in the existing `workshop_qc_checks.failed_items_json`. A failed-item description is required on QC FAIL.

No new table, no migration, no new route. **Invoice creation, payment and customer ledger functions are not called or changed.** Source hash gates only permit applying to the verified pre-FIX47 version; backup and source rollback included.

No changes to Android or OLD/legacy ERP.
