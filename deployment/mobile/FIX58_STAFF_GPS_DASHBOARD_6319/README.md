# FIX58 — Assam Motors Staff GPS Dashboard (Android v6.3.19 WebView)

Source patch prepared against the embedded `assets/fresh/index.html` GPS screen in the original **signed** Staff APK (currently v6.3.19 / build 60319).

## What changes
- A compact blue **Staff Live Location** header and five workforce KPI cards.
- A large, single-staff OpenStreetMap view using actual server-uploaded coordinates.
- Searchable staff location register, attendance and freshness filters, individual focus mode.
- Recorded GPS fix age, device-reported accuracy, weak/delayed/stale badge, and live server sync age displayed separately.
- Read-only privileged admin API requests. No GPS, geofence, login, check-in, ROT, background service, or notification setting is changed.
- Workshop map button only works when coordinates are returned by the admin API.

## Safe operational rules
- Uses the latest **recorded_at** fix, not the last refresh, for freshness.
- 'FRESH GPS' requires a real coordinate, a parseable recent fix timestamp, and acceptable device accuracy.
- A stale or weak point may still be drawn, but must never be labeled VERIFIED live.
- Staff access remains enforced by the existing Android Owner role and authenticated native API endpoints.
- No GPS position is invented, and map coordinates are **not** continuously sampled by this UI.

## Bundling and release
Run `.github/workflows/qa-fix58-signed-apk-ui-assets-20261019.yml` or let its push trigger execute. It checks the current published signed APK, merges only new files in `assets/fresh`, and produces a **source asset ZIP**, not an installable APK.

To ship to the **existing** Android app, insert the tested source assets into the original v6.3.19 Android build project, increment versionCode beyond 60319, and build/sign with **the exact same original signing certificate**. Then validate APK signature, package `com.assammotors.staff`, logged-in Owner GPS, app update, and the SHA256-gated updater before publication.

**Do not install an APK signed with a different key, do not uninstall the existing app, and do not publish an unsigned/patched APK to the update feed.** The server's `android-staff-app` folder is an unrelated old RC scaffold, not the current signed v6.3.19 project.

No original release signing key was found in the staging repository/server audit; until a compatible signed build is possible, this is **ready for original-signed APK integration**, not already installed on phones.
