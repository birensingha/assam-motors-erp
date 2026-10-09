#!/usr/bin/env bash
set -u

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash RUN_ALL_INSPECTORS.sh /path/to/laravel/app"
  exit 2
fi

DIR="$(cd "$(dirname "$0")" && pwd)"
LIVE_FIX_ROOT="$(cd "$DIR/.." && pwd)"
STAMP="$(date +%Y%m%d_%H%M%S)"
REPORT="$DIR/FAST_TRACK_REPORT_$STAMP.txt"

{
  echo "ASSAM MOTORS FAST TRACK REPORT — FIX32 to FIX35"
  echo "Generated: $(date)"
  echo "APP_ROOT: $APP_ROOT"
  echo
  echo "============================================================"
  echo "QUICK INSTALLED MARKERS — FIX26 to FIX31"
  echo "============================================================"

  check_marker() {
    local label="$1"
    local file="$2"
    local marker="$3"
    if [[ -f "$APP_ROOT/$file" ]] && grep -q "$marker" "$APP_ROOT/$file" 2>/dev/null; then
      echo "$label: INSTALLED MARKER FOUND"
    else
      echo "$label: marker not found / not installed"
    fi
  }

  check_marker "FIX26 Purchase Search" "public/js/purchase-entry.js" "wireJobFilter"
  check_marker "FIX27 Purchase Readability" "resources/views/purchases/create.blade.php" "FIX27_READABILITY"
  check_marker "FIX27 Estimate Readability" "resources/views/estimates/create.blade.php" "FIX27_READABILITY"
  check_marker "FIX28 ROT Live Progress" "public/legacy/api/staff-rot-v3.php" "near_standard_limit"
  check_marker "FIX30 Supplementary WIP" "resources/views/job-cards/show.blade.php" "Supplementary Estimate Ready"
  check_marker "FIX31 Job Card Form UI" "resources/views/job-cards/form.blade.php" "FIX31_JOBCARD_FORM_LAYOUT"
  echo

  run_inspector() {
    local label="$1"
    local path="$2"
    echo "============================================================"
    echo "$label"
    echo "============================================================"
    if [[ ! -f "$path" ]]; then
      echo "MISSING INSPECTOR: $path"
      echo
      return
    fi
    bash "$path" "$APP_ROOT"
    local rc=$?
    echo
    echo "$label EXIT CODE: $rc"
    echo
  }

  run_inspector "FIX32 — STAFF REMINDER COMPLIANCE"     "$LIVE_FIX_ROOT/FIX32_REMINDER_COMPLIANCE/INSPECT.sh"

  run_inspector "FIX33 — PART REVERSAL / PARTS-LABOUR UI"     "$LIVE_FIX_ROOT/FIX33_JOB_CARD_PART_REVERSAL_UI/INSPECT.sh"

  run_inspector "FIX34 — JOB CARD LIFECYCLE"     "$LIVE_FIX_ROOT/FIX34_JOBCARD_LIFECYCLE_QC_READY_INVOICE/INSPECT.sh"

  run_inspector "FIX35 — DIRECT PARTS / LABOUR"     "$LIVE_FIX_ROOT/FIX35_JOBCARD_DIRECT_PARTS_LABOUR/INSPECT.sh"

  echo "============================================================"
  echo "FAST TRACK INSPECTION COMPLETE"
  echo "============================================================"
} > "$REPORT" 2>&1

cat "$REPORT"
echo
echo "REPORT SAVED: $REPORT"
echo "No staging source or database changes were made."
