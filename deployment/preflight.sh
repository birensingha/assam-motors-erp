#!/usr/bin/env bash
set -u

# Assam Motors staging release preflight.
# READ-ONLY: no migrations, DB writes, cache clears, source changes or publishing.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ROOT="${APP_ROOT:-}"
CHECK_DB="${CHECK_DB:-0}"

PASS=0
WARN=0
FAIL=0

ok(){ printf 'PASS  %s\n' "$*"; PASS=$((PASS+1)); }
warn(){ printf 'WARN  %s\n' "$*"; WARN=$((WARN+1)); }
fail(){ printf 'FAIL  %s\n' "$*"; FAIL=$((FAIL+1)); }

need_file(){
  local p="$1"
  if [[ -f "$ROOT/$p" ]]; then ok "repo file: $p"; else fail "missing repo file: $p"; fi
}

echo "Assam Motors staging preflight (READ ONLY)"
echo "Repository: $ROOT"
echo

need_file "RELEASE_WORKSHOP_STACK_20261008.md"
need_file "docs/DELIVERY_STATUS_20261008.md"
need_file "docs/MASTER_BACKLOG.md"
need_file "deployment/STAGING_RUNBOOK.md"
need_file "deployment/ROLLBACK_MATRIX.md"

MIGRATIONS=(
  "patches/phase3-job-card-edit/001_job_card_edit_audit.sql"
  "patches/phase4-osl-purchase/001_osl_purchase_line_items.sql"
  "patches/phase5-rot-backend/001_create_rot_event_audit.sql"
  "patches/phase8-alert-compliance/001_staff_action_alerts.sql"
  "patches/phase10-fcm-push/001_staff_push_devices.sql"
)

for m in "${MIGRATIONS[@]}"; do
  need_file "$m"
  if [[ -f "$ROOT/$m" ]]; then
    if grep -qi "CREATE TABLE IF NOT EXISTS" "$ROOT/$m"; then
      ok "$m is additive (CREATE TABLE IF NOT EXISTS found)"
    else
      warn "$m needs manual SQL review: additive marker not found"
    fi
  fi
done

need_file "patches/staff-app-distribution/UPDATE_FEED_CONTRACT.md"
need_file "patches/staff-app-distribution/staff_app_update.php.example"

if command -v git >/dev/null 2>&1 && git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  suspicious="$(git -C "$ROOT" ls-files | grep -Ei '(^|/)(service[-_]?account.*\.json|.*\.keystore|.*\.jks|id_rsa|.*private.*\.key)$' || true)"
  if [[ -n "$suspicious" ]]; then
    fail "sensitive-looking tracked filenames detected"
    printf '%s\n' "$suspicious"
  else
    ok "no obvious service-account/keystore/private-key filenames tracked"
  fi
else
  warn "git unavailable or ROOT is not a work tree; tracked-secret filename check skipped"
fi

for cmd in php composer mysql mysqldump; do
  if command -v "$cmd" >/dev/null 2>&1; then
    version="$("$cmd" --version 2>/dev/null | head -1 || true)"
    ok "$cmd available: $version"
  else
    warn "$cmd not available in this shell"
  fi
done

if [[ -n "$APP_ROOT" ]]; then
  if [[ ! -d "$APP_ROOT" ]]; then
    fail "APP_ROOT does not exist: $APP_ROOT"
  else
    ok "APP_ROOT exists: $APP_ROOT"

    [[ -f "$APP_ROOT/artisan" ]] && ok "Laravel artisan found" || fail "artisan missing in APP_ROOT"
    [[ -f "$APP_ROOT/composer.json" ]] && ok "composer.json found" || fail "composer.json missing"
    [[ -f "$APP_ROOT/.env" ]] && ok ".env found" || warn ".env not found in APP_ROOT"

    if [[ -d "$APP_ROOT/storage" ]]; then
      [[ -w "$APP_ROOT/storage" ]] && ok "storage is writable by current shell user" || warn "storage is not writable by current shell user"
    else
      warn "storage directory missing"
    fi

    if command -v php >/dev/null 2>&1 && [[ -f "$APP_ROOT/artisan" ]]; then
      if (cd "$APP_ROOT" && php artisan --version >/dev/null 2>&1); then
        ok "artisan boots"
      else
        fail "artisan failed to boot"
      fi
    fi

    if command -v composer >/dev/null 2>&1 && [[ -f "$APP_ROOT/composer.json" ]]; then
      if (cd "$APP_ROOT" && composer show phpoffice/phpspreadsheet >/dev/null 2>&1); then
        ok "PhpSpreadsheet installed"
      else
        warn "PhpSpreadsheet not detected; keep Excel export disabled until installed"
      fi

      if (cd "$APP_ROOT" && composer show google/auth >/dev/null 2>&1); then
        ok "google/auth installed"
      else
        warn "google/auth not detected; keep FCM HTTP v1 sender disabled until installed"
      fi
    fi
  fi
else
  warn "APP_ROOT not supplied; live Laravel source checks skipped"
fi

if [[ -n "${FIREBASE_PROJECT_ID:-}" ]]; then
  ok "FIREBASE_PROJECT_ID is set"
else
  warn "FIREBASE_PROJECT_ID not set in this shell"
fi

if [[ -n "${GOOGLE_APPLICATION_CREDENTIALS:-}" ]]; then
  if [[ -r "${GOOGLE_APPLICATION_CREDENTIALS}" ]]; then
    ok "GOOGLE_APPLICATION_CREDENTIALS points to a readable file"
  else
    fail "GOOGLE_APPLICATION_CREDENTIALS is set but not readable"
  fi
else
  warn "GOOGLE_APPLICATION_CREDENTIALS not set in this shell"
fi

if [[ "$CHECK_DB" == "1" ]]; then
  missing=0
  for v in DB_HOST DB_DATABASE DB_USERNAME; do
    if [[ -z "${!v:-}" ]]; then
      fail "$v is required when CHECK_DB=1"
      missing=1
    fi
  done

  if ! command -v mysql >/dev/null 2>&1; then
    fail "mysql client required for CHECK_DB=1"
    missing=1
  fi

  if [[ "$missing" == "0" ]]; then
    DB_PORT="${DB_PORT:-3306}"
    export MYSQL_PWD="${DB_PASSWORD:-}"

    MYSQL=(mysql --batch --skip-column-names
      --host="$DB_HOST"
      --port="$DB_PORT"
      --user="$DB_USERNAME"
      "$DB_DATABASE")

    if "${MYSQL[@]}" -e "SELECT 1" >/dev/null 2>&1; then
      ok "read-only DB connection succeeded"

      TABLES=(
        job_card_edit_audit
        osl_purchase_line_items
        rot_event_audit
        staff_action_alerts
        staff_action_alert_events
        staff_push_devices
        staff_push_deliveries
      )

      for t in "${TABLES[@]}"; do
        found="$("${MYSQL[@]}" -e "SHOW TABLES LIKE '$t'" 2>/dev/null || true)"
        if [[ "$found" == "$t" ]]; then
          ok "DB table present: $t"
        else
          warn "DB table absent: $t (expected before migration, or needs apply)"
        fi
      done
    else
      fail "DB connection failed"
    fi

    unset MYSQL_PWD
  fi
else
  warn "CHECK_DB!=1; DB connectivity/table checks skipped"
fi

echo
echo "Summary: PASS=$PASS WARN=$WARN FAIL=$FAIL"

if [[ "$FAIL" -gt 0 ]]; then
  echo "Preflight result: FAIL"
  exit 2
fi

echo "Preflight result: PASS WITH $WARN WARNING(S)"
exit 0
