#!/usr/bin/env python3
"""AM189: Preserve NEW ERP sidebar scroll position after clicking menu items.

Only the shared Blade layout and one NEW public JS file are touched.
No route, CSS, customer/ledger/database, backend or permission changes.
Read-only preflight: python3 apply.py check /path/to/staging
Install:             python3 apply.py apply /path/to/staging
Rollback:            python3 apply.py rollback /path/to/staging
"""
from pathlib import Path
import hashlib,os,re,shutil,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback STAGING_LARAVEL_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
source=Path(__file__).resolve().parent/"am189-sidebar-scroll.js"
layout=root/"resources/views/layouts/erp.blade.php"
asset=root/"public/js/am189-sidebar-scroll.js"
backupdir=root/"storage/app/am-backups/AM189_SIDEBAR_SCROLL_PERSIST"
backup=backupdir/"erp.blade.php.before_AM189"
digest=backupdir/"erp.blade.php.installed.sha256"
marker="AM189_SIDEBAR_SCROLL_PERSIST"
injection="""    {{-- AM189_SIDEBAR_SCROLL_PERSIST: restore ONLY left sidebar scroll position --}}
    <script defer src="{{ asset('js/am189-sidebar-scroll.js') }}?v=1891"></script>
"""
def read(p):return p.read_text(encoding="utf-8",errors="surrogateescape")
def sha(s):return hashlib.sha256(s.encode("utf-8",errors="surrogateescape")).hexdigest()
def atomic(p,contents):
    fd,tmp=tempfile.mkstemp(prefix=".am189_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf-8",errors="surrogateescape") as file:
            file.write(contents)
        os.chmod(tmp,p.stat().st_mode & 0o777 if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if not (root/"artisan").is_file():
    raise SystemExit("AM189_STOP_NOT_LARAVEL_STAGING")
if not layout.is_file():
    raise SystemExit("AM189_STOP_ERP_SHARED_LAYOUT_NOT_FOUND")
if not source.is_file():
    raise SystemExit("AM189_STOP_JS_SOURCE_NOT_FOUND")
if not (root/"public/js").is_dir():
    raise SystemExit("AM189_STOP_PUBLIC_JS_FOLDER_MISSING")
old=read(layout)
if mode=="rollback":
    if not backup.is_file() or not digest.is_file():
        raise SystemExit("AM189_STOP_NO_ROLLBACK_BACKUP")
    if sha(old)!=digest.read_text().strip():
        raise SystemExit("AM189_STOP_SHARED_LAYOUT_CHANGED_SINCE_INSTALL_PRESERVE_NEW_EDITS")
    if marker not in old:
        raise SystemExit("AM189_STOP_NOT_INSTALLED")
    atomic(layout,read(backup))
    if asset.is_file() and read(asset)==read(source):
        asset.unlink()
    print("AM189_ROLLED_BACK_LAYOUT_AND_JS")
    print("AM189_RUN_PHP_ARTISAN_VIEW_CLEAR")
    sys.exit(0)

if marker in old or asset.exists():
    raise SystemExit("AM189_STOP_ALREADY_INSTALLED_OR_JS_CONFLICT")
if len(re.findall(r"</body\s*>",old,re.I))!=1:
    raise SystemExit("AM189_STOP_SHARED_LAYOUT_BODY_TAG_NOT_UNIQUE")
if not re.search(r"(?i)sidebar|side-nav|side_menu|side-menu|navigation|admin.menu",old):
    raise SystemExit("AM189_STOP_SIDEBAR_LAYOUT_SIGNATURE_MISSING")
if len(old)>1400000:
    raise SystemExit("AM189_STOP_LAYOUT_TOO_LARGE")
# Insert only the JS asset before </body>; do not move or modify the menus.
fixed=re.sub(r"</body\s*>",lambda m:injection+m.group(0),old,count=1,flags=re.I)
if fixed.count(marker)!=1:
    raise SystemExit("AM189_STOP_INJECTION_INTEGRITY")
js=read(source)
for expected in ("sessionStorage","scrollTop","findSidebar","pointerdown","requestAnimationFrame"):
    if expected not in js:
        raise SystemExit("AM189_STOP_JS_INTEGRITY_"+expected)
print("AM189_SHARED_LAYOUT",layout.relative_to(root))
print("AM189_LAYOUT_HAS_SIDEBAR_SIGNATURE True")
print("AM189_SCRIPT_ASSET public/js/am189-sidebar-scroll.js")
print("AM189_SCOPE left_sidebar_scroll_only")
print("AM189_DATABASE_CHANGES None")
print("AM189_ROUTES_CHANGED None")
print("AM189_EXISTING_SIDEBAR_LINKS_CHANGED None")
if mode=="check":
    print("AM189_CHECK_PASS_NO_WRITES")
    sys.exit(0)

backupdir.mkdir(parents=True,exist_ok=True)
os.chmod(backupdir,0o700)
if backup.exists() or digest.exists():
    raise SystemExit("AM189_STOP_BACKUP_ALREADY_EXISTS")
shutil.copy2(layout,backup)
os.chmod(backup,0o600)
# Store digest to make rollback safe if a later patch changes the shared layout.
digest.write_text(sha(fixed)+"\n",encoding="ascii")
os.chmod(digest,0o600)
shutil.copy2(source,asset)
atomic(layout,fixed)
print("AM189_SIDEBAR_SCROLL_PRESERVATION_INSTALLED")
print("AM189_CLEAR_VIEWS_AND_HARD_REFRESH")
print("AM189_EXISTING_DATA_ROUTES_STYLES_UNCHANGED")
