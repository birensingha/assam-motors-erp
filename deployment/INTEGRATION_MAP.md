# Assam Motors — Source Mapping / Integration Map

Release branch: `release/workshop-stack-20261008`

Purpose: map every prepared package to the exact kind of live Laravel/PHP source location where it must be integrated.

Because the real staging application source is **not present in this repository**, this document uses two labels:

- **EXPECTED TARGET** — normal Laravel/PHP location to inspect first.
- **DISCOVER IN STAGING** — exact live filename/class/table must be identified before editing.

Do not copy an `.example` file into production blindly.

---

# 1. First discovery pass

From the real staging application root:

```bash
cd /path/to/staging/app

php artisan route:list > /tmp/assam-routes.txt

grep -RniE "customers|vendors|parts|labour|vehicles|payments|job.?card|estimate|purchase|reminder|staff|rot|invoice" \
  routes app resources/views public legacy 2>/dev/null \
  > /tmp/assam-source-hits.txt
```

Also run:

```bash
bash /path/to/release/deployment/source-discovery.sh /path/to/staging/app
```

This produces a read-only source inventory.

---

# 2. Native Admin / Master pages

## 2.1 Customer Master

Prepared package:
`patches/phase1-customer-native-auth/`

### Route

**EXPECTED TARGET**
- `routes/web.php`
- or a module route file loaded by `RouteServiceProvider`

Canonical native route:
`/erp/native/customers`

**DISCOVER IN STAGING**

```bash
php artisan route:list | grep -i customer
grep -Rni "customers.php\|CustomerController\|customers" routes app resources/views 2>/dev/null
```

### Controller

**EXPECTED TARGET**
- `app/Http/Controllers/...Customer...Controller.php`

Integrate:
- Admin auth/permission
- native list/search
- no Legacy-session dependency

Reference:
`AdminNativeModuleController.customers.php.example`

### View

**EXPECTED TARGET**
- `resources/views/erp/native/customers/index.blade.php`

### Legacy compatibility

Existing URL:
`/legacy/workshop/customers.php`

Use redirect shim only if required:
`legacy_customers_redirect.php.example`

Never use the shim as an authentication bridge.

---

## 2.2 Vendor / Part / Labour / Vehicle / Labour-Part Mapping

Prepared package:
`patches/native-master-pages/`

### Routes

**EXPECTED TARGET**
- `routes/web.php`

Native route family:
- `/erp/native/vendors`
- `/erp/native/parts`
- `/erp/native/labours`
- `/erp/native/vehicles`
- `/erp/native/labour-part-mapping`

### Controllers

**EXPECTED TARGET**
- existing individual Master controllers, or
- a native Admin master controller

Reference:
`AdminNativeMasterController.php.example`

### Views

**EXPECTED TARGET**
- `resources/views/erp/native/vendors/*`
- `resources/views/erp/native/parts/*`
- `resources/views/erp/native/labours/*`
- `resources/views/erp/native/vehicles/*`
- `resources/views/erp/native/labour-part-mapping/*`

### Important cross-links

Part Master must feed:
- Parts Purchase autocomplete
- Job Card Parts search

Labour Master must feed:
- Job Card Labour search
- ROT Standard Hours

Vehicle Master must feed:
- Job Card customer/vehicle selection
- Purchase Against search
- Service Reminder

### Tables — DISCOVER IN STAGING

Likely concepts:
- vendor table
- part/item/inventory master
- labour/operation master
- vehicle master
- labour-part mapping

Do not assume example table names.

Discovery:

```bash
grep -RniE "class .*Vendor|class .*Part|class .*Labour|class .*Vehicle" app 2>/dev/null
grep -RniE "from\(['\"]?(vendors|parts|labours|vehicles)" app 2>/dev/null
```

---

# 3. Remaining Phase-1 native pages

Prepared package:
`patches/phase1-native-operations/`

Routes:
- Booking & Orders
- Users
- Products
- Staff
- Job Applications
- Staff Location
- Service Reminder

### Expected targets

Routes:
`routes/web.php`

Controllers:
`app/Http/Controllers/...Admin...Controller.php`

Views:
`resources/views/erp/native/<module>/`

Reference:
`AdminNativeOperationsController.php.example`

