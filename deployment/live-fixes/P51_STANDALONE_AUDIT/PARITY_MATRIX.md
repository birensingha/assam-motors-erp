# P51 Staging Independence — Legacy Module Parity Matrix
Read-only inventory date: 2026-10-16. Old production and OLD admin remain untouched.

**Legend:** NATIVE ROUTE means a Laravel page exists, NOT that Old data was imported or that all behavior matches. LEGACY FILE means public legacy source remains; it does not establish whether any new page actively calls it.

| OLD module/file | NEW Laravel target/status | Migration needed |
| --- | --- | --- |
| `workshop/customers.php`, `customer-core.php` | `/erp/customers` native protected route exists | Customer/vehicle source-ID and ledger reconciliation; replace `customers.php?action` consumers |
| `workshop/vehicle-master-core.php`, `vehicle-master.html` | `/erp/masters/vehicles` exists | Make–model–year–variant, registration, chassis and existing edits migration |
| `workshop/parts-master.html` | `/erp/masters/parts` exists | Part identity/SKU/HSN/GST/stock valuation and stock-movement parity |
| `workshop/job-cards.php` | `/erp/job-cards` exists | Lifecycle, ROT, parts issues, estimates, historical statuses and audit cross-links |
| `workshop/inspection.php` | `/erp/vehicle-inspection` exists | Assigned inspections, photos and related history |
| `workshop/job-estimates.php`, `estimate-core.php` | `/erp/estimates` exists | Estimate versions, approvals, transfer-to-JC and document totals |
| `workshop/purchases.php` | `/erp/purchases` exists | Vendors, OSL, posted purchases, tax, stock and payable reconciliation |
| `admin/customer-directory.php` | `/erp/customers` exists; NEW Admin shortcuts pending | Admin-specific directory controls and filters parity |
| `admin/get-attendance*.php`, `update-attendance.php`, `toggle-attendance.php` | `/erp/attendance` exists | Permission semantics, historical staff attendance, audit and HR integration |
| `admin/get-staff.php`, `add-staff.php`, `update-staff.php`, `delete-staff.php` | Native Admin feature parity unverified | Full CRUD, role mapping, authorisation and staff-app consistency |
| `admin/staff-location.php`, `staff-alerts.php` | Laravel/mobile APIs exist in staging; exact web parity unverified | Staff tracking, notification delivery and controls |
| `admin/rot-control-v3.html`, `job-card-live.html` | Native ROT performance and Job Card control pages exist | Time history, assigned/paused/complete, permission checks and live sync |
| `staging/legacy/api/job-cards.php`, `job-card-parts.php`, `rot_sessions.php` | Native Laravel route counterparts may exist; no endpoint equivalence certified | Replace client fetch consumers endpoint-by-endpoint |
| `staging/legacy/api/vehicle-lookup.php`, `staff-login.php`, `staff-inspections.php` | Native endpoints/Staff App exist | Authentication, images, location, lookup payload compatibility |
| `staging/legacy/api/invoices.php`, `payment-verify.php`, `payment-lib.php` | `/erp/invoices/*`, `/erp/payments` exist | Invoice numbering, GST, payment allocation, audit/credit-note and ledger consistency |
| `admin/admin-login.php` | NEW protected Admin Console `/erp/admin`, active admin role only | Legacy account migration not yet done; NO old passwords copied |

## Verified inventory
- Staging 263 Laravel routes, 146 `erp` paths and 102 staging tables before new Admin route creation.
- Original staging had zero standalone `/admin` Laravel routes. P51 Phase 1 added `/erp/admin` under existing auth + `EnsureErpAdmin` middleware, with separate Admin Blade shell.
- Old Workshop public folder: 10 relevant source pages; Old Admin public folder: 17; staging legacy subtree: 68 relevant .php/.html pages (nested Admin/Workshop included).
- Native Laravel source contains six files with legacy references; those must be audited for runtime usage. Historic APIs may be called by old JS outside Laravel source.

## Data migration acceptance gates (all not yet certified)
1. Immutable OLD backups and Staging backups; hash and timestamp; credentials kept secret.
2. Per-domain table/column/FK diff, count-by-type, checksum or safe key match, source→target ID map.
3. Customer/Vehicle duplicate conflict report. Never replace existing native `id`.
4. Correct invoice/JC/estimate relation, GST-inclusive/exclusive semantics, customer/vendor balances, advances and credits. No double posting.
5. Stock/ROT/time/inspection attachments and staff permissions reconciliation.
6. Replacement native endpoints tested with actual browser/mobile traffic; zero required calls to `/legacy/` for supported journeys.
7. Cutover after sign-off only: freeze OLD writes, keep OLD as archive. OLD app paths/data not deleted.
