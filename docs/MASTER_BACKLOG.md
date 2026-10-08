# Assam Motors WEB-ERP — Master Backlog

Reconciled: **08-Oct-2026**

Status guide:
- **LIVE VERIFIED** — confirmed live/staging.
- **BUILD TESTED** — Android implementation builds successfully.
- **PATCH READY** — Git package/reference implementation prepared.
- **LIVE WIRING PENDING** — staging source/deployment not available in this repo.
- **CONFIG BLOCKED** — external deployment config/secret missing.
- **NOT YET PATCHED** — implementation package still pending.

For full evidence and blockers, see `docs/DELIVERY_STATUS_20261008.md`.

## Phase 1 — Native Staging Navigation / Authentication

- [ ] Booking & Orders native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Users native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Products native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Staff native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Job Applications native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Staff Location native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Service Reminder native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Customer / Customer Master native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Vendor Master correct native route — **PATCH READY / LIVE WIRING PENDING**
- [ ] Part Master native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Labour Master native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Vehicle Master native staging page — **PATCH READY / LIVE WIRING PENDING**
- [ ] Labour / Part Mapping native staging page — **PATCH READY / LIVE WIRING PENDING**
- [x] Attendance native staging route — **PROJECT-RECORDED AS RESTORED**

## Phase 2 — Payment Voucher

- [ ] Fix `/erp/payments/create` HTTP 500 — **PATCH READY / LIVE DIAGNOSTIC + WIRING PENDING**
  - exact staging exception trace still required
  - null-safe controller/view + acceptance package prepared

## Phase 3 — Job Card

- [ ] Create Job Card — **PATCH READY / LIVE WIRING PENDING**
- [ ] Edit Job Card — **PATCH READY / LIVE WIRING PENDING**
- [ ] Parts above Labour — **PATCH READY / LIVE WIRING PENDING**
- [ ] Labour column-wise — **PATCH READY / LIVE WIRING PENDING**
- [ ] Direct Add Parts without Estimate — **PATCH READY / LIVE WIRING PENDING**
- [ ] Direct Add Labour without Estimate — **PATCH READY / LIVE WIRING PENDING**
- [ ] QC Pending → QC Passed — **PATCH READY / LIVE WIRING PENDING**
- [ ] Ready for Delivery — **PATCH READY / LIVE WIRING PENDING**
- [ ] Job Card Close — **PATCH READY / LIVE WIRING PENDING**
- [ ] Convert to Invoice — **PATCH READY / LIVE WIRING PENDING**
- [ ] View Invoice — **PATCH READY / LIVE WIRING PENDING**
- [ ] Print Invoice — **PATCH READY / LIVE WIRING PENDING**

## Phase 4 — OSL Purchase

- [ ] OSL Purchase multi-line UI like Parts Purchase — **PATCH READY / LIVE WIRING PENDING**
- [ ] Per-line Job Card/Vehicle allocation — **PATCH READY / LIVE WIRING PENDING**
- [ ] Server-authoritative totals/save — **PATCH READY / LIVE WIRING PENDING**

## Parts Purchase — Search / Allocation

- [ ] Part Code autocomplete — **PATCH READY / LIVE WIRING PENDING**
- [ ] Part Name autocomplete — **PATCH READY / LIVE WIRING PENDING**
- [ ] Multi-line independent autocomplete state — **PATCH READY / LIVE WIRING PENDING**
- [ ] Purchase Against: Job Card search — **PATCH READY / LIVE WIRING PENDING**
- [ ] Purchase Against: Vehicle Registration search — **PATCH READY / LIVE WIRING PENDING**
- [ ] Normalize spaces/dashes/case — **PATCH READY / LIVE WIRING PENDING**
- [ ] Avoid false "No Job Card/Vehicle Found" — **PATCH READY / LIVE WIRING PENDING**
- [ ] Save-time Job Card eligibility re-validation — **PATCH READY / LIVE WIRING PENDING**

## Phase 5 — ROT Backend

- [ ] Asia/Kolkata timestamps for ROT actions/events — **PATCH READY / LIVE WIRING PENDING**
- [ ] UTC companion timestamps — **PATCH READY / LIVE WIRING PENDING**
- [ ] Persist Android Pause reason/note in ERP ROT history — **ANDROID BUILD TESTED + SERVER PATCH READY / LIVE WIRING PENDING**
- [ ] Full START/PAUSE/RESUME/COMPLETE audit history — **PATCH READY / LIVE WIRING PENDING**
- [ ] Retry/idempotency audit key — **PATCH READY / LIVE WIRING PENDING**

