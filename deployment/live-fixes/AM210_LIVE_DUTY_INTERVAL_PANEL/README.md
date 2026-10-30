# AM210 — Custom GPS Reporting Timing under Live Duty Location (STAGING)

**Requested UX:** Admin enters ANY numeric GPS reporting interval, not a fixed
60sec/5min list. Keep the control immediately below **Live Duty Location**
staff details/map, not in Configuration or the Android app.

**Status:** Code and guarded installer committed to ERP repo main. **NOT
deployed to Hostinger.** The Android change remains in QA Draft PR #51, and
the Staff reporting-settings read-only API is staged but NOT routed yet.

## Current UI
- Numeric field "Report GPS every" and unit "Seconds / Minutes"
- Accepts 30–900 seconds (30 seconds to 15 minutes).
- Examples: 45 Seconds; 90 Seconds; 2 Minutes; 7 Minutes; 10 Minutes.
- "Current: N Seconds / M Minutes" fetched from server; Admin-only Save.
- No database or App changes when the user simply types but does not Save.
- Data changes affect global Staff GPS policy, NOT Check-In/ROT, geofence or
  map refresh frequency. Phone/network constraints can delay uploads.

## Exact seconds require one additive column
Existing \`staff_location_settings.sample_minutes\` stores integer minutes.
For 90 seconds the new \`staff_location_settings.sample_seconds\` is needed.
Its migration is nullable/non-destructive; no GPS history data is altered.
\`sample_minutes\` remains set to ceil(seconds/60) for older readers.
The Admin and Android new endpoint always prefer \`sample_seconds\`.

## Deployment procedure (Hostinger STAGING only)
Step 1: Read-only preflight:
\`\`\`bash
cd ~/assam-motors-deploy &&
git pull --ff-only origin main &&
ROOT="$HOME/domains/assammotors.com/assam-erp-staging" &&
PATCH="deployment/live-fixes/AM210_LIVE_DUTY_INTERVAL_PANEL/install.py" &&
python3 "$PATCH" check "$ROOT"
\`\`\`

Step 2: Prepare the migration file (no DB writes) and take a **fresh verified
STAGING database backup** before running the migration:
\`\`\`bash
python3 "$PATCH" prepare "$ROOT"
\`\`\`

Step 3: Run additive migration only after backup:
\`\`\`bash
(cd "$ROOT" && php artisan migrate --path=database/migrations/2026_10_30_180000_add_am210_sample_seconds_to_staff_location_settings.php --force)
\`\`\`

Step 4: Apply the Admin panel and protected save route:
\`\`\`bash
AM210_STAGING_APPROVED=YES python3 "$PATCH" apply "$ROOT"
\`\`\`

- \`check\` is read-only; \`prepare\` copies only new migration source.
- \`apply\` verifies the migrated column, backs up the original web route,
  view and pre-existing AM210 controller, then checks PHP/JS syntax, Laravel
  Blade compilation and Admin route middleware.
- In-place upgrade is supported if the older AM210 60sec/5min panel was already
  applied; the installer must not duplicate it. A failed apply restores source
  files; the harmless additive DB column stays.
- **Never rerun an earlier AM210 fixed-choice apply script from an older
  checkout.** Always \`git pull --ff-only\` first.

## Android end to end
- User's current installed 6.3.20 APK still hard-codes 60 seconds.
- Draft PR #51 has the Android policy-reader and adjusted Service sampling for
  exact integer seconds 30–900 (can change dynamically after initial supporting
  APK installation).
- ERP must FIRST securely expose GET
  \`/api/v1/staff/location/reporting-settings\`, authenticated using the same
  verified active Staff session policy as the existing POST location API.
  Staged controller lives in \`AM210_GPS_ADMIN_INTERVAL/files/...\`, but API
  route authorization still needs the live STAGING audit.
- Do not advertise saved Admin values as already active in installed Android
  6.3.20. Verify on real Staff devices after production-signer signed QA APK.

## Freshness presentation
- Genuine fix <= 2 min: LIVE; within extended Admin reporting interval:
  ON SCHEDULE (last-known only, never geofence-verified); beyond cadence:
  DELAYED/STALE; >30min without fix: OFFLINE.
- Check-In and ROT strict location authorization is not relaxed by GPS
  reporting frequency or status labels.
