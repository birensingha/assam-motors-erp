# Staff App Update Feed Contract

## Request

```
GET /legacy/api/staff-app-update.php?platform=android&version_code=60017
Authorization: Bearer <staff-token>
Accept: application/json
```

Authentication may use the existing Staff API bearer/session contract.

## Up-to-date response

```json
{
  "ok": true,
  "update_available": false,
  "latest": {
    "package_id": "com.assammotors.staff",
    "version_name": "6.0.18",
    "version_code": 60018
  }
}
```

## Update available response

```json
{
  "ok": true,
  "update_available": true,
  "latest": {
    "package_id": "com.assammotors.staff",
    "version_name": "6.0.18",
    "version_code": 60018,
    "channel": "production",
    "download_url": "https://downloads.assammotors.com/staff/Assam-Motors-Staff-v6.0.18-FRESH-PRODUCTION.apk",
    "sha256": "<64-hex-sha256>",
    "mandatory": false,
    "min_supported_version_code": 60017,
    "published_at": "2026-10-08T00:00:00Z",
    "release_notes": "ROT progress, alerts, performance, FCM/polling and release packaging."
  }
}
```

## Rules

- Compare integer `version_code`, not version-name text.
- Never return a lower version as a normal update.
- `package_id` must remain `com.assammotors.staff`.
- `download_url` must be HTTPS.
- Download host must be controlled by Assam Motors.
- `sha256` must be exactly the checksum of the published signed APK.
- Feed publication happens after APK publication, never before.
- `mandatory=true` should be reserved for genuinely unsupported/security-critical builds.
- Keep polling/API compatibility during phased rollouts.

## Android install behavior

The app opens the trusted official download/update page after Staff confirmation.

The normal Android package installer/device policy remains the authority that approves and installs an APK update.

Do not present the update feed as a silent-install mechanism.
