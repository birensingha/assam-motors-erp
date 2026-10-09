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

Android v6.0.18 release-gate builds **Run #105 PASS**, **Run #106 PASS**, and latest **Run #112 PASS**.

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
- [ ] Verify direct/auto-update delivery path — **ANDROID CHECK UI + SERVER FEED PATCH READY / LIVE VERIFICATION PENDING**
  - Settings has Check for App Update
  - only trusted Assam Motors HTTPS download URLs can open
  - update-feed endpoint contract prepared
  - Android/user/device policy still controls final APK installation

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


## Phase 13 — Staging Deployment / Release Control

- [x] Deployment order/runbook — **READY**
- [x] Rollback matrix — **READY**
- [x] Pre-deploy checklist — **READY**
- [x] Read-only preflight checker — **READY / BASH SYNTAX PASS**
- [x] Machine-readable release manifest — **READY**
- [ ] Run preflight against actual staging APP_ROOT — **PENDING LIVE SOURCE**
- [ ] Run read-only DB inspection against staging — **PENDING DB ACCESS**
- [ ] Execute staging deployment — **PENDING**
- [ ] Record smoke-test results and close rollback window — **PENDING**


## Phase 14 — Source Mapping / Integration Map

- [x] Patch → route/controller/view/API mapping document — **READY**
- [x] Android endpoint → ERP endpoint matrix — **READY**
- [x] DB concept → live-table worksheet — **READY / FILL ON STAGING**
- [x] Route/controller/view worksheet — **READY / FILL ON STAGING**
- [x] Read-only source discovery helper — **READY / BASH SYNTAX PASS**
- [x] Machine-readable source map — **READY**
- [ ] Run source discovery against actual staging app — **PENDING LIVE SOURCE**
- [ ] Replace every TBD/DISCOVER entry with exact live file/class/table — **PENDING LIVE SOURCE**


## Phase 15 — Merge / Rebase Audit

- [x] ERP main vs release divergence check — **BEHIND 0 / NO REBASE REQUIRED**
- [x] Android main vs release divergence check — **BEHIND 0 / NO REBASE REQUIRED**
- [x] ERP consolidated merge strategy — **PR #9 IS INTEGRATION PR**
- [x] Android consolidated merge strategy — **PR #5 IS INTEGRATION PR**
- [x] Stacked feature PR conflict-risk audit — **COMPLETE**
- [x] Android release CI after functional updater/licence changes — **RUN #114 PASS**
- [x] Android audit-doc follow-up CI — **RUN #114 PASS**
- [x] Current consolidated PR mergeability check — **ERP #9 TRUE / ANDROID #5 TRUE**
- [ ] Recheck GitHub mergeability immediately before the actual approved merge — **PENDING MERGE WINDOW**


## Phase 16 — Final Pre-Merge Readiness Gate

- [x] Duplicate/dead patch audit — **PASS**
- [x] Superseded Phase-1 umbrella marked — **PASS**
- [x] Sensitive tracked filename scan — **PASS**
- [x] Targeted secret-content scan — **PASS**
- [x] Migration destructive-statement scan — **PASS**
- [x] Android Run #114 — **PASS**
- [x] ERP/Android behind-main check — **0 / 0**
- [x] Current consolidated PR mergeability — **TRUE / TRUE**
- [x] Final GO/NO-GO report — **READY**
- [x] ERP PR #9 merged into main — **DONE / 3186eaa858a88b9f2fc8a4a1a00ad1276c636c28**
- [x] Android PR #5 merged into main — **DONE / da053a79284c0bd39264af15b022aba5accb7d04**
- [ ] Live staging deployment — **NO-GO UNTIL LIVE SOURCE AVAILABLE**
- [ ] Final production-signed Android release — **NO-GO UNTIL FIREBASE/SERVER GATES PASS**


## Phase 17 — Post-Merge Cleanup / Release Tagging

