# Staging Deployment Checklist

## Before
- [ ] Change window announced.
- [ ] Current deployed commit recorded.
- [ ] Staging source clean/reconciled.
- [ ] Read-only preflight reviewed.
- [ ] Full DB backup created and non-empty.
- [ ] Schema backup created and non-empty.
- [ ] Backups stored outside web root/Git.

## Database
- [ ] Job Card audit table checked/applied.
- [ ] OSL line table checked/applied.
- [ ] ROT audit table checked/applied.
- [ ] Staff alert/event tables checked/applied.
- [ ] Staff push device/delivery tables checked/applied.
- [ ] Resulting schemas inspected.

## ERP
- [ ] Native Admin pages.
- [ ] Payment Voucher exact 500 root cause fixed.
- [ ] Job Card Create/Edit.
- [ ] QC/Ready/Close.
- [ ] Invoice conversion/View/Print.
- [ ] Parts Purchase search/allocation.
- [ ] OSL multi-line.
- [ ] Purchase/Estimate readability.
- [ ] ROT audit/performance.
- [ ] Staff Alerts/Compliance.
- [ ] Admin Notes/Review/Resolution.
- [ ] Excel exports.

## Dependencies
- [ ] PhpSpreadsheet available before Excel.
- [ ] google/auth available before FCM.
- [ ] Laravel caches handled through normal deployment process.

## FCM
- [ ] Project ID configured.
- [ ] Service-account secret configured outside Git/web root.
- [ ] Device token registration works.
- [ ] FCM delivery audit SENT.
- [ ] Push received.
- [ ] Polling fallback independently verified.

## Android
- [ ] Firebase Android build secrets configured.
- [ ] Existing signing key used.
- [ ] Signed v6.0.18 generated.
- [ ] apksigner verification passed.
- [ ] licence/checksum/latest.json bundled.
- [ ] physical upgrade test passed.
- [ ] update feed published only after verified APK upload.

## Closeout
- [ ] Smoke-test results recorded.
- [ ] Logs reviewed.
- [ ] Deployment commit recorded.
- [ ] Backlog/status register updated.
- [ ] Rollback window closed after approval.
