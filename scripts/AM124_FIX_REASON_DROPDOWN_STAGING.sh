#!/usr/bin/env bash
# AM124 STAGING ONLY: fix Alteration Reason dropdown in Admin Full ROT Correction.
# Paste THIS shell script command in Hostinger SSH, not raw CSS.
set -Eeuo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT"
test -f artisan
BASE="https://raw.githubusercontent.com/birensingha/assam-motors-erp/fix/am124-p55-reason-dropdown-modal-20261024/deployment/live-fixes/AM124_P55_REASON_DROPDOWN"
DEST="/tmp/am124-p55-reason"
mkdir -p "$DEST"
for file in deploy.py modal-reason-picker.blade.php; do
  curl --connect-timeout 15 --max-time 60 -fsSL "$BASE/$file" -o "$DEST/$file"
  test -s "$DEST/$file"
done
python3 -m py_compile "$DEST/deploy.py"
echo "AM124_READ_ONLY_PREFLIGHT"
python3 "$DEST/deploy.py" check "$PWD"
echo "AM124_APPLY_STAGING"
python3 "$DEST/deploy.py" apply "$PWD"
echo "AM124_INSTALL_DONE_NO_DATABASE_CHANGES"
echo "NEXT: Hard refresh Main JC, Admin Full ROT Correction, click Alteration Reason. Options should stay above modal backdrop and typing should filter."