- [x] ERP consolidated PR #9 merged — **DONE**
- [x] Android consolidated PR #5 merged — **DONE**
- [x] Old ERP stacked PRs #1–#8 closed as superseded — **DONE**
- [x] Old Android stacked PRs #1–#4 closed as superseded — **DONE**
- [x] Feature branches retained for audit/history — **DONE**
- [x] ERP post-merge verification record — **DONE**
- [x] Android post-merge verification record — **DONE**
- [ ] Create ERP Git tag `workshop-stack-2026-10-08` — **CONNECTOR CAPABILITY BLOCKED**
- [ ] Create Android Git tag `v6.0.18` — **CONNECTOR CAPABILITY BLOCKED**
- [ ] Produce/publish final signed Android artifact — **STILL BLOCKED BY FIREBASE/SERVER GATES**


## Phase 18 — Live Staging Source Onboarding

- [x] One-shot staging handoff guide — **READY**
- [x] Sanitized live-source collector — **READY / BASH SYNTAX PASS**
- [x] Laravel DB schema-only probe — **READY / PHP SYNTAX PASS**
- [x] Collector excludes .env and source-code contents — **READY**
- [x] Collector refuses archive on obvious secret patterns — **READY**
- [x] Dependency collection sanitized to package/version maps — **READY**
- [ ] Run collector on actual staging APP_ROOT — **PENDING SERVER SHELL**
- [ ] Upload generated handoff archive — **PENDING**
- [ ] Fill exact live route/controller/view/table Integration Map — **PENDING HANDOFF**
- [ ] Begin controlled staging wiring — **PENDING SOURCE MAP**


## Phase 19 — Live Schema Reconciliation / Targeted Source

- [x] Sanitized staging archive analyzed — **DONE**
- [x] Exact live route/controller/view/model/table inventory — **RECORDED**
- [x] Old reference migration plan reconciled — **DO NOT APPLY PARALLEL TABLES**
- [x] Android/staging endpoint mismatch identified — **2 MISSING LEGACY ENDPOINTS**
- [x] Targeted source collector prepared — **READY**
- [ ] Run targeted source collector on staging — **PENDING SERVER SHELL**
- [ ] Inspect real controller/service/legacy API contents — **PENDING UPLOAD**
- [ ] Implement native Customer route/auth correction — **PENDING SOURCE REVIEW**
- [ ] Implement Job Card Create/store wiring — **PENDING SOURCE REVIEW**
- [ ] Resolve FCM token endpoint compatibility — **BLOCKER**
- [ ] Resolve Staff app update endpoint compatibility — **BLOCKER**


## Phase 20 — FIX20 Live Compatibility Package

- [x] Targeted live source archive analyzed — **DONE**
- [x] Native Customer/Master route gap confirmed — **DONE**
- [x] Job Card Create/Store wiring gap confirmed — **DONE**
- [x] Job Card display Parts-before-Labour patch prepared — **DONE**
- [x] Legacy ROT pause reason/note compatibility patch prepared — **DONE**
- [x] Server-authoritative legacy reminder snooze/escalation compatibility patch prepared — **DONE**
- [x] Legacy Staff device-token endpoint prepared against existing `staff_devices` — **DONE**
- [x] Safe Staff app-update feed endpoint prepared — **DONE**
- [x] Firebase payload alias compatibility patch prepared — **DONE**
- [x] FIX20 CHECK/APPLY/ROLLBACK scripts prepared — **DONE**
- [x] Payment Voucher read-only diagnostic prepared — **DONE**
- [x] FIX20 contains no DB migrations — **VERIFIED**
- [ ] Run FIX20 baseline CHECK on staging — **PENDING SERVER**
- [ ] Run Payment 500 read-only diagnostic — **PENDING SERVER**
- [ ] Apply FIX20 to staging — **WAIT FOR CHECK + DIAGNOSTIC REVIEW**
- [ ] Staging smoke tests — **PENDING APPLY**
- [ ] Android compatibility PR #6 build gate — **RUN #116 IN PROGRESS AT STATUS UPDATE**