### Staff distinction

Keep separate:
- ERP User/Login identity
- Staff/Employee operational identity

Search live models/tables before wiring:

```bash
grep -RniE "class .*User|class .*Staff|job_application|service_reminder|staff_location|booking" app routes resources/views 2>/dev/null
```

---

# 4. Payment Voucher HTTP 500

Prepared package:
`patches/payment-voucher-500/`

Problem route:
`GET /erp/payments/create`

### Route

Run:

```bash
php artisan route:list --path=payments
```

Identify the exact controller method.

### Controller

**EXPECTED TARGET**
- `app/Http/Controllers/...Payment...Controller.php`
- method `create()`

Reference:
`PaymentController.create.php.example`

### View

**EXPECTED TARGET**
- `resources/views/erp/payments/create.blade.php`
- or whatever route/controller currently returns

Reference:
`payment_create.blade.php.example`

### Exact root cause

Before editing:

```bash
tail -n 200 storage/logs/laravel.log
```

Open `/erp/payments/create`, then capture:
- exception class
- message
- file
- line
- first application stack frame

Do not deploy a generic catch without fixing the actual exception.

---

# 5. Job Card Create / Edit

Prepared packages:
- `patches/job-card-create/`
- `patches/phase3-job-card-edit/`

## Routes

**EXPECTED TARGET**
`routes/web.php`

Expected route concepts:
- GET create
- POST store
- GET edit
- PUT/PATCH update
- workflow transitions

Discovery:

```bash
php artisan route:list | grep -Ei "job.?card|workshop"
```

## Controller/service

**EXPECTED TARGET**
- `app/Http/Controllers/...JobCard...Controller.php`
- optionally `app/Services/...JobCard...Service.php`

References:
- `JobCardController.create_store.php.example`
- `job_card_edit_guard.php.example`

## Views

**EXPECTED TARGET**
- `resources/views/erp/job-cards/create.blade.php`
- `resources/views/erp/job-cards/edit.blade.php`

Required layout:
1. Customer/Vehicle
2. Complaint/Notes
3. Parts
4. Labour/ROT
5. totals/workflow

## Database

**DISCOVER IN STAGING**
- Job Card header table
- Parts line table
- Labour line table
- technician/ROT assignment table

Additive prepared table:
- `job_card_edit_audit`

---

# 6. Job Card workflow → Invoice

Prepared package:
`patches/job-card-invoice/`

## Route

Expected:
- POST Job Card → Invoice conversion
- GET Invoice view
- GET Invoice print

Discovery:

```bash
php artisan route:list | grep -Ei "invoice|job.?card"
grep -RniE "convert.*invoice|invoice.*job|JobCardInvoice" app routes 2>/dev/null
```

## Service

**EXPECTED TARGET**
`app/Services/JobCardInvoiceService.php`

Reference:
`JobCardInvoiceService.php.example`

Integrate with live:
- Job Card locking
- Invoice number sequence
- invoice header
- parts snapshot
- labour snapshot
- tax totals
- audit

## Views

Expected:
- `resources/views/erp/invoices/show.blade.php`
- `resources/views/erp/invoices/print.blade.php`

Reference:
`invoice_view_reference.html`

## Database — DISCOVER IN STAGING

Identify:
- Invoice header table
- Invoice Parts table
- Invoice Labour table
- numbering/sequence table or helper
- Job Card invoice link

Do not create a second parallel invoice model if one already exists.

---

# 7. Parts Purchase search/allocation

Prepared package:
`patches/parts-purchase-search-allocation/`

## Existing Purchase page

Discover:

```bash
php artisan route:list | grep -Ei "purchase|parts"
grep -RniE "PurchaseController|parts purchase|purchase.*part" app routes resources/views 2>/dev/null
```

## API endpoints

Recommended:
- `GET /erp/api/purchase/parts/search?q=`
- `GET /erp/api/purchase/job-card-search?q=`

Expected target:
- existing Purchase controller/API controller
- or dedicated `PurchaseSearchController`

Reference:
`purchase_search.php.example`

## Frontend

Expected target:
- Parts Purchase Blade/template JS

Integrate:
`purchase_autocomplete.js.example`

Do not create one shared global selected ID for all rows.

## Tables — DISCOVER IN STAGING

