# AM241 — Dual Staff Complaint Rework

## Case
JC-2026-0011 | AS01HC0595 | Original COMPLETED ROT-CUS-0211 | Original mechanic Kapil Rajbongshi AM9472. Do not assume who should receive the rework: Admin selects a qualified mechanic. Customer complaint substance not yet supplied.

## Behavior
1. On an OPEN Job Card, original ROT remains COMPLETED and protected.
2. Admin registers factual complaint through AM240. No confirmed misconduct until supervisor investigation.
3. Admin creates a NEW ROT session using the existing native ROT workflow, within the same Job Card. Keep original session unchanged.
4. AM241 ComplaintReworkLinkService links new session to complaint and original session, captures assigned mechanic and instruction; rejects completed replacement ROT, wrong Job Card, missing staff or already linked ROT.
5. Two persistent staff inbox records are added in one transaction: ORIGINAL_WORK_COMPLAINT and COMPLAINT_REWORK_ASSIGNED.
6. Original mechanic sees which case and linked ROT was reassigned and can read complaint status; new mechanic sees complaint-related work, original ROT reference, and instructions.
7. App must authenticate staff identity from existing staff token/session; never accept recipient_staff_id supplied by a client to read inbox.
8. Month-end: original mechanic's complaint counted as Reported until evidence-based confirmation, receiving mechanic gets separate rework assignment/actual hours, not original fault.

## Implemented in this branch
- Additive AM241 migration for complaint rework links, staff notifications and immutable audit events.
- ComplaintReworkLinkService::link() and inbox() backend domain logic. No edits to original ROT.
- Does not silently edit job_card_labour, billing, staff salary, existing ROT timers, inspection or QC.

## Still required before deployment
- Inspect current STAGING schema and native ROT creation endpoint; build a controller/form that calls existing ROT create and then ComplaintReworkLinkService::link atomically.
- Inspect Android Kotlin app and staff_web controller; add authenticated GET /my-work-complaints and /my-complaint-reworks; show a visible complaint banner and unread count.
- Expose employee response and read acknowledgement with correct own-staff authorization and event audit.
- Add rework ROT into staff ROT screen with existing Start/Pause/Resume/Complete, attendance and mechanic concurrency controls (do not duplicate ROT execution).
- Add link from AM240 complaint details to Create Rework ROT, plus 'Complaint History' on original completed ROT.
- Create migration and integration smoke tests on a clean staging copy. Use real schema and service; never insert fake complaint or assign fault on JC-11 automatically.

## Test cases
- JC OPEN and original ROT COMPLETED: new linked rework allowed; old ROT status/time and amount unchanged.
- JC CLOSED/INVOICED/CANCELLED: link rejected.
- Wrong new-session JC or wrong new-session staff: rejected.
- Recipient mechanic sees assigned rework but not original mechanic's unrelated complaints.
- Original mechanic sees complaint and receiver's staff assignment but no work allocated to their own timer.
- Unconfirmed report: monthly Reported=1, Confirmed=0; supervisor confirmed workmanship then Confirmed=1.
- No changes to QC, Ready for Delivery, payment/invoice, stock, attendance or salary.

## Deployment status
GitHub development code only. No route, UI, Android APK, database migration, or STAGING deployment performed yet. AM240 and AM241 require code review and staging preflight, then end-to-end testing.
