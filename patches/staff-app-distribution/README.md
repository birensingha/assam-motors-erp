# Staff App Distribution / Update Feed

This package defines the ERP/server side contract for distributing official Assam Motors Staff Android updates.

Current Android release target:
- package: `com.assammotors.staff`
- version: `6.0.18`
- version code: `60018`

## Purpose

The Staff App Settings screen can call:

`GET /legacy/api/staff-app-update.php?platform=android&version_code=<installed-build>`

The endpoint returns the currently published production release metadata.

## Security model

- Update metadata comes from Assam Motors server.
- Download URL must be HTTPS on an Assam Motors controlled domain.
- Android client independently restricts the link host to `assammotors.com` or a subdomain.
- APK SHA-256 is published with the release package.
- Official update APK must keep the authorised Assam Motors signing identity.
- Signing keystore/password must never be exposed through this endpoint.

## Publication rule

Do not point the feed at a new APK until:
1. production workflow signs it;
2. `apksigner verify` passes;
3. SHA-256 is generated;
4. APK is uploaded to the official HTTPS location;
5. checksum is verified after upload;
6. device upgrade test passes.

See:
- `UPDATE_FEED_CONTRACT.md`
- `staff_app_update.php.example`
- `PUBLISH_CHECKLIST.md`
