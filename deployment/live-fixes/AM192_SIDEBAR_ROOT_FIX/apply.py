#!/usr/bin/env python3
"""AM192: definitive sidebar reset correction for NEW/STAGING ERP.

From AM191 evidence:
  public/js/erp-pro-ui.js has three nav.scrollTop = 0 resets.
  external AM189/AM190 scroll helper did not reliably load (asset HTTP error).
AM192 eliminates only those three resets and embeds a focused scroll-position
helper directly in the shared Blade layout, avoiding public JS URL dependency.

Safe: check/apply/rollback; backups under storage/app/am-backups; no DB/route
changes; no editing of any ERP menu link, CSS or application workflow.
Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import os,re,shutil,sys,hashlib,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
layout=root/"resources/views/layouts/erp.blade.php"
pro_ui=root/"public/js/erp-pro-ui.js"
helper=package/"am192-sidebar-inline.js"
backup=root/"storage/app/am-backups/AM192_SIDEBAR_ROOT_FIX"
before_layout=backup/"erp.blade.php.before_AM192"
before_pro=backup/"erp-pro-ui.js.before_AM192"
manifest=backup/"installed.sha256"
marker="AM192_SIDEBAR_REAL_CAUSE_FIXED"
read=lambda p:p.read_text(encoding="utf8",errors="surrogateescape")
def hash_file(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()
def atomic(p,src):
    fd,temp=tempfile.mkstemp(prefix=".am192_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:
            f.write(src)
        os.chmod(temp,p.stat().st_mode & 0o777)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)

if not (root/"artisan").is_file():
    raise SystemExit("AM192_STOP_INVALID_STAGING_ROOT")
if not all(x.is_file() for x in (layout,pro_ui,helper)):
    raise SystemExit("AM192_STOP_SHARED_LAYOUT_OR_PRO_UI_OR_PATCH_MISSING")

if mode=="rollback":
    if not all(x.is_file() for x in (before_layout,before_pro,manifest)):
        raise SystemExit("AM192_STOP_ROLLBACK_BACKUP_NOT_FOUND")
    expected=manifest.read_text(encoding="ascii").splitlines()
    if expected != [hash_file(layout),hash_file(pro_ui)]:
        raise SystemExit("AM192_STOP_LIVE_FILES_CHANGED_AFTER_INSTALL")
    atomic(pro_ui,read(before_pro))
    atomic(layout,read(before_layout))
    print("AM192_ROLLBACK_RESTORED_LAYOUT_AND_PRO_UI")
    print("AM192_NO_DB_OR_ROUTE_CHANGES")
    print("AM192_RUN_PHP_ARTISAN_VIEW_CLEAR")
    sys.exit(0)

original_layout=read(layout)
original_pro=read(pro_ui)
if marker in original_layout or "AM192_NO_SCROLL_RESET" in original_pro:
    raise SystemExit("AM192_ALREADY_APPLIED")
if not re.search(r'<aside\b[^>]*\bid\s*=\s*["\x27]erpSidebar["\x27]',original_layout,re.I):
    raise SystemExit("AM192_STOP_EXPECTED_ERP_SIDEBAR_MISSING")
if not re.search(r'<nav\b[^>]*\bclass\s*=\s*["\x27][^"\x27]*\bsidebar-nav\b',original_layout,re.I):
    raise SystemExit("AM192_STOP_EXPECTED_ERP_SIDEBAR_NAV_MISSING")

# Exact installed AM189/AM190 tag, validated to occur precisely once.
old_tag=r'<script\s+defer\s+src="\{\{\s*asset\(\s*[\x27"]js/am189-sidebar-scroll\.js[\x27"]\s*\)\s*\}\}\?v=1902"></script>'
if len(re.findall(old_tag,original_layout,flags=re.I)) != 1:
    raise SystemExit("AM192_STOP_EXPECTED_AM190_SCRIPT_TAG_NOT_FOUND")
if not re.search("AM189_SIDEBAR_SCROLL_PERSIST",original_layout):
    raise SystemExit("AM192_STOP_AM189_INSTALLATION_NOT_CONFIRMED")

# Stop only the three unconditional reset statements observed in AM191.
# Replace with a syntactically valid no-op, preserving surrounding conditions.
reset=r'^(\s*)nav\.scrollTop\s*=\s*0\s*;\s*(?://[^\n]*)?$'
found=re.findall(reset,original_pro,flags=re.M)
if len(found)!=3:
    raise SystemExit("AM192_STOP_UNEXPECTED_SIDEBAR_SCROLL_RESET_COUNT_"+str(len(found)))
patched_pro=re.sub(
    reset,
    lambda m:m.group(1)+"/* AM192_NO_SCROLL_RESET — do not force sidebar top */ void 0;",
    original_pro,flags=re.M
)
if patched_pro.count("AM192_NO_SCROLL_RESET")!=3:
    raise SystemExit("AM192_STOP_JS_RESET_REPLACEMENT_FAILED")

js=read(helper).strip()
required=[
    "document.getElementById(\"erpSidebar\")",
    "nav.sidebar-nav",
    "sessionStorage.setItem",
    "document.addEventListener(\"scroll\"",
    "document.addEventListener(\"pointerdown\"",
    "window.__am192SidebarScrollActive",
    "scrollTop"
]
if not all(x in js for x in required):
    raise SystemExit("AM192_STOP_INLINE_JS_SIGNATURE_MISMATCH")
if "</script" in js.lower():
    raise SystemExit("AM192_STOP_UNSAFE_JS_SCRIPT_TERMINATOR")
replacement=(
    "<!-- "+marker+": inline helper avoids unreachable /js/ asset -->\n"
    "<script>\n"+js+"\n</script>"
)
patched_layout=re.sub(old_tag,lambda m:replacement,original_layout,count=1,flags=re.I)
if patched_layout.count(marker)!=1:
    raise SystemExit("AM192_STOP_INLINE_LAYOUT_INJECTION_FAILED")

# Only two very specific files may change. Fail instead of touching
# arbitrary navigation/layout source or an unexpected script version.
print("AM192_ROOT_CAUSE public/js/erp-pro-ui.js")
print("AM192_FORCED_RESET_COUNT",len(found))
print("AM192_INLINE_TARGET #erpSidebar nav.sidebar-nav")
print("AM192_EXTERNAL_AM190_SCRIPT_REFERENCE_REMOVED True")
print("AM192_DATABASE_CHANGE None")
print("AM192_ROUTE_CHANGE None")
print("AM192_SIDEBAR_MENU_CHANGE None")
print("AM192_LAYOUT_STRUCTURE_CHANGE None")
if mode=="check":
    print("AM192_CHECK_PASS_NO_WRITES")
    sys.exit(0)
if any(x.exists() for x in (before_layout,before_pro,manifest)):
    raise SystemExit("AM192_STOP_BACKUP_ALREADY_EXISTS")
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
shutil.copy2(layout,before_layout)
shutil.copy2(pro_ui,before_pro)
for p in (before_layout,before_pro):os.chmod(p,0o600)
# Atomic snapshots protect rollback against concurrent later changes.
atomic(pro_ui,patched_pro)
atomic(layout,patched_layout)
manifest.write_text(hash_file(layout)+"\n"+hash_file(pro_ui)+"\n",encoding="ascii")
os.chmod(manifest,0o600)
print("AM192_ROOT_CAUSE_FIXED_AND_INLINE_SCROLL_HELPER_INSTALLED")
print("AM192_CLEAR_VIEW_CACHE_AND_BROWSER_HARD_REFRESH")
