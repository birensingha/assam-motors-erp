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
- GitHub Actions **Run #112: PASS** after licence/About/update-check changes.
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
| Debug compile | PASS — latest Run #112 |
| Unsigned Release compile | PASS — latest Run #112 |
| Production signing key/package availability | AVAILABLE |
| Firebase Android build secrets | BLOCKED — not configured |
| ERP alert/device-token backend live | PENDING |
| Firebase HTTP v1 server credentials live | PENDING |
| Final production-signed v6.0.18 APK | NOT YET PRODUCED |
| Direct device update / auto-update delivery | ANDROID CHECK UI + SERVER FEED PATCH READY; LIVE WIRING/INSTALL VERIFICATION PENDING |

Production workflow intentionally refuses to produce the FCM-enabled production APK while required Firebase build values are missing.

---

## Additional User-Reported UX Items

| Item | Status | Note |
|---|---|---|
| Parts Purchase form readability / larger usable view | PATCH READY / LIVE WIRING PENDING | Same-page comfortable-view layer and readable Purchase reference prepared |
| Estimate form readability / zoomed presentation | PATCH READY / LIVE WIRING PENDING | Same-page Estimate preview/reference prepared |
| Avoid opening separate large window; improve in-page readability | PATCH READY / LIVE VERIFICATION PENDING | 100/115/125% in-page controls; default 115%; no normal window.open flow |
| Staff notification presentation | BUILD TESTED/PATCH READY | Android notification receiver + alert center; FCM config still blocked |
| Staff APK licensing/distribution package | PATCH READY / PRODUCTION ARTIFACT PENDING | Internal-use licence, About identity, checksum/latest.json bundle, Android Check for App Update and server update-feed contract prepared; signed v6.0.18/live feed still pending |

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


---

## Deployment / Release Tooling

| Item | Status | Note |
|---|---|---|
| Staging deployment runbook | READY | Ordered DB → Admin → Payment → Job Card → Purchase → ROT → Alerts → FCM → Android flow |
| Rollback matrix | READY | Code-first rollback; preserve additive audit/data tables by default |
| Pre-deploy checklist | READY | Backup, dependencies, FCM, Android and closeout gates |
| Read-only preflight checker | READY / SYNTAX VERIFIED | `deployment/preflight.sh`; Bash syntax check passed; no migration/write actions |
| Machine-readable release manifest | READY | `deployment/release-manifest.json` |
| Actual staging execution | PENDING | Requires live Laravel/PHP source, DB access and deployment environment |


---

## Phase 14 — Source Mapping / Live Integration Map

| Item | Status | Note |
|---|---|---|
| Route/controller/view/API integration map | READY | `deployment/INTEGRATION_MAP.md` |
| Android-to-server endpoint matrix | READY | Included in Integration Map |
| DB concept-to-live-table worksheet | READY / TO BE FILLED ON STAGING | Customer/Vehicle/Part/Labour/Staff/JC/Invoice/Purchase mapping |
| Route mapping worksheet | READY / TO BE FILLED ON STAGING | Live route/controller/view discovery table |
| Read-only source discovery helper | READY / BASH SYNTAX PASS | `deployment/source-discovery.sh` |
| Machine-readable source map | READY | `deployment/source-map.json` |
| Actual live filename/table mapping | PENDING LIVE SOURCE | Requires staging application root |


---

## Phase 15 — Merge / Rebase Audit

| Repository | Status | Evidence |
|---|---|---|
| ERP release branch | NO REBASE REQUIRED | main → release: behind 0; consolidated PR #9 is the intended integration PR |
| Android v6.0.18 release branch | NO REBASE REQUIRED | main → release: behind 0; consolidated PR #5 is the intended integration PR |
| Android latest completed release CI | PASS | Run #114; Debug + Unsigned Release + artifact upload all passed |
| Stacked feature PRs | DO NOT MERGE IN PARALLEL WITH CONSOLIDATED PR WITHOUT RE-AUDIT | ERP #1–#8, Android #1–#4 remain draft/open |
| ERP consolidated PR mergeability | MERGEABLE | Fresh GitHub check returned true; behind main = 0 |
| Android consolidated PR mergeability | MERGEABLE | Fresh GitHub check returned true; behind main = 0 |


---

## Phase 16 — Final Pre-Merge Gate