Search authoritative:
- Part Master
- Job Card
- Vehicle
- Customer

Critical SQL shape:
Job Card → LEFT JOIN Vehicle → LEFT JOIN Customer.

---

# 8. OSL Purchase

Prepared package:
`patches/phase4-osl-purchase/`

## Route/controller

Discover existing OSL purchase module first.

Expected:
- OSL Purchase create/store/edit
- multi-line save in one transaction

Reference:
`osl_purchase_save.php.example`

## View

Reference:
`osl_purchase_reference.html`

## New additive table

Prepared:
`osl_purchase_line_items`

Before integration identify existing OSL purchase header table and its PK type.

Map:
`osl_purchase_line_items.purchase_id`
to the real header ID.

---

# 9. Purchase / Estimate readability

Prepared package:
`patches/purchase-estimate-readability/`

## Target views

**DISCOVER IN STAGING**

Search:

```bash
grep -RniE "estimate|purchase" resources/views public app 2>/dev/null
```

Likely target:
- Parts Purchase Blade
- OSL Purchase Blade
- Estimate create/edit Blade
- Estimate preview Blade

Reusable assets:
- `workshop_readability.css.example`
- `workshop_readability.js.example`

Do not use `window.open()` for normal page readability.

Keep `@media print` independent.

---

# 10. ROT backend audit

Prepared package:
`patches/phase5-rot-backend/`

## Android endpoint already expected by app

Existing Staff app calls ROT endpoint concept:
`/staff-rot-v3.php`

### Live endpoint — DISCOVER IN STAGING

Search:

```bash
find public legacy -type f -iname '*rot*' -o -iname '*staff*rot*'
grep -Rni "staff-rot-v3.php\|action.*pause\|action.*resume" public legacy app 2>/dev/null
```

Integrate audit write after authoritative business-state change.

Reference:
`rot_audit_helper.php.example`

Prepared table:
`rot_event_audit`

Persist:
- START
- PAUSE
- RESUME
- COMPLETE
- pause reason/note
- server local/UTC timestamps
- request key

---

# 11. ROT Performance

Prepared package:
`patches/phase6-rot-performance/`

## Admin API

Recommended:
`GET /erp/api/rot-performance`

Expected controller/service:
- Admin reporting controller
- dedicated ROT performance service

Reference:
`rot_performance_calc.php.example`

## View

Reference:
`admin_rot_performance_reference.html`

## Query source

Must start from the real assignment/session table so never-started Assigned ROTs remain visible.

Audit table alone is insufficient as the report's root dataset.

---

# 12. Staff Alerts / Reminder Compliance

Prepared package:
`patches/phase8-alert-compliance/`

## Existing Staff API endpoint

Android expects:
`/staff-reminders.php`

Actions:
- poll
- snooze
- resolve/business reconciliation

### Live source discovery

```bash
find public legacy -type f -iname '*reminder*' -o -iname '*alert*'
grep -Rni "staff-reminders.php\|action=poll\|action=snooze" public legacy app 2>/dev/null
```

Reference:
`staff_reminders_api.php.example`

Prepared tables:
- `staff_action_alerts`
- `staff_action_alert_events`

The server table becomes authoritative for:
- alert identity
- postpone count
- escalation
- resolution

---

# 13. Admin Alert Compliance / Excel

Prepared package:
`patches/phase9-admin-alert-review-export/`

## Route

Recommended:
`/erp/native/staff-alerts`

Expected:
- Admin queue controller
- detail action
- Under Review
- note
- resolve
- export

Reference:
`admin_alert_api.php.example`

## View

Reference:
`admin_alert_compliance_reference.html`

## Excel

Reference:
`admin_alert_export_xlsx.php.example`

Dependency:
`phpoffice/phpspreadsheet`

Install through normal Composer process if absent.

---

# 14. FCM token registration / sender

Prepared package:
`patches/phase10-fcm-push/`

## Device token endpoint

Android calls:
`/staff-device-token.php`

Expected live location:
- same Staff API area as login/reminder endpoints

Reference:
`staff_device_token.php.example`

Staff ID must come from authenticated session/token, never request JSON.

## Sender

Integrate after committed alert lifecycle changes.

Reference:
`fcm_sender.php.example`

