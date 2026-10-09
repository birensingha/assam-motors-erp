# FIX38 — Job Card Edit consistency

Staging inspection confirmed the main edit process already exists:
- Edit route and PUT update route;
- Edit button on Job Card list;
- Edit Job Card button on Job Card Summary;
- dedicated edit page with mandatory Modification Reason;
- closed/invoiced/cancelled locking;
- `job_card_change_audits` backend;
- Part price/qty edit with reason and stock-safe delete.

FIX38 therefore avoids rewriting the controller. It only makes the shared rich Job Card form safe when rendered in edit mode:
- adds PUT method spoofing;
- adds mandatory Modification Reason.

No DB migration.
