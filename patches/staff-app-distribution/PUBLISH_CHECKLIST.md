# Staff App Release Publication Checklist

## Build / signing

- [ ] Release version/code correct.
- [ ] Firebase production build values configured.
- [ ] Release APK built.
- [ ] APK signed with existing Assam Motors production key.
- [ ] `apksigner verify` passed.

## Integrity bundle

- [ ] APK present.
- [ ] Licence notice present.
- [ ] Release note present.
- [ ] `SHA256SUMS.txt` present.
- [ ] `latest.json` present.
- [ ] APK SHA-256 matches both checksum file and metadata.

## Server publication

- [ ] APK uploaded to trusted Assam Motors HTTPS endpoint.
- [ ] Downloaded copy checksum re-verified.
- [ ] Update feed populated with that exact URL/checksum.
- [ ] Update feed does not expose signing/private credentials.
- [ ] Auth/permission behavior tested.

## Device test

- [ ] Existing Assam Motors Staff app installed.
- [ ] Settings → Check for App Update shows new version.
- [ ] Download link opens only Assam Motors HTTPS host.
- [ ] Android recognises APK as an update to the existing package.
- [ ] User/device policy approves installation.
- [ ] App launches after update.
- [ ] Staff session/login behavior verified.
- [ ] Attendance/ROT/alerts smoke test passed.

## Rollout

- [ ] First rollout to one internal test device.
- [ ] Then small Staff group.
- [ ] Then remaining authorised devices.
- [ ] Keep prior release metadata/artifact for audit, not as an automatic downgrade.