Dependency:
`google/auth`

Prepared tables:
- `staff_push_devices`
- `staff_push_deliveries`

Secrets:
- `FIREBASE_PROJECT_ID`
- `GOOGLE_APPLICATION_CREDENTIALS`

Never commit service-account JSON.

---

# 15. Staff Android update feed

Prepared package:
`patches/staff-app-distribution/`

Android checks:
`GET /staff-app-update.php?platform=android&version_code=...`

Expected live location:
same authenticated Staff API namespace.

Reference:
`staff_app_update.php.example`

Publish only after:
- signed APK exists;
- checksum verified;
- trusted Assam Motors HTTPS download URL exists.

---

# 16. Android-to-server endpoint matrix

| Android feature | Current endpoint concept | Server package |
|---|---|---|
| Staff login | `/staff-login.php` | existing live API |
| ROT action | `/staff-rot-v3.php` | Phase 5 audit integration |
| Reminder poll/snooze | `/staff-reminders.php` | Phase 8 |
| ROT Performance | `/staff-rot-v3.php?action=performance` or `/staff-rot-performance.php` | Phase 6 |
| FCM token | `/staff-device-token.php` | Phase 10 |
| App update check | `/staff-app-update.php` | distribution package |

During deployment, confirm actual API base prefix used by Android `BuildConfig.API_BASE_URL`.

---

# 17. Database mapping sheet to fill before deployment

Before any application integration, fill this table from staging schema:

| Concept | Live table | Primary key | Important foreign keys | Status |
|---|---|---|---|---|
| Customer | TBD | TBD | — | DISCOVER |
| Vehicle | TBD | TBD | customer_id? | DISCOVER |
| Part | TBD | TBD | — | DISCOVER |
| Labour | TBD | TBD | — | DISCOVER |
| Staff | TBD | TBD | user_id? | DISCOVER |
| Job Card | TBD | TBD | customer/vehicle | DISCOVER |
| Job Card Parts | TBD | TBD | job_card/part | DISCOVER |
| Job Card Labour | TBD | TBD | job_card/labour | DISCOVER |
| ROT assignment/session | TBD | TBD | staff/job/labour | DISCOVER |
| Purchase header | TBD | TBD | vendor | DISCOVER |
| Parts purchase lines | TBD | TBD | purchase/part | DISCOVER |
| OSL purchase header | TBD | TBD | vendor | DISCOVER |
| Invoice header | TBD | TBD | job/customer | DISCOVER |
| Invoice Parts | TBD | TBD | invoice | DISCOVER |
| Invoice Labour | TBD | TBD | invoice | DISCOVER |
| Payment/Voucher | TBD | TBD | account/payee | DISCOVER |

Do not finalize SQL/controller wiring until this mapping is complete.

---

# 18. Route mapping sheet to fill before deployment

| Feature | Live route | Controller/action | View | Status |
|---|---|---|---|---|
| Customers | TBD | TBD | TBD | DISCOVER |
| Vendors | TBD | TBD | TBD | DISCOVER |
| Parts | TBD | TBD | TBD | DISCOVER |
| Labour | TBD | TBD | TBD | DISCOVER |
| Vehicles | TBD | TBD | TBD | DISCOVER |
| Payment create | `/erp/payments/create` | TBD | TBD | DISCOVER |
| Job Card create | TBD | TBD | TBD | DISCOVER |
| Job Card edit | TBD | TBD | TBD | DISCOVER |
| Invoice convert | TBD | TBD | — | DISCOVER |
| Invoice view | TBD | TBD | TBD | DISCOVER |
| Parts Purchase | TBD | TBD | TBD | DISCOVER |
| OSL Purchase | TBD | TBD | TBD | DISCOVER |
| ROT Performance | TBD | TBD | TBD | DISCOVER |
| Admin Alerts | TBD | TBD | TBD | DISCOVER |

---

# 19. Integration completion rule

A package is not considered **LIVE VERIFIED** until:

1. live route/controller/view/table mapping is filled;
2. code is integrated into the actual staging source;
3. database migration/schema mapping is applied where needed;
4. acceptance checklist passes;
5. logs show no new critical errors;
6. status register is updated from PATCH READY to LIVE VERIFIED.
