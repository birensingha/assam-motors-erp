# ASSAM MOTORS — Canonical Workshop Management Protocol

**Date locked:** 09-Oct-2026  
**Scope:** Existing staging ERP + existing Staff Android App. This is NOT a new system.

## 1. Core purpose

The Job Card is the authoritative Workshop Work Order. It links:
- Customer and Vehicle
- complaint / workshop instruction
- Vehicle Inspection
- Diagnosis / Technician Decision
- Estimate when used
- Parts
- Labour / ROT
- OSL / Outside Labour
- work execution
- Quality Check
- Ready for Delivery
- Job Card closure
- Invoice and Customer Ledger
- Staff Android actions and history

No workshop module may bypass the Job Card lifecycle.

## 2. Canonical high-level Job Card states

Use one authoritative work-order state vocabulary:

OPEN
→ WIP
→ WORK_COMPLETE
→ QC_PENDING
→ QC_PASSED
→ READY_FOR_DELIVERY
→ CLOSED
→ INVOICED

Exceptional states:
- QC_FAILED_REWORK → returns to WIP
- CANCELLED
- authorised CLOSED → REOPEN requires reason + audit

Do not use customer-facing labels as substitutes for the authoritative workflow state.

## 3. Separate sub-workflows

The Job Card state must not be overloaded with every sub-process.

Keep these separately controlled:
- inspection_status
- diagnosis_status
- decision_status
- estimate status/version
- part issue_status
- labour execution_status
- ROT session status
- OSL purchase/billing status

Customer public status is display/communication only and must derive from, or be validated against, the real workflow.

## 4. Job Card create protocol

1. Select/validate Customer.
2. Select/validate Customer-owned Vehicle.
3. Record odometer, service type, complaint, inward time, notes.
4. Generate Job Card number on server.
5. Save as OPEN.
6. Server transaction must prevent partial Job Card creation.
7. Server totals are authoritative.

Parts remain above Labour in the Job Card UI.

## 5. Inspection / Diagnosis protocol

- Vehicle Inspection is an official Job Card activity.
- Assigned staff must see the inspection in Staff Portal/App.
- Completed inspection records submitted-by and submitted-at.
- Deciding Technician may review the Inspection Report.
- Diagnosis + Technician Decision are required before Estimate Preparation.
- Authorised skip/reopen must be audited.

## 6. Estimate protocol

Estimate is OPTIONAL for Job Card execution because Assam Motors requires direct Part/Labour addition without an Estimate.

When an Estimate is used:
1. Inspection/Diagnosis gate applies.
2. Estimate is versioned.
3. Approval does not itself execute work.
4. Approved estimate must be explicitly SENT TO JOB CARD.
5. Released lines become planned Job Card work.
6. Additional work during WIP uses SUPPLEMENTARY ESTIMATE.
7. Previously approved estimate remains immutable.

## 7. Direct Parts / Labour protocol

Authorised Admin may add Parts or Labour/ROT directly without Estimate.

Every direct addition must:
- link to the same Job Card;
- carry source = DIRECT/MANUAL;
- use server-side calculation;
- create audit history;
- obey closure/invoice locks.

Direct Part creation must not silently issue stock unless an explicit stock-issue operation occurs.

## 8. Parts protocol

Each Job Card part line has a clear state:
PLANNED → ISSUED/FITTED → RETURNED/REVERSED as applicable.

Rules:
- stock movement must be transactional;
- issued/fitted lines cannot be hard-deleted;
- removal after issue requires stock reversal;
- zero/missing price blocks final closure/invoice;
- all changes require reason + audit where financial/stock state changes.

## 9. Labour / ROT protocol

Each Labour line is a work line; ROT is its execution timer/control.

ROT:
ASSIGNED → RUNNING → PAUSED ↔ RUNNING → COMPLETED

Rules:
- Start/Resume requires active staff check-in where current policy requires it;
- one mechanic cannot run conflicting ROT work;
- Pause stores reason/note;
- Complete synchronizes labour execution_status = COMPLETED;
- mechanic changes are audited;
- completed ROT reopen requires authorised reason;
- Job Card cannot become final WORK_COMPLETE/CLOSED while required ROT work is unresolved.

## 10. OSL protocol

OSL is Outside Labour linked to a Job Card, not an unrelated purchase.

Each OSL line must link:
Vendor + Supplier Bill + Job Card + Vehicle + Work Description + Purchase Cost + Customer Billing Value.

Rules:
- multiple OSL lines per supplier bill are allowed;
- each line must retain its own Job Card linkage;
- OSL can create/update the Job Card labour/billing line under the existing rules;
- CLOSED/INVOICED Job Cards reject normal OSL mutation;
- Job Card should surface related OSL status so Admin does not have to search a separate module to understand work completeness.

## 11. Work Complete gate

WIP → WORK_COMPLETE only when:
- required Labour/ROT lines are completed or explicitly administratively closed;
- no unresolved required Parts issue/reversal problem exists;
- OSL work required for this Job Card is resolved;
- required supplementary approval/work scope is not left unresolved;
- server totals can be recalculated.

WORK_COMPLETE is not Invoice-ready. It means workshop execution is finished and QC must start.

## 12. Quality Check protocol

WORK_COMPLETE → QC_PENDING.

QC records:
- QC user/staff
- date/time
- checklist/result
- note
- pass/fail

QC fail:
QC_FAILED_REWORK → WIP, with audit/reason.

QC pass:
QC_PASSED → READY_FOR_DELIVERY when final blocking checks pass.

## 13. Ready for Delivery

