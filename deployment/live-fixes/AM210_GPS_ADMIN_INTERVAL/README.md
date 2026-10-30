# AM210 — Staff GPS interval API, seconds precision

**Status: STAGED ONLY, NOT ROUTED OR DEPLOYED.**

The current Admin-selected timing is stored in
\`staff_location_settings.sample_seconds\` (nullable; fall back to
\`sample_minutes * 60\` until Admin custom interval is saved).

## Proposed authorized Staff endpoint
GET \`/api/v1/staff/location/reporting-settings\`:
\`\`\`json
{"ok":true,"interval_seconds":90,"sample_minutes":2,"source":"STAFF_LOCATION_SETTINGS"}
\`\`\`
The exact interval is **90 seconds**, not the rounded legacy two minutes.
Valid configurable range: **30–900 seconds**, any whole-second value.

Controller source staged at:
\`files/app/Http/Controllers/Api/V1/GpsReportingIntervalController.php\`.

**MUST** inherit existing authenticated/active Staff POST GPS reporting
route's middleware. No public endpoint; no coords, tokens, GPS history or
staff personal info in response.

Read-only STAGING audit:
\`\`\`bash
cd ~/assam-motors-deploy &&
git pull --ff-only origin main &&
python3 deployment/live-fixes/AM210_GPS_ADMIN_INTERVAL/audit.py \
 "$HOME/domains/assammotors.com/assam-erp-staging"
\`\`\`

Next: verify current route and authentication, implement/activate route
safely, test 200 authorized / 401 not logged in / denied revoked accounts,
and test the Android QA build. Android 6.3.20 currently fixed 60sec;
Admin save alone doesn't alter installed phones. All GPS/geofence/
attendance/ROT protections remain independent.


## Secure integration helper (new)
The \`install_api.py\` helper is now staged beside this README:
- \`python3 install_api.py check ROOT\`: read-only API route & auth/middleware preflight, exact POST GPS controller/URI anchor check, syntax check.
- \`python3 install_api.py prepare ROOT\`: copies only the new GPS interval GET controller; no route or database changes.
- \`AM210_STAGING_APPROVED=YES python3 install_api.py apply ROOT\`: backs up the original \`routes/api.php\`, installs the read-only GET endpoint **next to** the existing GPS POST route and requires that their final registered Laravel middleware lists match. Any mismatch automatically restores the original routes.
- If the existing route uses different nesting, per-route middleware, controllers or an ambiguous shape, the script **must safe-stop** and wait for a verified route-specific patch. Never bypass authentication.

**Recommended first command:**
\`\`\`bash
cd ~/assam-motors-deploy &&
git pull --ff-only origin main &&
ROOT="$HOME/domains/assammotors.com/assam-erp-staging" &&
python3 deployment/live-fixes/AM210_GPS_ADMIN_INTERVAL/audit.py "$ROOT" &&
python3 deployment/live-fixes/AM210_GPS_ADMIN_INTERVAL/install_api.py check "$ROOT"
\`\`\`
This command is **read-only**. Inspect the results before migration/installation.
