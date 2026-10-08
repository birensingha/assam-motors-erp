# Assam Motors WEB-ERP — Delivery Status Register

Reconciled: **08-Oct-2026**

This register distinguishes code/package readiness from actual staging deployment.

## Status legend

- **LIVE VERIFIED** — confirmed working on staging/live.
- **BUILD TESTED** — implementation compiles/builds successfully, but may still require server/config deployment.
- **PATCH READY** — integration/reference patch is prepared in Git.
- **LIVE WIRING PENDING** — actual staging PHP/Laravel source is not present in this repository, so the prepared package has not been wired into staging.
- **EXTERNAL CONFIG BLOCKED** — code is ready but an external deployment input/secret is missing.
- **NOT STARTED / NOT YET PATCHED** — known requirement still needs an implementation package.

> A checkbox in the original backlog should not be treated as complete merely because a patch exists. Staging verification is the completion gate unless explicitly noted otherwise.

---

## Executive status

### Android Staff App

Release branch: `release/v6.0.18-20261008`

- **BUILD TESTED** — v6.0.18 / build 60018.
- GitHub Actions **Run #105: PASS**.
- GitHub Actions **Run #106: PASS** after the production Firebase guard was added.
- Debug APK: build passed.
- Unsigned Release APK: build passed.
- Existing production signing artifact/key package: available.
- **EXTERNAL CONFIG BLOCKED** — Firebase build values are not configured in GitHub secrets.
- Production workflow now fails fast if the four Firebase values are missing.
- **NOT YET PRODUCED** — final production-signed v6.0.18 APK.
- Existing polling fallback remains functional in builds without Firebase configuration.

Required Firebase build values:
- `FIREBASE_APP_ID`
- `FIREBASE_API_KEY`
- `FIREBASE_PROJECT_ID`
- `FIREBASE_SENDER_ID`

FCM server also requires securely deployed service-account credentials outside Git.

### ERP / Workshop

Release branch: `release/workshop-stack-20261008`

- **PATCH READY** — consolidated ERP release package exists.
- Draft PR: **#9** — currently open and mergeable.
- Android release Draft PR **#5** is also currently open and mergeable.
- **LIVE WIRING PENDING** — actual staging Laravel/PHP application source is not present in this repository.
- Therefore no ERP package below is being represented as already deployed.

---

## Phase 1 — Native Staging Navigation / Authentication

| Item | Status | Prepared package / note |
|---|---|---|
| Booking & Orders | PATCH READY / LIVE WIRING PENDING | `patches/phase1-native-operations/` |
| Users | PATCH READY / LIVE WIRING PENDING | Native account/role/deactivate contract |
| Products | PATCH READY / LIVE WIRING PENDING | Native catalog Product Master; distinct from Part Master |
| Staff | PATCH READY / LIVE WIRING PENDING | Native Staff/App-login/technician/attendance contract |
| Job Applications | PATCH READY / LIVE WIRING PENDING | Review/status/Admin-note contract |
| Staff Location | PATCH READY / LIVE WIRING PENDING | Read-only latest/history contract |
| Service Reminder | PATCH READY / LIVE WIRING PENDING | Due/overdue/customer-vehicle follow-up UI/contract |
| Customer Master | PATCH READY / LIVE WIRING PENDING | `patches/phase1-customer-native-auth/`; fixes Legacy auth-boundary design |
| Vendor Master | PATCH READY / LIVE WIRING PENDING | `patches/native-master-pages/` |
| Part Master | PATCH READY / LIVE WIRING PENDING | Native CRUD/search; source for Parts autocomplete |
| Labour Master | PATCH READY / LIVE WIRING PENDING | Standard-time/rate validation |
| Vehicle Master | PATCH READY / LIVE WIRING PENDING | Aligned with existing Vehicle prototype |
| Labour / Part Mapping | PATCH READY / LIVE WIRING PENDING | Mapping CRUD/search/reference UI |
| Attendance native route | LIVE/PROJECT-RECORDED | Existing project backlog already marks this restored; not independently re-verified in this reconciliation |

---

## Phase 2 — Payment Voucher

| Item | Status | Note |
|---|---|---|
| `/erp/payments/create` HTTP 500 | PATCH READY / LIVE WIRING PENDING | `patches/payment-voucher-500/` |
| Exact root-cause exception | LIVE DIAGNOSTIC PENDING | Requires staging `laravel.log` / actual source |
| Create page null-safe contract | PATCH READY | Controller/view reference prepared |
| Voucher POST/accounting wiring | LIVE SCHEMA PENDING | Must map to actual accounting/ledger schema |

