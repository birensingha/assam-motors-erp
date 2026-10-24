#!/usr/bin/env bash
# AM123 — Main Job Card unified ROT controls. STAGING ONLY.
# No DB writes, no invoice/estimate modifications, no source deletion.
set -Eeuo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT"
test -f artisan
B122="https://raw.githubusercontent.com/birensingha/assam-motors-erp/feature/am122-void-inline-rot-actions-20261024/scripts/AM122_APPLY_STAGING.sh"
B123="https://raw.githubusercontent.com/birensingha/assam-motors-erp/feature/am123-unified-rot-workspace-20261024/deployment/live-fixes/AM123_UNIFIED_ROT_WORKSPACE"
DEST="/tmp/assam-am123"
mkdir -p "$DEST"
for p in deploy.py unified-correction.blade.php; do
  curl --connect-timeout 15 --max-time 60 -fsSL "$B123/$p" -o "$DEST/$p"
  test -s "$DEST/$p"
done
python3 -m py_compile "$DEST/deploy.py"

# AM122 is the small Void-ROT toolbar requested earlier; install it only
# when absent. It is independently guarded and backup-first.
if ! grep -q 'AM122_VOID_INLINE_ROT_ACTIONS' resources/views/job-cards/partials/completed-rot-void.blade.php; then
  echo "AM123: AM122 inline toolbar absent; applying guarded view-only prerequisite"
  curl --connect-timeout 15 --max-time 60 -fsSL "$B122" -o "$DEST/AM122_APPLY_STAGING.sh"
  bash -n "$DEST/AM122_APPLY_STAGING.sh"
  bash "$DEST/AM122_APPLY_STAGING.sh"
fi

echo "AM123_CHECK_EXISTING_P55_AND_JC_ANCHORS"
python3 "$DEST/deploy.py" check "$PWD"
echo "AM123_INSTALLING_MAIN_JC_UNIFIED_EDITOR"
python3 "$DEST/deploy.py" apply "$PWD"
echo "AM123_STAGING_INSTALL_FINISHED_NO_DB_WRITES"
echo "NEXT: Admin Main JC WIP, hard refresh; verify Full ROT Correction, inline Rate/Discount/GST, Time Editor, Void/Delete, History. Save only on disposable test JC."
echo "QTY_BACKEND_NOT_READY: Main JC currently has no separate labour qty column; must implement with full tax/invoice audit before enabling."
