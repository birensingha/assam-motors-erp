# FIX43 — Staff App Update Channel

Purpose:
- make the existing **Settings → Check for App Update** flow functional;
- install authenticated `/legacy/api/staff-app-update.php`;
- publish signed release metadata;
- keep the production APK in web-blocked private storage;
- return a short-lived HMAC-signed Assam Motors download URL.

Signing reality:
- Debug v6.0.20 certificate SHA-256: `061cecc2...`
- Debug v6.0.21 certificate SHA-256: `57eb3ebf...`
- therefore those two GitHub Debug APKs cannot update each other in place.
- FIX43 publishes the **Assam Motors production-signed v6.0.21** release.
- The current Debug v6.0.20 needs a one-time uninstall/reinstall to move onto the permanent production signing identity.
- After that transition, future updates must keep the same production signing key and can use **Check for App Update** normally.

No database migration.
