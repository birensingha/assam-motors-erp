# Assam Motors — Live Staging Map (09-Oct-2026)

Source: sanitized staging handoff archive generated from:
`/home/u956497103/domains/assammotors.com/assam-erp-staging`

Runtime:
- PHP 8.4.19
- Laravel 13.34.0
- Composer 2.9.8
- Staging app is not a Git worktree
- storage and bootstrap/cache are owned by the hosting user
- proc_open is disabled on the hosting PHP CLI

This document supersedes the earlier generic `DISCOVER/TBD` assumptions where the handoff provides exact evidence.

## Critical rule

**Do not apply the old prepared SQL migrations blindly.**

The live staging application already contains real production-oriented tables that overlap several reference migrations.

---

## Customer / native master

### Existing source

- Controller: `app/Http/Controllers/Web/AdminNativeModuleController.php`
- Customer view: `resources/views/admin/native/customers.blade.php`
- Part view: `resources/views/admin/native/parts.blade.php`
- Labour view: `resources/views/admin/native/labour.blade.php`
- Vehicle view: `resources/views/admin/native/vehicles.blade.php`
- Mapping view: `resources/views/admin/native/mappings.blade.php`
- Other native views:
  - users
  - products
  - staff
  - job-applications
  - staff-location
  - service-reminders
  - bookings-orders

Legacy customer endpoint still exists:
- `public/legacy/workshop/customers.php`

### Route finding

The current route inventory exposes `erp/attendance` through `AdminNativeModuleController`, but the collected route list does not show native Customer/Part/Labour/Vehicle routes.

**Implication:** native views/controller exist, but navigation/routes for these modules need source-level review before adding aliases. This is directly relevant to the old Legacy Customer “Admin login required” issue.

### Live master tables

- Customer candidates:
  - `am_erp_customers`
  - `customer_master`
- Vehicle:
  - `am_vehicle_master`
- Part:
  - `parts_master`
- Labour/ROT master:
  - `labour_master`
- Staff:
  - `staff`
- ERP login:
  - `erp_auth_users`

Controller/model source review is still required to identify the authoritative Customer table used by the Laravel native page.

---

## Vendors

### Exact route/controller

- `GET erp/vendors` → `Web\AdminVendorPageController@index`
- `POST erp/vendors` → `Web\AdminVendorPageController@save`
- vendor ledger/payment/toggle routes are live

### Source

- Controller: `app/Http/Controllers/Web/AdminVendorPageController.php`
- Service: `app/Services/VendorService.php`
- Model: `app/Models/WorkshopVendor.php`
- Payment model: `app/Models/WorkshopVendorPayment.php`
- Views:
  - `resources/views/vendors/index.blade.php`
  - `resources/views/vendors/ledger.blade.php`

### Tables

- `workshop_vendors`
- `workshop_vendor_payments`

---

## Payment Voucher

### Exact live route

- `GET erp/payments/create`
- route name: `erp.payments.create`
- controller: `Web\AdminExpensePaymentPageController`

Other live routes:
- payments index
- store
- show
- void
- expenses index/create/store

### Source

- Controller: `app/Http/Controllers/Web/AdminExpensePaymentPageController.php`
- Service: `app/Services/ExpensePaymentService.php`
- Model: `app/Models/WorkshopPaymentVoucher.php`
- View: `resources/views/payments/create.blade.php`
- Browser JS: `public/js/payment-voucher.js`

### Table

`workshop_payment_vouchers`

Important columns include:
- voucher_no
- payment_date
- source_type/source_id/source_reference
- job_card_id
- payee_type/vendor_id/payee_name
- amount/payment_mode/account_name/reference_no
- record_status
- void fields

**This is now the exact source target for the reported /erp/payments/create HTTP 500.**

---

## Job Card

### Live routes currently present

- `GET erp/job-cards`
- `GET erp/job-cards/{job}`
- `GET erp/job-cards/{job}/edit`
- `PUT erp/job-cards/{job}`
- Parts update/delete
- Labour mechanic assignment
- diagnosis workflow
- inspection report
- Job Card → purchase
- Job Card → estimate

### Important missing route from the collected inventory

No Laravel Job Card **create/store** route is visible in the route list.