| Check | Status | Note |
|---|---|---|
| Duplicate/dead patch audit | PASS | Phase-1 native-navigation umbrella explicitly marked superseded; no active duplicate deployment layer |
| Sensitive filename scan | PASS | No .env/JKS/keystore/service-account/private-key file tracked |
| Targeted secret content scan | PASS | No private key/API-key/JWT/bearer/hardcoded password found in sensitive release files |
| SQL destructive-statement scan | PASS | No DROP/ALTER/TRUNCATE/DELETE/UPDATE/REPLACE statements in prepared SQL |
| Android latest CI | PASS | Run #114 |
| ERP/Android behind main | PASS | Both behind = 0 at gate check |
| ERP/Android mergeability | PASS | Both consolidated PRs returned mergeable=true on fresh check |
| ERP live staging deployment | NO-GO | Live Laravel/PHP source and exact schema mapping still unavailable |
| Production signed Android v6.0.18 | NO-GO | Firebase/server/end-to-end gates remain pending |

Final report: `deployment/FINAL_PRE_MERGE_GATE_20261008.md`


---

## Phase 17 — Post-Merge Cleanup

| Item | Status | Evidence |
|---|---|---|
| ERP PR #9 | MERGED | merge commit `3186eaa858a88b9f2fc8a4a1a00ad1276c636c28` |
| Android PR #5 | MERGED | merge commit `da053a79284c0bd39264af15b022aba5accb7d04` |
| ERP stacked PRs #1–#8 | CLOSED / SUPERSEDED | consolidated into PR #9 |
| Android stacked PRs #1–#4 | CLOSED / SUPERSEDED | consolidated into PR #5 |
| ERP post-merge record | READY | `docs/POST_MERGE_VERIFICATION_20261008.md` |
| Android post-merge record | READY | `POST_MERGE_VERIFICATION_V6.0.18.md` |
| ERP desired tag | PENDING TOOL CAPABILITY | `workshop-stack-2026-10-08` |
| Android desired tag | PENDING TOOL CAPABILITY | `v6.0.18` |
| Staging deployment | NOT DONE | live Laravel/PHP source still unavailable |
| Signed production APK | NOT DONE | Firebase/server gates still pending |


---

## Phase 18 — Live Staging Handoff

| Item | Status | Note |
|---|---|---|
| Handoff guide | READY | `deployment/LIVE_STAGING_HANDOFF.md` |
| Sanitized collector | READY / BASH SYNTAX PASS | `deployment/collect-live-staging.sh` |
| DB schema-only probe | READY / PHP SYNTAX PASS | `deployment/db-schema-probe.php` |
| Source-code collection | DISABLED BY DESIGN | Collector gathers paths/inventory, not source contents |
| .env / credential collection | DISABLED BY DESIGN | Secret scan blocks archive on obvious key/password patterns |
| Business-row collection | DISABLED BY DESIGN | DB probe reads information_schema metadata only |
| Actual staging collection | PENDING | Run on the real staging APP_ROOT and upload generated tar.gz |


---

## Phase 19 — Live Schema Reconciliation

| Area | Live finding | Decision |
|---|---|---|
| Job Card audit | `job_card_change_audits` already live | Reference audit SQL **DO NOT APPLY** |
| OSL/Purchase | `workshop_purchase_batches` + `workshop_purchases` already normalized | Reference OSL line-table SQL **DO NOT APPLY** |
| ROT audit/performance | `rot_sessions`, `rot_session_events`, `rot_mechanic_segments` live | Reference ROT audit SQL **DO NOT APPLY** |
| Staff reminders | `staff_reminder_state` + engine/settings live | Reference Staff action-alert tables **DO NOT APPLY** |
| FCM/device registry | `staff_devices` + Firebase service/outbox source live | Reference push-device SQL **DO NOT APPLY** |
| Android FCM token endpoint | Android expects `/legacy/api/staff-device-token.php`; file absent | **BLOCKER — TARGETED SOURCE REVIEW REQUIRED** |
| Android update endpoint | Android expects `/legacy/api/staff-app-update.php`; file absent | **BLOCKER — TARGETED SOURCE REVIEW REQUIRED** |
| Job Card Create | form view exists; create/store route not visible | **WIRING PENDING** |
| Native Customer route | native controller/view exist; route not visible | **WIRING PENDING** |


---

## Phase 20 — FIX20 Live Compatibility