---

## Phase 3 — Job Card

| Item | Status | Prepared package / note |
|---|---|---|
| Create Job Card | PATCH READY / LIVE WIRING PENDING | `patches/job-card-create/` |
| Customer / Vehicle validation | PATCH READY | Server ownership check contract |
| Parts above Labour | PATCH READY | Create + Edit contracts |
| Direct Add Part without Estimate | PATCH READY | Create + Edit contracts |
| Direct Add Labour without Estimate | PATCH READY | Create + Edit contracts |
| Labour column-wise | PATCH READY | No raw paragraph layout |
| Edit Job Card | PATCH READY / LIVE WIRING PENDING | `patches/phase3-job-card-edit/` |
| QC Pending → QC Passed | PATCH READY | Workflow gate defined |
| Ready for Delivery | PATCH READY | Active ROT/business-action checks defined |
| Job Card Close | PATCH READY | No running ROT + final totals |
| Convert to Invoice | PATCH READY / LIVE WIRING PENDING | `patches/job-card-invoice/` |
| Duplicate Invoice prevention | PATCH READY | Row lock + idempotent existing-invoice return |
| Immutable Parts/Labour invoice snapshot | PATCH READY | Historical integrity contract |
| View Invoice | PATCH READY | Snapshot view contract |
| Print Invoice | PATCH READY | A4 Parts-first/Labour-second reference |

---

## Phase 4 — OSL Purchase

| Item | Status | Note |
|---|---|---|
| Multi-line OSL Purchase like Parts Purchase | PATCH READY / LIVE WIRING PENDING | `patches/phase4-osl-purchase/` |
| 10–20+ row usable layout | PATCH READY | Reference form included |
| Per-line JC/Vehicle allocation | PATCH READY | Search/allocation contract |
| Server final totals/transaction | PATCH READY | Reference save handler |

---

## Parts Purchase — Search / Allocation

| Item | Status | Note |
|---|---|---|
| Part Code autocomplete | PATCH READY / LIVE WIRING PENDING | `patches/parts-purchase-search-allocation/` |
| Part Name autocomplete | PATCH READY / LIVE WIRING PENDING | Code/name/description/OEM contract |
| Multi-line independent search state | PATCH READY | Per-row result/selection state |
| Job Card search | PATCH READY | Normalized search contract |
| Vehicle Registration search | PATCH READY | Space/dash/case normalization |
| Avoid false "No Job Card/Vehicle Found" | PATCH READY | Job Card-first + LEFT JOIN optional relations |
| Save-time JC eligibility validation | PATCH READY | Server re-validation required |

---

## Phase 5 — ROT Backend

| Item | Status | Note |
|---|---|---|
| Asia/Kolkata ROT timestamps | PATCH READY / LIVE WIRING PENDING | `patches/phase5-rot-backend/` |
| UTC companion timestamp | PATCH READY | Included in audit contract |
| START / PAUSE / RESUME / COMPLETE audit | PATCH READY | Event audit table/helper |
| Android Pause reason/note transmission | BUILD TESTED | Included in Android v6.0.18 release chain |
| Server persistence of Pause reason/note | PATCH READY / LIVE WIRING PENDING | Requires live ROT endpoint integration |
| Retry/idempotency audit key | PATCH READY | Request-key design included |

---

## Phase 6 — ROT Performance

| Item | Status | Note |
|---|---|---|
| Waiting / Assigned ROT list | PATCH READY / LIVE WIRING PENDING | Must start from authoritative assignment/session table |
| Date/Staff/Status/Job/Vehicle filters | PATCH READY | Admin report contract |
| Technician Performance summary | PATCH READY + ANDROID BUILD TESTED | Admin package + Staff app metrics |
| Standard vs Actual/Productive | BUILD TESTED on Android; PATCH READY on ERP | v6.0.18 + Phase 6 report |
| Efficiency % | BUILD TESTED on Android; PATCH READY on ERP | Standard ÷ Productive × 100 |
| Over-standard / Within-standard | BUILD TESTED on Android | Performance cards |
| Detailed ROT History | PATCH READY / LIVE WIRING PENDING | Pause/start/end detail contract |

---

## Phase 7 — Android ROT Live Progress

All items below are **BUILD TESTED** in Android v6.0.18:

- Elapsed time.
- Standard time.
- Balance / percentage used.
- Over-standard warning.
- Local one-second running display.
- Paused/completed state does not keep incrementing.

Final Staff-visible behaviour still depends on live server ROT data being correct.

---

## Phase 8–11 — Staff Alerts / Compliance

