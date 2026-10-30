# AM210 — Admin GPS Reporting Interval (60 seconds / 5 minutes)

Status: **Staged for compatibility audit. NOT installed to Hostinger; no live ERP route changes.**

## Goal
Reuse existing STAGING GPS Settings (`staff_location_settings.sample_minutes`) as the single source of truth. Admin changes it to **1 minute (60 seconds)** or **5 minutes**, at any time. New Android QA app periodically downloads the authenticated setting; no new APK is needed for **subsequent interval changes** (the initial supporting APK still must be installed).

## Package
- `audit.py`: read-only: existing API route and auth middleware, Admin GPS settings form/controller anchors, table columns and current sample_minutes. No customer/staff/location coordinates or tokens are printed; no writes.
- `files/app/Http/Controllers/Api/V1/GpsReportingIntervalController.php`: read-only, login-protected API **implementation file not installed yet**.

## Planned API
Authenticated GET `/api/v1/staff/location/reporting-settings`
returns:
```json
{"ok": true, "interval_seconds": 300, "sample_minutes": 5, "source": "STAFF_LOCATION_SETTINGS"}
```
- Must use the exact authentication/authorization conventions of existing authenticated POST `/api/v1/staff/location`.
- Unauthenticated/revoked user must not be granted access.
- Do not expose workshop latitude/longitude, geofence settings, staff IDs or location history.
- Mobile app defaults to 60 seconds until the endpoint is installed and validated. Offline retains the last server-verified interval.
- Existing setting values from 1 to 15 minutes are retained to avoid silently changing previous 10-minute configurations.

## Safe sequence
1. Run `python3 audit.py "$HOME/domains/assammotors.com/assam-erp-staging"`.
2. Inspect exact authenticated API route, existing Admin UI and actual sample_minutes value.
3. Patch existing Admin GPS Settings dropdown in place, with options 1 and 5 minutes, preserving all existing accuracy/geofence fields.
4. Add the protected read-only API endpoint after validating its route middleware matches existing Staff location POST.
5. Compile, verify authorized / unauthorized access, and regression test original GPS upload and attendance/ROT geofence authorization.
6. Build and test a signed next Android Staff App version using the original signing certificate, then verify toggling 1 ↔ 5 min on an enrolled device.

No new database migration, no duplicated GPS settings and no change to GPS reporting accuracy or Check-In/ROT precision requirements.