## Phase 6 — ROT Performance

- [ ] Waiting / Assigned ROT list — **PATCH READY / LIVE WIRING PENDING**
- [ ] Performance filters — **PATCH READY / LIVE WIRING PENDING**
- [ ] Technician Performance summary — **PATCH READY + ANDROID BUILD TESTED**
- [ ] Standard vs Actual/Productive — **PATCH READY + ANDROID BUILD TESTED**
- [ ] Efficiency % — **PATCH READY + ANDROID BUILD TESTED**
- [ ] Over-standard / Within-standard — **ANDROID BUILD TESTED**
- [ ] Detailed ROT History — **PATCH READY / LIVE WIRING PENDING**

## Phase 7 — Android ROT Live Progress

- [ ] Elapsed time — **BUILD TESTED**
- [ ] Standard time — **BUILD TESTED**
- [ ] Balance / percentage used — **BUILD TESTED**
- [ ] Over-standard warning — **BUILD TESTED**
- [ ] Pause reason/note transmission — **BUILD TESTED**

Android v6.0.18 release-gate builds **Run #105 PASS** and **Run #106 PASS**.

## Phase 8–11 — Staff Alerts / Compliance

- [ ] Server-global alert identity per Staff + ROT + Alert Type — **PATCH READY / LIVE WIRING PENDING**
- [ ] Three reminders then Admin escalation — **PATCH READY + ANDROID BUILD TESTED**
- [ ] Alerts persist across Check-Out/later Check-In — **PATCH READY / LIVE WIRING PENDING**
- [ ] Canonical Reminder Control + Notification Compliance — **PATCH READY / LIVE WIRING PENDING**
- [ ] Admin Notes — **PATCH READY / LIVE WIRING PENDING**
- [ ] Under Review — **PATCH READY / LIVE WIRING PENDING**
- [ ] Resolution audit — **PATCH READY / LIVE WIRING PENDING**
- [ ] Excel Staff Summary — **PATCH READY / DEPENDENCY + LIVE WIRING PENDING**
- [ ] Excel Alert Detail — **PATCH READY / DEPENDENCY + LIVE WIRING PENDING**
- [ ] FCM push primary — **ANDROID BUILD TESTED / CONFIG BLOCKED / SERVER LIVE WIRING PENDING**
- [ ] Android polling fallback — **BUILD TESTED**
- [ ] Device-token registry — **PATCH READY / LIVE WIRING PENDING**
- [ ] FCM HTTP v1 sender — **PATCH READY / SERVER CONFIG PENDING**

## Phase 12 — Final Android Synchronization

- [x] Integrated Android release branch created — **READY**
- [x] v6.0.18 / build 60018 Debug build — **PASS**
- [x] v6.0.18 / build 60018 Unsigned Release build — **PASS**
- [x] Existing production signing artifact/key package available — **AVAILABLE**
- [ ] Configure Firebase Android build secrets — **CONFIG BLOCKED**
- [ ] Deploy ERP alert/device-token backend — **LIVE WIRING PENDING**
- [ ] Configure FCM HTTP v1 server credentials — **CONFIG BLOCKED**
- [ ] Produce final production-signed v6.0.18 APK — **PENDING**
- [ ] Verify upgrade on installed Staff app — **PENDING**
- [ ] Verify direct/auto-update delivery path — **PENDING**

## Additional UX / Distribution Items

- [ ] Parts Purchase page readability / larger in-page presentation — **PATCH READY / LIVE VIEW WIRING PENDING**
- [ ] Estimate page readability / zoomed in-page presentation — **PATCH READY / LIVE VIEW WIRING PENDING**
- [ ] Keep Purchase/Estimate readable without separate large-window workflow — **PATCH READY / LIVE VERIFICATION PENDING**
  - same-page 100% / 115% / 125% controls prepared
  - default comfortable view is 115%
  - print/PDF scale remains independent
- [ ] Final Staff APK licensing/distribution package — **PATCH READY / FINAL SIGNED ARTIFACT PENDING**
  - Assam Motors internal-use licence notice prepared
  - About App shows licence/package/update identity
  - production bundle includes APK + release note + licence + SHA256SUMS + latest.json
  - live distribution endpoint/device update verification still pending

## Current hard blockers

1. **Actual staging ERP Laravel/PHP source is missing from this repository.**
2. **Firebase Android build secrets are not configured.**
3. **FCM server service-account credentials/project configuration are not deployed.**
4. **Final production-signed v6.0.18 should follow server deployment and end-to-end push verification.**
