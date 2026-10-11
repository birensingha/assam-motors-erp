# FIX46 — Web Job Card Lifecycle + Invoice

Desktop ERP completion for the canonical workshop flow:

OPEN → WIP → WORK_COMPLETE → QC_PENDING → QC_PASSED → READY_FOR_DELIVERY → CLOSED → INVOICED

## Reuses existing staging data
- job_cards
- job_card_parts
- job_card_labour
- rot_sessions
- workshop_qc_checks
- workshop_osl_requests
- invoices
- customer_ledger
- job_card_change_audits

No duplicate lifecycle table is introduced.

## Server-side gates
- WIP requires completed/skipped Inspection + completed Diagnosis/Technician Decision.
- Work Complete blocks pending/running ROT, unfinished Labour, Parts not fitted/returned, and unfinished OSL requests.
- QC is a distinct stage with PASS / FAIL; FAIL returns the Job Card to WIP/Rework.
- Ready for Delivery requires latest QC PASS and no unfinished work.
- Close requires Ready for Delivery.
- Invoice requires CLOSED Job Card and Customer Master link.
- Duplicate active invoice creation is blocked/reused.
- Invoice creation posts the matching Customer Ledger DEBIT in the same DB transaction.
- Invoiced Job Card becomes locked.

## Web UI
A visible lifecycle stepper and action panel is included on the Job Card show page.
Invoice has ERP View and standalone Print / Save PDF views.

## Safety
- CHECK → backup → APPLY → VERIFY.
- Source rollback on apply/verify failure.
- Schema change is additive only: creates workshop_qc_checks only if it is absent.
