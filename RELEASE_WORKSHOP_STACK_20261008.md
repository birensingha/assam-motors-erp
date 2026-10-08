# Assam Motors Web-ERP — Workshop Release Stack

Release branch: `release/workshop-stack-20261008`

Current delivery truth/status register: `docs/DELIVERY_STATUS_20261008.md`

This branch consolidates the prepared Workshop/Staff implementation packages into one review/deployment line. It does **not** claim that staging has already been modified because the live ERP PHP/Laravel source is not present in this repository.

## Included work

- Customer Master native Admin-auth route and safe Legacy redirect
- Native Vendor / Part / Labour / Vehicle / Labour-Part Mapping routes and CRUD/search contracts
- Native Booking & Orders / Users / Products / Staff / Job Applications / Staff Location / Service Reminder contracts
- Job Card Create with Customer/Vehicle selection, Parts above Labour, direct add without Estimate and server-side totals
- Job Card Edit with Parts above Labour and direct add without Estimate
- Job Card Close → Invoice conversion with immutable Parts/Labour snapshot, duplicate prevention, View and Print flow
- QC → Ready for Delivery → Job Card Close → Invoice conversion workflow
- OSL Purchase multi-line fast-entry format
- Parts Purchase Part Code/Name autocomplete and Job Card/Vehicle allocation search fix
- Purchase + Estimate same-page readability layer with 100/115/125% in-page sizing and print isolation
- Payment Voucher `/erp/payments/create` HTTP 500 diagnostic/fix package
- ROT START / PAUSE / RESUME / COMPLETE audit history
- Asia/Kolkata + UTC event timestamps and pause metadata
- Technician ROT Performance and detailed history
- Server-global Staff Alerts with maximum 3 postponements
- Admin escalation, Under Review, Admin notes and resolution audit
- Staff Summary + Alert Detail Excel export contract
- FCM Staff device registry, HTTP v1 sender and delivery audit
- Polling fallback retained when push is unavailable
- Staff Android production distribution/update-feed contract with trusted Assam Motors download host and checksum metadata

## Recommended database migration order

1. `patches/phase3-job-card-edit/001_job_card_edit_audit.sql`
2. `patches/phase4-osl-purchase/001_osl_purchase_line_items.sql`
3. `patches/phase5-rot-backend/001_create_rot_event_audit.sql`
4. `patches/phase8-alert-compliance/001_staff_action_alerts.sql`
5. `patches/phase10-fcm-push/001_staff_push_devices.sql`

Review every migration against the live staging schema before applying.

## Recommended application wiring order

1. Customer + Vendor + Part + Labour + Vehicle + Labour/Part Mapping native routes/navigation
2. Job Card Create
3. Job Card Edit
4. Job Card Close → Invoice conversion / View / Print
5. OSL Purchase
6. ROT audit hooks
7. Technician Performance
8. Alert poll/snooze/resolve
9. Admin Alert Compliance/export
10. FCM device registration/sender

## Android dependency

Deploy server-global Alerts and the Staff device-token endpoint before enabling Firebase values in Android Staff App v6.0.18.

## Security / integrity rules

- No Admin auth bypass.
- Staff identity comes from the authenticated server session/token, not Android JSON.
- Server totals/calculations remain authoritative.
- Invoiced Job Cards remain locked except explicit authorised workflow.
- Firebase service-account/private keys are never committed to Git.
- Push failure never rolls back Attendance, ROT or other business transactions.

## Current limitation

These are deployment-ready integration packages/reference implementations. The actual live staging ERP PHP/Laravel source must be added or made available in the deployment workspace before the features can be wired live.
