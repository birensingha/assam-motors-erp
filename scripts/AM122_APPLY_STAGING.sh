#!/usr/bin/env bash
# Assam Motors AM122 — STAGING ONLY: relocate Completed ROT Void next to Time Editor.
# View-only patch. Does not void ROTs, change DB, modify controllers or routes.
set -Eeuo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT"
test -f artisan
BASE="https://raw.githubusercontent.com/birensingha/assam-motors-erp/feature/am122-void-inline-rot-actions-20261024/deployment/live-fixes/AM122_VOID_INLINE_ACTIONS"
TMP=/tmp/assam-am122
mkdir -p "$TMP"
for f in deploy.py toolbar-fragment.blade.php; do
  curl --connect-timeout 15 --max-time 60 -fsSL "$BASE/$f" -o "$TMP/$f"
  test -s "$TMP/$f"
done
python3 -m py_compile "$TMP/deploy.py"
python3 "$TMP/deploy.py" check "$PWD"
python3 "$TMP/deploy.py" apply "$PWD"
echo "AM122_INSTALL_FINISHED_NO_DATABASE_CHANGES"
echo "NEXT: open WIP Job Card with Completed ROT, hard refresh, verify compact Void ROT immediately to right of Edit ROT Start / End / Time."