## Phase 20B — Payment Voucher 500 exact fix

- [x] Read-only live diagnostic executed — **DONE**
- [x] SQLSTATE 42000 / error 1055 root cause identified — **DONE**
- [x] Safe query-only fix prepared — **DONE**
- [x] SQL mode/schema left unchanged — **CONFIRMED**
- [x] Payment service added to baseline SHA guard — **DONE**
- [x] FIX20B combined package prepared — **READY**
- [ ] Upload FIX20B to staging handoff folder — **PENDING SERVER**
- [ ] Run FIX20B CHECK — **PENDING SERVER**
- [ ] Apply FIX20B — **PENDING CHECK GO**
- [ ] Confirm post-apply payableSources PASS + /erp/payments/create loads — **PENDING APPLY**


## Phase 20C — Hostinger-compatible apply

- [x] FIX20B baseline check on live staging — **GO**
- [x] Hostinger /dev/fd incompatibility identified — **DONE**
- [x] Confirm failure occurred before backup/copy/install — **DONE**
- [x] Remove process substitution from APPLY/ROLLBACK — **DONE**
- [x] Bash syntax validation — **PASS**
- [x] Target PHP lint — **PASS**
- [x] FIX20C archive generated — **READY**
- [ ] Upload FIX20C to staging handoff — **PENDING SERVER**
- [ ] Run FIX20C CHECK — **PENDING SERVER**
- [ ] Run FIX20C APPLY + read-only verify — **PENDING CHECK GO**


## Phase 20C — Live apply result

- [x] FIX20C CHECK on live staging — **GO**
- [x] Backup created — **DONE**
- [x] FIX20C installed on live staging — **DONE**
- [x] Live PHP syntax verification — **PASS**
- [x] Native master routes verified — **PASS**
- [x] Job Card Create/Store routes verified — **PASS**
- [x] Payment Voucher backend probe — **PASS (count=10)**
- [x] No database migration — **CONFIRMED**
- [ ] Browser smoke: Payment Voucher Create — **PENDING USER TEST**
- [ ] Browser smoke: Native Customer page — **PENDING USER TEST**
- [ ] Browser smoke: Job Card Create + Save — **PENDING USER TEST**
- [ ] Browser smoke: Job Card show Parts-before-Labour — **PENDING USER TEST**
- [ ] Android smoke: login/reminder/ROT/FCM/update check — **PENDING DEVICE TEST**
- [ ] Merge Android PR #6 — **WAIT FOR DEVICE/STAGING SMOKE**


## Phase 21 — Auth redirect / browser 500

- [x] Latest live exception captured — **Route [login] not defined**
- [x] Common root cause identified — **Laravel guest redirect route missing**
- [x] FIX21 minimal route-only package prepared — **READY**
- [x] PHP/Bash syntax validation — **PASS**
- [x] No DB migration — **CONFIRMED**
- [ ] Run FIX21 CHECK — **PENDING SERVER**
- [ ] Apply FIX21 — **PENDING CHECK GO**
- [ ] HTTP unauth redirect verification — **PENDING APPLY**
- [ ] Authenticated Payment browser render — **PENDING**
- [ ] Authenticated Customer browser render — **PENDING**
- [ ] Authenticated Job Card Create browser render — **PENDING**


## Phase 22 — Customer Navigation + Job Card Create Button

- [x] Live Customer sidebar legacy link identified — **DONE**
- [x] Live Job Card index missing Create button identified — **DONE**
- [x] Exact live SHA-256 baselines captured — **DONE**
- [x] FIX22 two-file baseline-guarded package prepared — **READY**
- [x] Bash syntax validation — **PASS**
- [x] No DB migration / Payment change — **CONFIRMED**
- [ ] Run FIX22 CHECK on staging — **PENDING SERVER**
- [ ] Apply FIX22 — **PENDING CHECK GO**
- [ ] Browser: sidebar Customer opens native page — **PENDING**
- [ ] Browser: + Create Job Card visible and opens form — **PENDING**


