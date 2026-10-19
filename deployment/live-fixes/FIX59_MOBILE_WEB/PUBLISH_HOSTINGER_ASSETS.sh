#!/usr/bin/env bash
# FIX59 publish verified Laravel mobile assets into actual Hostinger STAGING document root.
set -euo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
SITE="$HOME/domains/assammotors.com/public_html/staging"
SOURCE="$ROOT/public/mobile-erp"
TARGET="$SITE/mobile-erp"
test -f "$ROOT/artisan" && test -f "$SITE/index.php" && test -s "$SITE/css/erp.css"
test -s "$SOURCE/mobile-erp.css" && test -s "$SOURCE/mobile-erp.js"
if [ -e "$TARGET" ];then
  if cmp -s "$SOURCE/mobile-erp.css" "$TARGET/mobile-erp.css" &&
     cmp -s "$SOURCE/mobile-erp.js" "$TARGET/mobile-erp.js";then
    echo FIX59_STAGING_STATIC_ASSETS_ALREADY_IDENTICAL
    exit 0
  fi
  echo FIX59_DOCROOT_COLLISION_ABORT
  exit 14
fi
mkdir "$TARGET"
rollback(){
  rm -f "$TARGET/mobile-erp.css" "$TARGET/mobile-erp.js"
  rmdir "$TARGET" 2>/dev/null || true
  echo FIX59_DOCROOT_COPY_ROLLBACK
}
trap rollback ERR
cp -p "$SOURCE/mobile-erp.css" "$SOURCE/mobile-erp.js" "$TARGET/"
cmp "$SOURCE/mobile-erp.css" "$TARGET/mobile-erp.css"
cmp "$SOURCE/mobile-erp.js" "$TARGET/mobile-erp.js"
trap - ERR
echo FIX59_ACTUAL_HOSTINGER_STAGING_STATIC_ASSETS_PUBLISHED
