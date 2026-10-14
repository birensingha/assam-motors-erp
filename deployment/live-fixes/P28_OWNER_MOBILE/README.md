# P28 — Native Android Owner/Admin Attendance and Manager Inspection API

## Endpoints (under existing authenticated Owner/Admin mobile token route group)

- `GET /api/v1/admin/mobile/my-attendance`: linked Admin Staff's IST attendance and allowed action
- `POST /api/v1/admin/mobile/my-attendance/check-in`: own Check-In
- `POST /api/v1/admin/mobile/my-attendance/check-out`: own Check-Out
- `GET /api/v1/admin/mobile/manager-inspections`: Job Cards, assigned manager, completion and deciding technician
- `GET /api/v1/admin/mobile/manager-inspections/{job}`: details/previous checklist
- `POST /api/v1/admin/mobile/manager-inspections/{job}/assign`: assign active Manager with reason
- `POST /api/v1/admin/mobile/manager-inspections/{job}/complete`: assigned Manager submits all 10 checklist items

## Business policy preserved

Only active Owner/Admin bearer identity. Staff Master link is server-side, never caller-provided for self attendance. Uses existing guarded and audited Web ERP attendance and Manager inspection business functions, including duplicate Check-In, missing/duplicate Check-Out, Running ROT, active Manager assignment, completed inspection edit locks, and IST server timestamps. Regular staff geofence and Staff App attendance endpoints are not modified.

API payload rejects ordinary non-admin Staff with HTTP 403. Remote-admin geofence exemption does not apply to regular staff.

## Status and testing

GitHub Actions staging API audit #38548556033 PASS, strict preflight #38548743565 PASS and staging deploy #38549045975 PASS.

Deployment verified:
- `P28_OWNER_BEARER_ROUTE_PARITY_PASS`
- `P28_ADMIN_SELF_ATTENDANCE_JSON_GET_PASS`
- `P28_MANAGER_INSPECTION_QUEUE_JSON_GET_PASS`
- `P28_NON_ADMIN_FORBIDDEN_PASS`
- `P28_NO_CHECKIN_CHECKOUT_OR_INSPECTION_POST_SUBMITTED`
- `P28_STAGING_OWNER_MOBILE_APIS_DEPLOY_PASS`

Routes backup under `storage/app/p28_owner_mobile_backup_20261014_155215_4040976/api.php`.

**Pending:** real user-issued Check-In/Out and Inspection completion smoke tests on signed v6.2.3 Android QA APK. No live attendance or inspection records were created by deployment.