| Item | Status | Note |
|---|---|---|
| Targeted live-source review | COMPLETE | Exact Laravel/Legacy source bundle analyzed |
| Native Customer/Master routes | PATCH READY | Controller/views existed; route gap confirmed |
| Job Card Create/Store | PATCH READY | Existing form reused; missing route/controller wiring confirmed |
| Job Card Parts-before-Labour | PATCH READY | Live Job Card view reordered |
| Legacy ROT pause metadata | PATCH READY | Existing legacy endpoint now accepts required reason + optional note |
| Legacy reminder postpone/escalation | PATCH READY | Uses existing `staff_reminder_state`; no parallel tables |
| Staff device-token compatibility | PATCH READY | Uses existing `staff_devices` registry |
| Staff update-feed compatibility | PATCH READY / PUBLICATION DISABLED | update_available remains false without signed URL + SHA |
| Firebase payload compatibility | PATCH READY | Existing outbox payload aligned with Android aliases |
| Database migration | NONE | FIX20 does not create/alter tables |
| Payment Voucher 500 | DIAGNOSTIC READY | Exact exception still must be captured; no guess-based accounting patch |
| Staging applied | NO | Await baseline CHECK + diagnostic output |
| Android PR | #6 DRAFT | Run #116 started |


### FIX20 validation addendum

- package Bash syntax: **PASS**
- patched/new PHP syntax: **PASS**
- uploaded live-source baseline SHA-256 match: **PASS**
- native route-name contract check: **PASS**
- local CHECK → APPLY → ROLLBACK script smoke: **PASS**
- live staging apply: **NOT RUN**


### Phase 20B — Payment Voucher 500 exact diagnosis

- live diagnostic: **CONFIRMED SQLSTATE 42000 / error 1055**
- failing method: `ExpensePaymentService::payableSources()`
- cause: Parts Purchase aggregation rejected by `ONLY_FULL_GROUP_BY`
- fix: derived-table `src_ref` + outer aggregation
- DB SQL mode change: **NO**
- DB migration: **NO**
- revised combined staging package: **FIX20B READY**
- staging apply: **NOT YET RUN**


### Android compatibility build evidence

- Staff App PR #6: **OPEN / DRAFT / MERGEABLE**
- workflow Run #116: **SUCCESS**
- Debug APK build: **PASS**
- Unsigned Release APK build: **PASS**
- APK artifact upload: **PASS**
- merge remains gated on FIX20B staging apply + end-to-end smoke test


### Phase 20C — Hostinger shell compatibility

- FIX20B live CHECK: **GO**
- FIX20B APPLY: **STOPPED BEFORE BACKUP/COPY**
- failure: Hostinger shell does not expose `/dev/fd` for Bash process substitution used by APPLY script
- staging source modification from failed FIX20B apply: **NONE (failure occurred before backup mkdir/copy/install)**
- FIX20C: **READY**
- APPLY/ROLLBACK process substitution: **REMOVED**
- replacement mechanism: temporary file lists under `/tmp`
- Bash syntax: **PASS**
- target PHP lint: **PASS (10 files)**
- FIX20C SHA-256: `43bc2651e987a90270f90a0d61451f2f0a032c2e30dc41f329462df9fe1a41f3`


---

## Phase 20C — LIVE STAGING APPLY SUCCESS (09-Oct-2026)

Evidence from Hostinger staging apply:

- `CHECK_FIX20C_STAGING.sh`: **GO**
- target PHP syntax precheck: **PASS**
- live file backup: **CREATED**
- patched files installed: **10**
- live PHP syntax verification: **PASS**
- route cache clear: **SUCCESS**
- native Customer routes: **PRESENT**
- native Parts/Labour/Vehicle/Mapping routes: **PRESENT**
- Job Card Create/Store routes: **PRESENT**
- post-apply read-only verification: **PASS**
- `ExpensePaymentService::payableSources()`: **PASS (count=10)**
- database migration: **NONE**
- rollback backup: `ASSAM_MOTORS_FIX20C_BACKUP_20261009_042027`

Status: **STAGING CODE APPLY COMPLETE; MANUAL UI/DEVICE SMOKE TEST PENDING.**


### Phase 21 — Three-page browser 500 common root cause

- Payment/Customer/Job Card browser smoke after FIX20C: **FAIL**
- latest common exception: **Route [login] not defined**
- Payment `ONLY_FULL_GROUP_BY` entry in log: **historical; backend probe now passes**
- FIX21 scope: **routes/web.php only**
- database/schema change: **NONE**
- browser PASS criteria: **actual HTTP/browser request after authenticated ERP login**


