#!/usr/bin/env bash
# FIX59 guarded STAGING-only Laravel mobile view installation; no database mutations.
set -euo pipefail
ROOT="$1"
PKG="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$ROOT" && pwd)"
LAYOUT="$ROOT/resources/views/layouts/erp.blade.php"
PARTIAL="$ROOT/resources/views/partials/mobile-erp.blade.php"
STATIC="$ROOT/public/mobile-erp"
test -f "$ROOT/artisan" && test -d "$ROOT/vendor" && test -f "$LAYOUT"
test -s "$PKG/mobile-erp.css" && test -s "$PKG/mobile-erp.js" && test -s "$PKG/mobile-erp.blade.php"
case "$ROOT" in */domains/assammotors.com/assam-erp-staging) ;; *) echo FIX59_WRONG_ROOT_ABORT;exit 11;; esac
if grep -Fq "@include('partials.mobile-erp')" "$LAYOUT" || [ -e "$PARTIAL" ] || [ -e "$STATIC" ]; then
  echo "FIX59_ALREADY_INSTALLED_OR_TARGET_COLLISION_ABORT"
  exit 12
fi
python3 - "$LAYOUT" <<'PY'
import sys
from pathlib import Path
p=Path(sys.argv[1]).read_bytes().decode('utf-8','surrogateescape')
assert p.count('</body>')==1
assert p.count('<meta name="viewport"')==1
assert 'id="erpSidebar"' in p and 'main class="page-shell' in p
assert p.count("@include('partials.mobile-erp')")==0
print("FIX59_LAYOUT_PREFLIGHT_PASS")
PY
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$ROOT/storage/app/fix59_mobile_erp_backup_$STAMP"
mkdir -p "$BACKUP" "$(dirname "$PARTIAL")"
cp -p "$LAYOUT" "$BACKUP/erp.blade.php"
rollback(){
  echo "FIX59_ROLLBACK_SOURCE"
  cp -p "$BACKUP/erp.blade.php" "$LAYOUT"
  rm -f "$PARTIAL"
  rm -rf "$STATIC"
  cd "$ROOT" && php artisan view:clear >/dev/null 2>&1 || true
}
trap rollback ERR
mkdir "$STATIC"
cp -p "$PKG/mobile-erp.css" "$STATIC/mobile-erp.css"
cp -p "$PKG/mobile-erp.js" "$STATIC/mobile-erp.js"
cp -p "$PKG/mobile-erp.blade.php" "$PARTIAL"
python3 - "$LAYOUT" <<'PY'
import sys
from pathlib import Path
p=Path(sys.argv[1])
b=p.read_bytes()
text=b.decode('utf8','surrogateescape')
assert text.count('</body>')==1
text=text.replace('</body>',"    @include('partials.mobile-erp')\n</body>",1)
p.write_bytes(text.encode('utf8','surrogateescape'))
PY
cd "$ROOT"
php artisan view:clear >/dev/null
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$layout=file_get_contents(resource_path("views/layouts/erp.blade.php"));
$partial=file_get_contents(resource_path("views/partials/mobile-erp.blade.php"));
if(substr_count($layout,"@include('\''partials.mobile-erp'\'')")!==1 ||
 !str_contains($partial,"@auth") ||
 !str_contains($partial,"mobile-erp/mobile-erp.css") ||
 !str_contains($partial,"mobile-erp/mobile-erp.js"))exit(30);
foreach(["layouts/erp.blade.php","partials/mobile-erp.blade.php"] as $f){
 $php=app("blade.compiler")->compileString(file_get_contents(resource_path("views/".$f)));
 token_get_all($php,TOKEN_PARSE);
}
echo "FIX59_BLADE_COMPILE_AND_ASSET_ASSERT_PASS\n";
'
test -s "$STATIC/mobile-erp.js" && test -s "$STATIC/mobile-erp.css"
if command -v node >/dev/null 2>&1;then node --check "$STATIC/mobile-erp.js";fi
trap - ERR
echo "FIX59_STAGING_DEPLOY_SUCCESS BACKUP=$BACKUP"
