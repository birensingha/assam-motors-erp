# FIX43 — Staff App Update Channel

Purpose:
- publish Assam Motors Staff v6.0.21 through the existing in-app **Check for App Update** flow;
- install the missing authenticated legacy endpoint `/legacy/api/staff-app-update.php`;
- publish official release metadata at `/staff-app/latest.json`;
- publish the production-signed APK from the actual staging document root.

Important signing note:
- GitHub Debug APK v6.0.20 and Debug APK v6.0.21 do **not** share the same signing certificate.
- Therefore the currently installed Debug v6.0.20 cannot be overwritten by a production-signed APK.
- One signing transition/reinstall is required.
- After the production-signed app is installed, future updates must keep the same Assam Motors production signing identity and can use the normal Check for App Update flow.

No database migration.
