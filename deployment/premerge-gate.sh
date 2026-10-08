#!/usr/bin/env bash
set -u

# Assam Motors pre-merge repository gate.
# READ ONLY: does not modify Git, DB, caches, workflows, or deployment state.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FAIL=0
WARN=0
PASS=0

ok(){ printf 'PASS  %s\n' "$*"; PASS=$((PASS+1)); }
warn(){ printf 'WARN  %s\n' "$*"; WARN=$((WARN+1)); }
fail(){ printf 'FAIL  %s\n' "$*"; FAIL=$((FAIL+1)); }

required=(
  "RELEASE_WORKSHOP_STACK_20261008.md"
  "docs/DELIVERY_STATUS_20261008.md"
  "docs/MASTER_BACKLOG.md"
  "deployment/STAGING_RUNBOOK.md"
  "deployment/ROLLBACK_MATRIX.md"
  "deployment/PRE_DEPLOY_CHECKLIST.md"
  "deployment/INTEGRATION_MAP.md"
  "deployment/source-discovery.sh"
  "deployment/preflight.sh"
  "deployment/release-manifest.json"
  "deployment/source-map.json"
)

for f in "${required[@]}"; do
  [[ -f "$ROOT/$f" ]] && ok "required file: $f" || fail "missing required file: $f"
done

sql_files=(
  "patches/phase3-job-card-edit/001_job_card_edit_audit.sql"
  "patches/phase4-osl-purchase/001_osl_purchase_line_items.sql"
  "patches/phase5-rot-backend/001_create_rot_event_audit.sql"
  "patches/phase8-alert-compliance/001_staff_action_alerts.sql"
  "patches/phase10-fcm-push/001_staff_push_devices.sql"
)

for f in "${sql_files[@]}"; do
  if [[ ! -f "$ROOT/$f" ]]; then
    fail "missing migration: $f"
    continue
  fi

  if grep -Eiq '^\s*(DROP|ALTER|TRUNCATE|DELETE|UPDATE|REPLACE)\b' "$ROOT/$f"; then
    fail "destructive SQL statement found: $f"
  else
    ok "no destructive SQL statements: $f"
  fi

  if grep -qi 'CREATE TABLE IF NOT EXISTS' "$ROOT/$f"; then
    ok "additive create marker found: $f"
  else
    warn "CREATE TABLE IF NOT EXISTS not found: $f"
  fi
done

if command -v git >/dev/null 2>&1 && git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  suspicious="$(git -C "$ROOT" ls-files | grep -Ei '(^|/)(\.env($|\.)|service[-_]?account.*\.json|.*\.keystore|.*\.jks|id_rsa|.*private.*\.key)$' || true)"
  if [[ -n "$suspicious" ]]; then
    fail "sensitive-looking tracked filenames found"
    printf '%s\n' "$suspicious"
  else
    ok "no obvious secret/keystore filenames tracked"
  fi
else
  warn "git unavailable; tracked filename scan skipped"
fi

if grep -RIlE '-----BEGIN [A-Z ]*PRIVATE KEY-----|AIza[0-9A-Za-z_-]{30,}|eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}'   "$ROOT/patches" "$ROOT/deployment" 2>/dev/null | grep -q .; then
  fail "private-key/API-key/JWT-like content found under patches/deployment"
else
  ok "no private-key/API-key/JWT-like content found under patches/deployment"
fi

if grep -q 'SUPERSEDED AS AN UMBRELLA PATCH' "$ROOT/patches/phase1-native-navigation/README.md"; then
  ok "superseded Phase-1 umbrella patch clearly marked"
else
  warn "Phase-1 native-navigation umbrella not marked superseded"
fi

echo
echo "Summary: PASS=$PASS WARN=$WARN FAIL=$FAIL"
if [[ "$FAIL" -gt 0 ]]; then
  echo "Repository gate: NO-GO"
  exit 2
fi

echo "Repository gate: GO WITH $WARN WARNING(S)"
exit 0