The source inventory does contain:
- `resources/views/job-cards/form.blade.php`

This strongly suggests Create Job Card is not fully wired as a native Laravel route yet.

### Source

- Controller: `app/Http/Controllers/Web/AdminJobCardPageController.php`
- Service: `app/Services/JobCardService.php`
- Models:
  - `JobCard.php`
  - `JobCardPart.php`
  - `JobCardLabour.php`
- Views:
  - `job-cards/index.blade.php`
  - `job-cards/show.blade.php`
  - `job-cards/edit.blade.php`
  - `job-cards/form.blade.php`
  - `job-cards/inspection-report.blade.php`

### Tables

- `job_cards`
- `job_card_parts`
- `job_card_labour`
- `job_card_change_audits`
- diagnosis/inspection tables

### Audit migration decision

**DO NOT apply** reference migration:
`patches/phase3-job-card-edit/001_job_card_edit_audit.sql`

Live staging already has:
- migration `2026_10_08_120000_create_job_card_change_audits_table.php`
- table `job_card_change_audits`

Use the existing audit system.

---

## Job Card → Invoice

Live DB has:
- `invoices`
- unique `job_card_id`
- totals/status/customer/vehicle snapshot fields

However source inventory does **not** show a Laravel Invoice model/controller/view. It only shows:
- `public/legacy/api/invoices.php`

Therefore the prepared native Job Card → Invoice service must be integrated with the **existing invoice table/legacy behavior**, not create a parallel invoice subsystem.

Targeted source review is required before implementation.

---

## Estimate

### Source

- Controller: `AdminEstimatePageController.php`
- Service: `EstimateService.php`
- Views:
  - create
  - index
  - show
  - print
- JS: `public/js/estimate-entry.js`

### Tables

- `job_estimates`
- `job_estimate_items`
- approvals/events/transfers/print logs

Estimate → Job Card routes already exist.

---

## Parts Purchase / OSL Purchase

### Live routes

Laravel:
- `erp/purchases`
- `erp/purchases/create`
- `erp/purchases/{purchase}/edit`
- `erp/purchases/{purchase}/print`
- `erp/purchases/parts/search`
- `erp/purchases/osl/create`
- `POST erp/purchases/osl`

API:
- `api/v1/purchases/job-options`
- `api/v1/purchases/osl`
- `api/v1/purchases/parts/batch`
- purchase history/show/destroy

### Source

- Web controller: `AdminPurchasePageController.php`
- API controller: `Api/V1/PurchaseController.php`
- Service: `PurchaseService.php`
- Models:
  - `WorkshopPurchase.php`
  - `WorkshopPurchaseBatch.php`
  - `PurchaseAuditLog.php`
- Views:
  - `purchases/create.blade.php`
  - `purchases/edit.blade.php`
  - `purchases/osl-create.blade.php`
  - index/show/print
- JS:
  - `public/js/purchase-entry.js`
  - `public/js/purchase-edit.js`

### Live tables

Primary Workshop purchase design:
- `workshop_purchase_batches`
- `workshop_purchases`
- `workshop_vendors`

Older/general purchase tables also exist:
- `purchases`
- `purchase_items`

`workshop_purchases` is already line-normalized and contains:
- purchase_batch_no
- purchase_line_no
- purchase_type
- vendor
- job_card_id
- part_master_id
- labour_line_id
- osl_master_id
- description
- qty/rate/discount/GST/totals
- billing status

### OSL migration decision

**Do not apply** `patches/phase4-osl-purchase/001_osl_purchase_line_items.sql` unless source review proves a separate table is necessary.

The live `workshop_purchases` + `workshop_purchase_batches` structure already supports multi-line normalized purchase entries and already has OSL route/controller flow.

---

## ROT

### Live routes

Web:
- start
- pause
- resume
- complete
- change mechanic
- reopen/reset end time
- history

API:
- same lifecycle under `api/v1/rot/{session}/...`

Admin report:
- `GET erp/rot-performance`

### Source

- Web controller: `AdminRotPageController.php`
- API controller: `Api/V1/RotWorkflowController.php`
- Service: `RotWorkflowService.php`
- Assignment service: `RotAssignmentService.php`
- Models:
  - `RotSession.php`
  - `RotSessionEvent.php`
  - `RotMechanicSegment.php`
  - `RotAdminCorrection.php`
