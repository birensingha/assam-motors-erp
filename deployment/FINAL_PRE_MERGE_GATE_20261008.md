# Assam Motors — Final Pre-Merge Readiness Gate

Date: 08-Oct-2026

Scope:
- ERP consolidated PR #9
- Android consolidated PR #5

This report separates **merge readiness** from **live deployment / production release readiness**.

## Executive verdict

### ERP PR #9 — MERGE GATE

**GO — technically merge-ready as the consolidated reference/integration branch.**

Conditions:
- keep PR #9 as the single ERP integration PR;
- do not merge draft feature PRs #1–#8 independently in parallel without re-audit;
- merging this PR does **not** mean the prepared packages are live on staging.

### Android PR #5 — MERGE GATE

**GO — technically merge-ready as the consolidated v6.0.18 integration branch.**

Evidence:
- branch behind current main: 0;
- latest CI Run #114: PASS;
- Debug APK build: PASS;
- Unsigned Release APK build: PASS;
- artifact preparation/upload: PASS.

Conditions:
- use PR #5 as the Android integration PR;
- do not merge stacked PRs #1–#4 independently in parallel without re-audit.

### Staging deployment

**NO-GO at present.**

Reason:
- actual staging Laravel/PHP source is not present in this repository;
- live route/controller/view/table mapping is still pending.

### Production-signed Android v6.0.18

**NO-GO at present.**

Reason:
- Firebase Android build secrets remain unconfigured;
- server-global Alerts/device-token/FCM sender are not live-verified;
- end-to-end push + polling fallback device test is still pending.

---

## Gate 1 — Branch divergence / mergeability

Latest audit:
- ERP release behind main: **0**
- Android release behind main: **0**
- ERP consolidated PR: mergeable on fresh GitHub check
- Android consolidated PR: mergeable on fresh GitHub check

No rebase is required while behind count remains 0.

Re-check immediately before the actual approved merge because GitHub mergeability can temporarily recalculate after head updates.

---

## Gate 2 — Duplicate / dead patch audit

Prepared patch directories: 17.

Result:
- 16 active package directories are referenced by release/status/integration documentation.
- `patches/phase1-native-navigation/` was an older umbrella package and is now explicitly marked **SUPERSEDED**.
- Its scope is replaced by:
  - `phase1-customer-native-auth`
  - `native-master-pages`
  - `phase1-native-operations`

Decision:
- retain historical folder;
- do not deploy it as a separate implementation layer;
- no active duplicate package blocker remains.

---

## Gate 3 — Secret / sensitive material audit

Filename scan:
- no tracked `.env`
- no `.jks`
- no `.keystore`
- no service-account JSON
- no private-key filename

Targeted content scan of FCM/workflow/update files:
- no private-key block
- no Google API-key pattern
- no JWT/bearer credential
- no hardcoded production password
- no Firebase server key

Production secrets remain references/placeholders only.

Decision: **PASS**.

---

## Gate 4 — Database migration safety

Prepared SQL files:
1. Job Card edit audit
2. OSL purchase lines
3. ROT event audit
4. Staff action alerts/events
5. Staff push devices/deliveries

Statement-level scan:
- DROP: none
- ALTER: none
- TRUNCATE: none
- DELETE: none
- UPDATE statement: none
- REPLACE: none

All prepared migrations use additive `CREATE TABLE IF NOT EXISTS`.

Important:
- this does not prove compatibility with the unknown live staging schema;
- live PK/FK types, charset and time precision must still be checked before applying.

Decision: **PASS for repository merge; live migration application remains gated.**

---

## Gate 5 — Android build/release workflow

Latest completed CI:
- Run #114: PASS
- Java setup: PASS
- Gradle setup: PASS
- Debug APK: PASS
- Unsigned Release APK: PASS
- artifacts: PASS

Production workflow contains:
- four Firebase-value gates;
- fail-fast path;
- existing-signing-key verification flow;
- `apksigner` verification;
- licence file;
- SHA-256 metadata;
- `latest.json`.

Decision: **PASS for merge.**

Production signing remains blocked until external/server gates pass.

---

## Gate 6 — Deployment/document consistency

Ready:
- Delivery Status Register
- Master Backlog
- Staging Runbook
- Rollback Matrix
- Pre-deploy Checklist
- Preflight Checker
- Source Integration Map
- Source Discovery Helper
- Release Manifest
- Merge/Rebase Audit

Stale Phase-15 status has been corrected to:
- Run #114 PASS
- both consolidated PRs mergeable on fresh check

Decision: **PASS**.

---

## Final GO / NO-GO matrix

| Action | Verdict |
|---|---|
| Merge ERP consolidated PR #9 into main | **GO, when owner approves** |
| Merge Android consolidated PR #5 into main | **GO, when owner approves** |
| Merge old stacked feature PRs separately | **NO-GO unless consolidated branches are re-audited** |
| Deploy ERP packages to staging now from this repo alone | **NO-GO** |
| Apply migrations blindly to staging | **NO-GO** |
| Produce final FCM-enabled signed v6.0.18 now | **NO-GO** |
| Publish Staff update feed now | **NO-GO** |
| Run source discovery/preflight when live staging source becomes available | **GO** |

## Before actual merge

Immediately before pressing Merge:
1. verify both branches remain behind main by 0;
2. verify both consolidated PRs report mergeable;
3. verify Android latest CI is still green;
4. confirm no unreviewed new commits were added;
5. keep the PRs Draft until the owner explicitly approves the merge.

No merge is performed by this gate.