## Phase 22B — Hostinger-compatible Customer/Job Card UI apply

- [x] FIX22 CHECK on live staging — **GO**
- [x] python3 dependency failure identified — **DONE**
- [x] Confirm failed FIX22 stopped before source modification — **DONE**
- [x] Replace Python patcher with PHP CLI patcher — **DONE**
- [x] Bash/PHP syntax validation — **PASS**
- [x] Synthetic Customer + Create-button patch smoke — **PASS**
- [x] FIX22B archive prepared — **READY**
- [ ] Run FIX22B CHECK — **PENDING SERVER**
- [ ] Apply FIX22B — **PENDING CHECK GO**
- [ ] Browser verify Customer native navigation — **PENDING**
- [ ] Browser verify + Create Job Card button — **PENDING**


## Phase 23 — New Customer + Vehicle

- [x] Legacy Customer + Vehicle flow reviewed — **DONE**
- [x] Live Customer/Vehicle schema mapped — **DONE**
- [x] Native Customer form vehicle section prepared — **READY**
- [x] Up to 5 vehicles in one Customer save — **READY**
- [x] Transactional Customer + Vehicle persistence — **READY**
- [x] Duplicate vehicle identity validation — **READY**
- [x] Vehicle catalogue validation — **READY**
- [x] Bash/PHP syntax validation — **PASS**
- [x] No DB migration — **CONFIRMED**
- [ ] Run FIX23 CHECK on staging — **PENDING**
- [ ] Apply FIX23 — **PENDING CHECK GO**
- [ ] Browser create Customer + Vehicle smoke — **PENDING**


## Phase 24 — OSL Purchase Multi-Line

- [x] Live OSL controller/service/view reviewed — **DONE**
- [x] Parts Purchase batch pattern mapped to OSL — **DONE**
- [x] Multi-line OSL UI prepared — **READY**
- [x] OSL batch transaction service prepared — **READY**
- [x] Existing Job Card labour-link rules preserved — **READY**
- [x] Existing purchase batch tables reused — **NO NEW DB TABLE**
- [x] PHP/JS syntax validation — **PASS**
- [x] Deployment + rollback smoke — **PASS**
- [ ] Run FIX24 CHECK on staging — **PENDING**
- [ ] Apply FIX24 — **PENDING CHECK GO**
- [ ] Browser save 2+ OSL lines in one supplier bill — **PENDING**
- [ ] Verify each OSL line linked to correct Job Card — **PENDING**


## Phase 25 — Customer Ledger / Vehicles / Job Card Summary

- [x] Old Customer Database UI mapped from supplied screenshots — **DONE**
- [x] Customer profile + ledger page prepared — **READY**
- [x] Add/Edit Vehicle from Customer Ledger — **READY**
- [x] Unlimited linked vehicles — **READY**
- [x] Vehicle-wise Job Card/service history — **READY**
- [x] Customer invoice/ledger summary — **READY**
- [x] Job Card Summary action/list redesign — **READY**
- [x] PHP/Blade/Hostinger validation — **PASS**
- [x] No DB migration — **CONFIRMED**
- [ ] Run FIX25 CHECK on staging — **PENDING**
- [ ] Apply FIX25 — **PENDING CHECK GO**
- [ ] Browser verify Customer Database/Customer Ledger — **PENDING**
- [ ] Browser verify Add/Edit Vehicle + service history — **PENDING**
- [ ] Browser verify Job Card Summary page/list — **PENDING**


## Phase 26 — Parts Purchase Search / Allocation

