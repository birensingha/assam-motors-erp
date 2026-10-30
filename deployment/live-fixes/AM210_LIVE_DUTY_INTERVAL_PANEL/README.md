# AM210 — Live Duty Location GPS Reporting Interval (STAGING)

**Requested placement:** on the existing **Live Duty Location** page, directly BELOW the staff details/map block and BEFORE the existing GPS explanation, not in a new Configuration page.

**Status:** patch committed to ERP main, **NOT applied to Hostinger**. No customer, staff GPS, attendance, or ROT records have been touched by this repository commit.

## Visible behavior
- Admin-only compact section named **GPS Reporting Interval**
- Current value fetched from authoritative `staff_location_settings.sample_minutes`
- Controls: **60 Seconds (1 Minute)** and **5 Minutes**, followed by **Save Reporting Interval**
- Success, permission failure and session expiry displayed without inventing a successful save
- When another Admin updates it, page checks current setting at most once a minute while visible
- If legacy GPS policy is a different value (e.g. 10 minutes), the current value is shown, but only 1/5-minute choices are offered for new saves
- This saves a single setting column only. Existing geofence radius, accuracy thresholds, GPS history and permissions remain unchanged.
- The live dashboard still checks the UI/feed every 1 second. **Reporting interval is NOT map refresh interval.**

## Safety of 5-minute reporting
Previously a GPS fix older than 2 minutes was marked DELAYED, which would create false warnings under 5-minute reporting. With AM210:
- LIVE = genuine GPS fix at most 2 minutes old.
- ON SCHEDULE = last-known GPS position within the configured longer reporting interval plus 90-sec tolerance. **It is NOT used for real-time geofence / check-in verification**.
- DELAYED/STALE = progressively beyond the configured cadence.
- OFFLINE = older than 30 minutes or no current data.
The existing Check-In/ROT strict geo-verification continues to require a fresh verified fix, not just ON SCHEDULE.

## Stage 1: page + ERP Admin save
```bash
cd ~/assam-motors-deploy &&
git pull --ff-only origin main &&
ROOT="$HOME/domains/assammotors.com/assam-erp-staging" &&
python3 deployment/live-fixes/AM210_LIVE_DUTY_INTERVAL_PANEL/install.py check "$ROOT"
```

Apply after successful check:
```bash
AM210_STAGING_APPROVED=YES python3 \
  deployment/live-fixes/AM210_LIVE_DUTY_INTERVAL_PANEL/install.py apply "$ROOT"
```
Installer creates a private timestamped backup of the original route and view and
automatically rolls back on a failed Blade compilation or Admin middleware check.
Current panel controller is a new dedicated file, so prior workflows remain in place.

## Stage 2: Android actual reporting
Android **v6.3.20 still hard-codes 60 seconds**. Saving 5 minutes in this STAGING panel will not by itself change installed App behavior.

The Android QA branch in **Draft PR #51** implements dynamic interval reading.
Before release it needs:
1. A protected read-only Staff API GET `/api/v1/staff/location/reporting-settings` using the same authorized login as Staff GPS POST, to read existing `sample_minutes`. An API controller has been staged at `deployment/live-fixes/AM210_GPS_ADMIN_INTERVAL/files/...`, **but the route is not activated yet**.
2. The read-only route/auth audit in `AM210_GPS_ADMIN_INTERVAL/audit.py`.
3. Tests for GET 200 authorized, 401 unauthenticated and revoked sessions, interval 1->5->1, and no change to existing location POST, ROT, Check-In and assigned staff permissions.
4. Android debug build, QA on a real phone with background and network recovery tests, new signed versionCode and original install signer.

GPS delivery time is an **OS/network constrained target**, never a guaranteed wall-clock 60s / 5min.