### Phase 22 — Customer Navigation + Create Job Card UI

Live evidence from 09-Oct-2026:
- ERP sidebar Customer link still points to `/legacy/workshop/customers.php`
- native Customer route `erp.customers` exists
- Job Card index has only `Estimate Register` in page header
- native Job Card Create route `erp.job-cards.create` exists and opens

FIX22 scope:
- `resources/views/layouts/erp.blade.php`: Customer → native `erp.customers`
- `resources/views/job-cards/index.blade.php`: add visible `+ Create Job Card` button
- baseline SHA guards: **ENABLED**
- database migration: **NONE**
- Payment module: **NOT TOUCHED**


### Phase 22B — Hostinger-compatible UI apply

- FIX22 live CHECK: **GO**
- FIX22 APPLY: **STOPPED before file modification**
- failure: `python3: command not found`
- backups created by failed FIX22 attempts: **YES**
- source modification from failed FIX22 apply: **NONE**
- FIX22B patcher runtime: **PHP CLI**
- Python dependency: **REMOVED**
- /dev/fd dependency: **NONE**
- scope remains two Blade files only
- local Bash syntax: **PASS**
- local PHP syntax: **PASS**
- synthetic patch smoke: **PASS**
- FIX22B SHA-256: `8c8615794a0129042a5850400ebcd1328719cea214c6d05754cbe07eaf213317`


### Phase 23 — Native Customer + Vehicle

- request: New Customer ke saath Vehicle add facility, Legacy-like
- existing live tables reused: `am_customer_master`, `am_vehicle_master`, `am_vehicle_catalog_master`
- Customer-only creation: **SUPPORTED**
- Customer + 1–5 Vehicles same transaction: **READY**
- duplicate Registration/Chassis/Engine protection: **READY**
- active Vehicle Master selection validation: **READY**
- database migration: **NONE**
- FIX23 package SHA-256: `44e2df81d9c88a135fa9a6dd17ff89b334901bb3247b930b90a688fc8bfc4163`
- staging apply: **PENDING SERVER CHECK**


### Phase 24 — OSL Purchase = Parts Purchase style

- live single-line OSL flow reviewed: **DONE**
- existing batch-capable schema reused: **YES**
- OSL shared supplier header: **READY**
- multi-line OSL rows: **READY (max 50)**
- per-line Job Card / Vehicle link: **READY**
- per-line OSL Master/manual description: **READY**
- purchase + customer billing preview: **READY**
- transactional OSL batch save: **READY**
- database migration: **NONE**
- PHP syntax: **PASS**
- browser JS syntax: **PASS**
- CHECK/APPLY/VERIFY/ROLLBACK smoke: **PASS**
- staging apply: **PENDING SERVER CHECK**


### Phase 25 — Legacy-style Customer Ledger + Job Card Summary

- Customer Database screenshot/workflow mapped — **DONE**
- Customer full ledger/profile UI — **READY**
- unlimited Customer Vehicles after creation — **READY**
- Vehicle Add/Edit — **READY**
- vehicle-wise service history — **READY**
- Customer Job Card summary — **READY**
- invoice/account ledger summary — **READY**
- Job Card register summary counters/actions — **READY**
- DB migration — **NONE**
- PHP / Blade / Hostinger validation — **PASS**
- staging apply — **PENDING SERVER CHECK**


### Phase 26 — Direct Deploy / Parts Purchase Search

- tar.gz handoff workflow: **REPLACED FOR NEW FIXES**
- direct deployment root: `deployment/live-fixes/`
- FIX26 Part Name/Code search normalization: **READY**
- FIX26 Job Card/Vehicle per-line filter: **READY**
- PHP lint: **PASS**
- JavaScript syntax: **PASS**
- DB migration: **NONE**
- live apply: **PENDING**


### Phase 27 — Purchase / Estimate readability

- same-page controls: **100% / 115% / 125%**
- default: **115%**
- separate large window: **NOT USED**
- browser preference persistence: **READY**
- print/PDF layout scaling: **NOT COUPLED TO VIEW CONTROL**
- DB/save calculations: **UNCHANGED**
- direct deploy path: `deployment/live-fixes/FIX27_PURCHASE_ESTIMATE_READABILITY`
- live apply: **PENDING**