- [x] Current live purchase source reviewed — **DONE**
- [x] Part Name / Code normalization patch — **READY**
- [x] Per-line JC / Vehicle / Customer filter — **READY**
- [x] Vehicle format normalization — **READY**
- [x] Existing server save-time Job Card validation preserved — **CONFIRMED**
- [x] Direct GitHub CHECK/APPLY/VERIFY/ROLLBACK workflow — **READY**
- [x] PHP + JavaScript syntax validation — **PASS**
- [x] No DB migration — **CONFIRMED**
- [ ] One-time server GitHub deployment clone — **PENDING USER SERVER**
- [ ] Run FIX26 CHECK/APPLY — **PENDING SERVER**
- [ ] Browser verify Part Name search — **PENDING**
- [ ] Browser verify JC No / Vehicle Reg search — **PENDING**


## Phase 27 — Purchase / Estimate Same-Page Readability

- [x] Purchase 100% / 115% / 125% view controls — **READY**
- [x] Estimate 100% / 115% / 125% view controls — **READY**
- [x] Default comfortable view 115% — **READY**
- [x] Browser preference persistence — **READY**
- [x] Print mode kept independent — **READY**
- [x] No DB/controller/service calculation changes — **CONFIRMED**
- [x] Direct GitHub CHECK/APPLY/VERIFY/ROLLBACK scripts — **READY**
- [ ] Run FIX27 CHECK/APPLY on staging — **PENDING SERVER**
- [ ] Browser verify Purchase readability — **PENDING**
- [ ] Browser verify Estimate readability — **PENDING**


## Phase 28 — Staff ROT Live Progress

- [x] Live legacy ROT endpoint reviewed against Android — **DONE**
- [x] Server standard/remaining/overtime/progress fields — **READY**
- [x] Server target-end / ending-soon flag — **READY**
- [x] Latest pause reason/note exposure — **READY**
- [x] Android Started / Target End display — **READY**
- [x] Android Ending Soon / Overtime notice — **READY**
- [x] Android latest Pause reason/note display — **READY**
- [x] Existing Start/Pause/Resume/Complete rules preserved — **CONFIRMED**
- [x] No DB migration — **CONFIRMED**
- [x] Direct GitHub CHECK/APPLY/VERIFY/ROLLBACK scripts — **READY**
- [x] Android PR #6 updated — **READY**
- [ ] Run FIX28 CHECK/APPLY on staging — **PENDING SERVER**
- [ ] Android Run #117 — **QUEUED**
- [ ] Device smoke: live timer / ending soon / overtime / pause remark — **PENDING**


## Phase 29 — Job Card Supplementary Estimate Shortcut

- [x] Live estimate create/store route already exists — **CONFIRMED**
- [x] Job Card detail missing visible estimate/supplementary action — **CONFIRMED**
- [x] UI-only shortcut patch prepared — **READY**
- [x] No DB / estimate calculation / approval logic changes — **CONFIRMED**
- [x] Direct GitHub CHECK/APPLY/VERIFY/ROLLBACK scripts — **READY**
- [ ] Run FIX29 CHECK/APPLY on staging — **PENDING SERVER**
- [ ] Browser verify Job Card button visible — **PENDING**
- [ ] Browser verify converted estimate opens Supplementary mode — **PENDING**


## Phase 30 — Supplementary Estimate / WIP Diagnosis Deadlock

- [x] Exact live error guards located — **DONE**
- [x] Initial/Revision diagnosis gate retained — **CONFIRMED**
- [x] Supplementary eligibility separated from diagnosis re-completion — **READY**
- [x] Prior SENT TO JOB CARD estimate required — **READY**
- [x] WIP Job Card status preserved during supplementary draft — **READY**
- [x] Existing supplementary DRAFT reopens correctly — **READY**
- [x] Job Card UI shows Supplementary Ready instead of Complete Diagnosis — **READY**
- [x] No DB migration — **CONFIRMED**
- [x] Direct GitHub CHECK/APPLY/VERIFY/ROLLBACK — **READY**
- [ ] Run FIX30 CHECK/APPLY on staging — **PENDING SERVER**
- [ ] Browser verify Supplementary Estimate opens during WIP — **PENDING**
- [ ] Save/send/approve Supplementary without reopening Diagnosis — **PENDING**