READY_FOR_DELIVERY means:
- QC passed;
- no running/unresolved ROT;
- final work scope resolved;
- final server totals available.

It is a real workflow state, not only a customer_public_status label.

## 14. Job Card Close

READY_FOR_DELIVERY → CLOSED only through an authorised Close action.

Close must:
- lock/recheck the Job Card;
- verify QC;
- verify ROT/work completion;
- calculate final Parts/Labour/OSL totals;
- timestamp closed_at;
- write audit.

Closed but not invoiced:
- normal work mutation is locked;
- authorised reopen requires mandatory reason and audit.

## 15. Invoice conversion

Only CLOSED Job Cards can convert to Invoice.

Conversion is atomic:
1. lock Job Card;
2. reject duplicate conversion;
3. validate final work;
4. recalculate server totals;
5. allocate Invoice No.;
6. create immutable Invoice header;
7. copy Parts snapshot;
8. copy Labour/OSL billing snapshot;
9. link Invoice ↔ Job Card;
10. set Job Card = INVOICED;
11. write audit;
12. commit.

Failure rolls back the entire conversion.

INVOICED is immutable for normal Job Card editing.

## 16. Customer / Vehicle rule

Customer Master and Vehicle Master are authoritative identities.

Job Card keeps required customer/vehicle snapshots for historical documents, but:
- selected vehicle must belong to selected customer;
- edit must never create a duplicate Job Card;
- identity changes must not silently corrupt historical invoices/service history.

## 17. Audit rule

At minimum record:
JOB_CARD_CREATE
JOB_CARD_EDIT
PART_ADD / UPDATE / REMOVE / STOCK_REVERSAL
LABOUR_ADD / UPDATE / REMOVE
TECHNICIAN_ASSIGN / CHANGE
ROT_START / PAUSE / RESUME / COMPLETE / REOPEN
OSL_ADD / UPDATE
STATUS_CHANGE
QC_PASS / QC_FAIL
READY_FOR_DELIVERY
JOB_CARD_CLOSE / REOPEN
INVOICE_CONVERT

Use actor, time, reason, before/after where relevant.

## 18. Staff Android App protocol

The App is a controlled client of the same Workshop system, not a second independent database.

Server is authoritative.

App must synchronize:
- login/session
- attendance/check-in state
- assigned Vehicle Inspections
- assigned ROT work
- live ROT state/time
- reminders/escalations
- completion/action acknowledgements
- app update metadata

Required sync behaviour:
- refresh on login, resume, successful action and explicit refresh;
- FCM may prompt refresh, polling remains fallback;
- write actions receive server acknowledgement;
- pending/retry state must never be shown as final success;
- duplicate/retried actions require idempotency protection;
- server version/state wins on conflict;
- offline pending commands may be retained locally, but final state changes only after server acceptance.

## 19. Current staging problems identified

1. Lifecycle is incomplete after WIP/Completed: QC → Ready → Close → Invoice is not yet fully live-wired.
2. Status vocabulary is fragmented: current Job Card status uses open/in-progress/completed/closed/invoiced/cancelled while customer_public_status separately contains Quality Check/Ready for Delivery/Delivered.
3. `completed` is overloaded and does not clearly mean WORK_COMPLETE vs QC/closure.
4. Current observed job_cards schema does not contain dedicated ready_at / qc_status / quality_check_at fields.
5. Two Job Card edit UI paths exist (edit.blade.php and shared form.blade.php), creating consistency risk.
6. Job Card operations are spread across header edit, direct-entry panel, Parts inline edit, ROT cards, OSL Purchase and Estimate screens instead of one coherent Work Order workspace.
7. Estimate-optional direct execution and Estimate-gated diagnosis flow both exist, but the UI does not explain the two valid modes clearly enough.
8. The observed job_card_parts schema default issue_status can conflict with the intended PLANNED-before-stock-issue behaviour and must be reconciled.
9. OSL is correctly linked to Job Cards but the Job Card must visibly expose related OSL work/completion.
10. Invoice conversion rules are documented but final staging lifecycle wiring remains unfinished.
11. Android currently uses direct endpoint refresh/polling; a unified reliable sync/retry/idempotency layer is not complete.
12. Browser/device acceptance is incomplete, so deployment/check PASS must not be treated as user-visible completion.
13. Live staging application source is not maintained as the canonical main repository source; the repository contains deployment patches/reference contracts. This causes repeated inspection and patch drift.

## 20. Decisions locked from 09-Oct-2026 discussion

- Do NOT build a new ERP.
- Continue the existing staging ERP and existing Staff Android App.
- Job Card is the permanent main development track.
- Customer, OSL, Purchase, Staff App, etc. are integrated into Job Card workflow; they do not replace/stop Job Card work.
- No more treating inspector/check output as feature completion.
- A feature is DONE only after browser/device workflow verification.
- 63-point rectification list is not considered fully complete until reconciled point-by-point.
- Current installed work such as FIX37/FIX38/FIX39/FIX40/FIX28 remains part of the same staging system.
- The target is a real Workshop Management workflow, end-to-end, not a collection of independent pages.

## 21. Completion definition

Staging Workshop is READY only when one real test vehicle can complete:

Customer
→ Vehicle
→ Job Card
→ Inspection
→ Diagnosis
→ optional Estimate / direct work
→ Parts + Labour/ROT + OSL
→ Work Complete
→ QC
→ Ready for Delivery
→ Close
→ Invoice
→ Payment / Customer Ledger

and the assigned staff can see and action the same work from the Android App without state mismatch.