| Item | Status | Note |
|---|---|---|
| Server-global alert identity | PATCH READY / LIVE WIRING PENDING | `patches/phase8-alert-compliance/` |
| Three reminders then Admin escalation | PATCH READY; Android BUILD TESTED | Server authoritative + local fallback |
| Persist across Check-Out/later Check-In | PATCH READY / LIVE WIRING PENDING | Poll excludes login/checkout state as deletion criterion |
| Merge Reminder Control + Compliance | PATCH READY / LIVE WIRING PENDING | Canonical server alert registry defined |
| Admin Notes | PATCH READY | Phase 9 |
| Under Review | PATCH READY | Remains unresolved/Staff-visible |
| Resolution audit | PATCH READY | Actor/status/note history |
| Staff Summary Excel | PATCH READY / DEPENDENCY CHECK PENDING | PhpSpreadsheet deployment required if not already installed |
| Alert Detail Excel | PATCH READY / DEPENDENCY CHECK PENDING | Same workbook/export contract |
| FCM push primary | Android BUILD TESTED; EXTERNAL CONFIG BLOCKED | Firebase build secrets absent |
| Android polling fallback | BUILD TESTED | Retained even when FCM unavailable |
| Device token registry | PATCH READY / LIVE WIRING PENDING | Phase 10 |
| FCM HTTP v1 sender | PATCH READY / EXTERNAL SERVER CONFIG PENDING | Requires service-account credentials outside Git |

---

## Phase 12 — Final Android Synchronization / APK

| Gate | Status |
|---|---|
| Integrated Android release branch | READY |
| v6.0.18 / build 60018 | READY |
| Debug compile | PASS — Run #106 |
| Unsigned Release compile | PASS — Run #106 |
| Production signing key/package availability | AVAILABLE |
| Firebase Android build secrets | BLOCKED — not configured |
| ERP alert/device-token backend live | PENDING |
| Firebase HTTP v1 server credentials live | PENDING |
| Final production-signed v6.0.18 APK | NOT YET PRODUCED |
| Direct device update / auto-update delivery | NOT YET VERIFIED |

Production workflow intentionally refuses to produce the FCM-enabled production APK while required Firebase build values are missing.

---

## Additional User-Reported UX Items

| Item | Status | Note |
|---|---|---|
| Parts Purchase form readability / larger usable view | PARTIALLY COVERED | Search/multi-line contracts prepared; exact live page visual tuning still requires source |
| Estimate form readability / zoomed presentation | NOT YET PATCHED | Live Estimate source/view required |
| Avoid opening separate large window; improve in-page readability | NOT YET LIVE VERIFIED | Must be applied to actual Purchase/Estimate templates |
| Staff notification presentation | BUILD TESTED/PATCH READY | Android notification receiver + alert center; FCM config still blocked |
| Staff APK licensing/distribution package | NOT FINALIZED | Repository has project licensing, but final production distribution/licence packaging is not marked complete |

---

## Current hard blockers

### Blocker A — Live ERP source unavailable in this repository

This prevents direct staging wiring/verification for:
- native pages;
- Payment Voucher 500;
- Job Card Create/Edit/Invoice;
- OSL/Parts Purchase;
- ROT server audit/performance;
- Alerts/Admin compliance/export;
- FCM token endpoint/sender.

### Blocker B — Firebase deployment configuration

Missing from GitHub build secrets:
- `FIREBASE_APP_ID`
- `FIREBASE_API_KEY`
- `FIREBASE_PROJECT_ID`
- `FIREBASE_SENDER_ID`

Server side also requires secure FCM service-account credentials and project ID.

### Blocker C — Production release should follow server deployment

Do not ship the FCM production APK as "fully enabled" before:
1. alert backend is live;
2. device-token endpoint is live;
3. FCM sender is live;
4. Firebase Android values are configured;
5. end-to-end test push succeeds;
6. polling fallback is also verified.

---

## Recommended next execution order

1. Make the actual staging ERP Laravel/PHP source available in the deployment repository/workspace.
2. Rebase/refresh consolidated ERP PR against current main if needed.
3. Wire Phase 1 native routes first so Admin navigation/auth is stable.
4. Fix and verify Payment Voucher 500 from the actual stack trace.
5. Wire Job Card Create/Edit/QC/Close/Invoice.
6. Wire Parts Purchase + OSL Purchase.
7. Wire ROT audit + Performance.
8. Wire Alerts/Admin compliance/export.
9. Configure Firebase server + Android build values.
10. End-to-end test Staff alerts on a real device.
11. Run existing production signing workflow for v6.0.18.
12. Verify upgrade installation over the currently installed production app.