## Phase 31 — Job Card Form Layout / Dropdowns

- [x] Live Job Card form reviewed — **DONE**
- [x] Desktop left/right two-column form layout — **READY**
- [x] Mobile single-column fallback — **READY**
- [x] Customer / Vehicle / Technician / Status native dropdown styling — **READY**
- [x] Service Type converted from text input to dropdown — **READY**
- [x] Existing custom Service Type value preservation — **READY**
- [x] Long Complaint / Voice / Diagnosis fields full width — **READY**
- [x] No DB/save/business logic change — **CONFIRMED**
- [x] Direct GitHub CHECK/APPLY/VERIFY/ROLLBACK — **READY**
- [ ] Run FIX31 CHECK/APPLY on staging — **PENDING SERVER**
- [ ] Browser verify Job Card two-column entry UI — **PENDING**
- [ ] Browser verify Service Type dropdown list — **PENDING**


## Phase 32 — Staff Reminder Compliance / 3-Reminder Escalation

- [x] Android reminder contract reviewed — **DONE**
- [x] Android server-global postpone count support — **CONFIRMED**
- [x] Android 1/3 → 2/3 → 3/3 display — **CONFIRMED**
- [x] Android Admin escalation display after limit — **CONFIRMED**
- [x] Live reminder engine read-only inspector — **READY**
- [x] No DB/source change in inspection step — **CONFIRMED**
- [ ] Run FIX32 INSPECT on staging — **PENDING SERVER**
- [ ] Reconcile live snooze/escalation rules — **PENDING INSPECT OUTPUT**
- [ ] Prepare exact FIX32 CHECK/APPLY/VERIFY — **PENDING INSPECT OUTPUT**
- [ ] Device smoke reminder 1/3 → 3/3 → Admin — **PENDING**


## Phase 33 — Job Card Part Stock Reversal + Parts/Labour UI

- [x] User-facing delete-block behavior identified — **ISSUED/FITTED PART REQUIRES STOCK REVERSAL**
- [x] Safety rule defined: no hard delete without stock reversal — **CONFIRMED**
- [x] Read-only live source/schema inspector — **READY**
- [x] Purchase line-item UI selected as visual reference — **CONFIRMED**
- [x] No DB/source modification in inspection step — **CONFIRMED**
- [ ] Run FIX33 INSPECT on staging — **PENDING SERVER**
- [ ] Map exact stock issue/reversal service/table — **PENDING INSPECT OUTPUT**
- [ ] Add transactional Reverse Stock & Remove action — **PENDING INSPECT OUTPUT**
- [ ] Redesign Job Card Parts block — **PENDING INSPECT OUTPUT**
- [ ] Redesign Job Card Labour/ROT block — **PENDING INSPECT OUTPUT**
- [ ] Browser + stock balance smoke test — **PENDING**


## Phase 34 — Job Card Lifecycle: QC → Ready → Close → Invoice

- [x] Required lifecycle confirmed from user workflow — **DONE**
- [x] No direct Work Complete → Invoice jump — **RULE DEFINED**
- [x] Read-only live route/controller/service/schema inspector — **READY**
- [x] Existing Job Card audit table selected for reuse — **CONFIRMED**
- [x] No DB/source modification in inspection step — **CONFIRMED**
- [ ] Run FIX34 INSPECT on staging — **PENDING SERVER**
- [ ] Map exact live QC/Ready/Close/Invoice fields/routes — **PENDING INSPECT OUTPUT**
- [ ] Build controlled lifecycle transitions — **PENDING INSPECT OUTPUT**
- [ ] Enforce Parts/Labour/ROT completion before QC — **PENDING INSPECT OUTPUT**
- [ ] Enforce QC before Ready for Delivery — **PENDING INSPECT OUTPUT**
- [ ] Enforce Close before Invoice conversion — **PENDING INSPECT OUTPUT**
- [ ] Browser end-to-end smoke — **PENDING**