- View: `resources/views/dashboard/rot-performance.blade.php`
- History view: `resources/views/rot/history.blade.php`

### Tables

- `rot_sessions`
- `rot_session_events`
- `rot_mechanic_segments`
- `rot_admin_corrections`

`rot_sessions` already contains:
- Assigned/Running/Paused/Completed
- start/end/first_start/completed
- total_seconds
- pauses
- labour_line_id

`rot_mechanic_segments` contains productive_seconds and mechanic-change reason/note.

### ROT migration decision

**DO NOT apply** reference `rot_event_audit` table migration.

Use/extend the existing `rot_session_events` / `rot_mechanic_segments` design.

Targeted source review is required to decide the safest location for pause reason/note persistence.

---

## Staff reminder / compliance

### Live APIs

- `GET api/v1/staff/reminders`
- `POST api/v1/staff/reminders/{reminder}/ack`
- `GET api/v1/admin/staff-notification-compliance`
- staff notifications APIs

Legacy compatibility file exists:
- `public/legacy/api/staff-reminders.php`

### Source

- `Api/V1/ReminderController.php`
- `Api/V1/AdminStaffComplianceController.php`
- `Api/V1/StaffNotificationController.php`
- `app/Services/StaffReminderEngine.php`
- `app/Services/MandatoryNotificationService.php`
- Admin page: `AdminStaffNotificationPageController.php`
- View: `resources/views/admin/staff-notifications.blade.php`

### Tables

- `staff_reminder_state`
- `staff_reminder_settings`
- reminder holidays/log support tables

`staff_reminder_state` already has:
- reminder_type
- target_key
- sent_count
- first_due_at
- last_sent_at
- escalated_at
- resolved_at
- context_json
- unique staff/type/target identity

### Alert migration decision

Do not create a second `staff_action_alerts` subsystem by default.

Extend the existing server-authoritative reminder state/engine unless targeted source review reveals a missing requirement.

---

## FCM / devices

### Native API

- `POST api/v1/staff/device`
- `GET api/v1/staff/device/compliance`

### Source

- `Api/V1/DeviceController.php`
- `FirebaseFcmService.php`
- `StaffDevice.php`
- `NotificationOutbox.php`
- commands:
  - FirebaseHealthCheck
  - FcmSmokeTest
  - DispatchNotificationOutbox
  - Stage10Preflight

### Live table

`staff_devices` already stores:
- staff/device identity
- FCM token
- app/platform/device info
- notification/location permissions
- invalidated timestamp
- last FCM error
- active/last seen

### FCM migration decision

**DO NOT apply** the reference `staff_push_devices` migration as a separate competing registry.

Use the existing `staff_devices`/outbox/Firebase service.

---

## Android compatibility finding — BLOCKER

Android `main` currently uses:

`API_BASE_URL = https://staging.assammotors.com/legacy/api`

Existing legacy files confirmed in staging:
- `staff-login.php`
- `staff-checkin.php`
- `staff-checkout.php`
- `staff-attendance.php`
- `staff-reminders.php`
- `staff-rot-v3.php`
- `staff-rot-performance.php`

But the staging handoff does **not** contain:
- `public/legacy/api/staff-device-token.php`
- `public/legacy/api/staff-app-update.php`

Android v6.0.18 currently calls exactly those two missing legacy URLs for:
- FCM token registration
- app update check

Therefore these two Android features are currently expected to **404** unless another webserver rewrite supplies them.

This must be resolved before final production signing.

Preferred resolution will be chosen after targeted source review:
1. add authenticated legacy compatibility wrappers that delegate to the existing Laravel native APIs/services; or
2. change the Android implementation to call the native Laravel API routes directly.

Do not guess the auth bridge until current source is inspected.

---

## Immediate next source bundle

To finish exact wiring, collect **targeted source contents** only (no .env/no secrets) for:
- routes/web.php
- routes/api.php
- the controllers/services/models/views named above
- relevant legacy Staff API compatibility files
- Purchase/Payment/Estimate browser JS
- existing migrations for Job Card audit, Staff devices/reminders, Payment module

Use `deployment/collect-targeted-live-source.sh`.
