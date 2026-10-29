#!/usr/bin/env python3
"""AM190: Fix live STAGING sidebar scroll reset after AM189 installation.

Replaces only the AM189 sidebar JS asset, and bumps its Blade layout cache-
busting version. Backup/rollback are guarded. No database/routes/ERP links.
Usage: python3 apply.py check|apply|rollback /path/to/staging
"""
from pathlib import Path
import hashlib,os,re,shutil,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
source=pkg/"am190-sidebar-scroll.js"
layout=root/"resources/views/layouts/erp.blade.php"
asset=root/"public/js/am189-sidebar-scroll.js"
backups=root/"storage/app/am-backups/AM190_SIDEBAR_SCROLL_FIX"
layout_backup=backups/"erp.blade.php.before_AM190"
asset_backup=backups/"am189-sidebar-scroll.js.before_AM190"
layout_hash=backups/"installed-layout.sha256"
asset_hash=backups/"installed-asset.sha256"
old_version="""<script defer src="{{ asset('js/am189-sidebar-scroll.js') }}?v=1891"></script>"""
new_version="""<script defer src="{{ asset('js/am189-sidebar-scroll.js') }}?v=1902"></script>"""
marker="AM189_SIDEBAR_SCROLL_PERSIST"
read=lambda p:p.read_text(encoding="utf8",errors="surrogateescape")
def sha(s):return hashlib.sha256(s.encode("utf8",errors="surrogateescape")).hexdigest()
def atomic(p,s):
    fd,temp=tempfile.mkstemp(prefix=".am190_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as file:
            file.write(s)
        os.chmod(temp,p.stat().st_mode&0o777 if p.exists() else 0o644)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
if not (root/"artisan").is_file():raise SystemExit("AM190_STOP_NOT_STAGING_ROOT")
if not layout.is_file() or not asset.is_file() or not source.is_file():
    raise SystemExit("AM190_STOP_LAYOUT_OR_SCRIPT_MISSING")
old_layout=read(layout)
old_asset=read(asset)

if mode=="rollback":
    for p in [layout_backup,asset_backup,layout_hash,asset_hash]:
        if not p.is_file():raise SystemExit("AM190_STOP_BACKUP_MISSING")
    if sha(old_layout)!=read(layout_hash).strip() or sha(old_asset)!=read(asset_hash).strip():
        raise SystemExit("AM190_STOP_LIVE_FILES_CHANGED_AFTER_APPLY")
    atomic(layout,read(layout_backup))
    atomic(asset,read(asset_backup))
    print("AM190_ROLLBACK_RESTORED_LAYOUT_AND_SCRIPT")
    print("AM190_CLEAR_BLADE_VIEW_CACHE")
    sys.exit(0)

if marker not in old_layout:
    raise SystemExit("AM190_STOP_AM189_NOT_INSTALLED")
if old_layout.count(old_version)!=1:
    raise SystemExit("AM190_STOP_AM189_SCRIPT_REFERENCE_UNEXPECTED")
if "window.__am189SidebarScrollLoaded = true" not in old_asset:
    raise SystemExit("AM190_STOP_OLD_SCRIPT_UNEXPECTED")
if 'window.__am190SidebarScrollLoaded = true' in old_asset:
    raise SystemExit("AM190_ALREADY_INSTALLED")
new_asset=read(source)
for token in [
    "am-staging-sidebar-scroll-am189",
    "document.addEventListener(\"scroll\"",
    "document.addEventListener(\"pointerdown\"",
    "window.addEventListener(\"pagehide\"",
    "sessionStorage.setItem",
    "scan(true)", "scrollTop", "getBoundingClientRect"
]:
    if token not in new_asset:
        raise SystemExit("AM190_STOP_SCRIPT_VALIDATION_FAILED")
if new_asset.count("window.__am190SidebarScrollLoaded = true")!=1:
    raise SystemExit("AM190_STOP_SCRIPT_GUARD_INVALID")
new_layout=old_layout.replace(old_version,new_version,1)
if new_layout.count("?v=1902")!=1:
    raise SystemExit("AM190_STOP_CACHE_BUST_INJECTION_FAILED")
print("AM190_AM189_INSTALLATION_CONFIRMED")
print("AM190_ACTUAL_SCROLL_ELEMENT_CAPTURE_ENABLED")
print("AM190_RESTORE_SURVIVES_PAGE_NAVIGATION")
print("AM190_JS_BUNDLE_VERSION_UPDATED 1902")
print("AM190_NO_CSS_ROUTES_DATABASE_OR_MENU_CHANGE")
if mode=="check":
    print("AM190_CHECK_PASS_NO_WRITES")
    sys.exit(0)
if any(p.exists() for p in [layout_backup,asset_backup,layout_hash,asset_hash]):
    raise SystemExit("AM190_STOP_BACKUP_ALREADY_EXISTS")
backups.mkdir(parents=True,exist_ok=True)
os.chmod(backups,0o700)
shutil.copy2(layout,layout_backup)
shutil.copy2(asset,asset_backup)
for p in [layout_backup,asset_backup]:os.chmod(p,0o600)
layout_hash.write_text(sha(new_layout)+"\n",encoding="ascii")
asset_hash.write_text(sha(new_asset)+"\n",encoding="ascii")
for p in [layout_hash,asset_hash]:os.chmod(p,0o600)
atomic(asset,new_asset)
atomic(layout,new_layout)
print("AM190_SIDEBAR_SCROLL_FIX_INSTALLED")
print("AM190_VIEW_CLEAR_AND_BROWSER_HARD_REFRESH_REQUIRED")
