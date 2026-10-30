#!/usr/bin/env python3
"""AM219: narrow, staging-only presentation fix after AM217/AM218 incident.

Remove ONLY the visual "OSL · PURCHASE ONLY" footer/row from
the customer Labour + OSL Billing Register. Preserve original AM93 Blade
logic, all server-side PHP expressions, underlying supplier purchase,
vendor ledger, customer charge lines and other OSL modals.

This intentionally uses a scoped CSS rule rather than modifying Blade
@foreach/@if blocks, to avoid reintroducing AM217's runtime 500.
"""
import os,sys,shutil,tempfile,subprocess,re
from pathlib import Path

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback STAGING_APP_ROOT")
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
view=root/"resources/views/job-cards/show.blade.php"
backup=root/"storage/app/am-backups/AM219_OSL_BILLING_FOOTER_UI/show.blade.php.before_AM219"
marker="AM219_HIDE_PURCHASE_ONLY_IN_CUSTOMER_BILLING"
anchor='<script id="am93-osl-row-sort">'
css='''{{-- AM219_HIDE_PURCHASE_ONLY_IN_CUSTOMER_BILLING: only a UI row;
    supplier OSL purchases remain in the OSL Purchase Register. --}}
<style id="am219-hide-purchase-only-billing">
/* This vendor-cost reference is not a customer billing line. */
.am93-purchase-pending { display: none !important; }
</style>
'''
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("AM219_STOP_NOT_STAGING_LARAVEL")
source=view.read_text("utf8",errors="surrogateescape")
def atomic_write(path,data):
    fd,tmp=tempfile.mkstemp(dir=path.parent,prefix=".am219_")
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as out:out.write(data)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=="rollback":
    if not backup.is_file() or source.count(marker)!=1:
        raise SystemExit("AM219_STOP_ROLLBACK_SOURCE_OR_BACKUP_CHANGED")
    if not source.replace(css,"",1)==backup.read_text("utf8",errors="surrogateescape"):
        raise SystemExit("AM219_STOP_ROLLBACK_SOURCE_NO_LONGER_EXACT")
    atomic_write(view,backup.read_text("utf8",errors="surrogateescape"))
    print("AM219_SAFE_PREVIOUS_VIEW_RESTORED")
    sys.exit(0)
if marker in source:
    print("AM219_ALREADY_APPLIED")
    sys.exit(0)
for name,needle in [
    ("ROW_CLASS",'class="am93-purchase-pending"'),
    ("PURCHASE_ONLY_BADGE","OSL · PURCHASE ONLY"),
    ("BILLING_PENDING_TEXT","Pending JC customer billing — not included in customer total"),
    ("AM93_ROW_SORT",anchor),
    ("SUPPLIER_REVIEW_LINK","route('erp.fix48.osl-edit',$am93Purchase->id)"),
    ("AM193_DELETE_AUDIT","route('erp.job-cards.delete-unused', $job->id)"),
]:
    count=source.count(needle)
    print("AM219_PREFLIGHT_"+name+"="+("PASS" if count==1 else "FAIL_"+str(count)))
    if count!=1:raise SystemExit("AM219_STOP_SOURCE_DRIFT_"+name)
if "AM217_OSL_UI_CLEANUP" in source:
    raise SystemExit("AM219_STOP_AM217_UNROLLED_BACK")
candidate=source.replace(anchor,css+anchor,1)
if candidate.count(marker)!=1 or candidate.replace(css,"",1)!=source:
    raise SystemExit("AM219_STOP_UNRELATED_CHANGE_DETECTED")
# Compile BOTH Blade variants and assert generated executable PHP remains
# byte-for-byte the same once the added CSS is stripped. This protects
# against inline Blade directive scoping regressions that caused AM217 500.
with tempfile.TemporaryDirectory(prefix="am219_blade_check_") as tmp:
    td=Path(tmp)
    a=td/"before.blade.php";b=td/"candidate.blade.php"
    original_php=td/"before.php";candidate_php=td/"candidate.php"
    a.write_text(source,"utf8",errors="surrogateescape")
    b.write_text(candidate,"utf8",errors="surrogateescape")
    compile_php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for inp,out in [(a,original_php),(b,candidate_php)]:
        r=subprocess.run(["php","-r",compile_php,str(inp),str(out)],cwd=root,
                         capture_output=True,text=True,timeout=70)
        if r.returncode:raise SystemExit("AM219_STOP_BLADE_COMPILE "+r.stderr[-250:])
    orig=original_php.read_text("utf8",errors="surrogateescape")
    compiled=candidate_php.read_text("utf8",errors="surrogateescape")
    if compiled.replace(css,"",1)!=orig:
        # Blade removes the comment, but original code must still match.
        cleaned=re.sub(r"\n?<style id=\"am219-hide-purchase-only-billing\">.*?</style>\n?",
                       "\n",compiled,flags=re.S)
        if cleaned!=orig:
            raise SystemExit("AM219_STOP_COMPILED_PHP_CHANGED_MORE_THAN_CSS")
    lint=subprocess.run(["php","-l",str(candidate_php)],cwd=root,
                        capture_output=True,text=True,timeout=30)
    if lint.returncode:raise SystemExit("AM219_STOP_COMPILED_PHP_LINT "+(lint.stdout+lint.stderr)[-300:])
print("AM219_ONLY_PRESENTATION_CSS_INSERTED=PASS")
print("AM219_ORIGINAL_AM93_BLADE_AND_PHP_CONTROL_FLOW_PRESERVED=PASS")
print("AM219_SUPPLIER_PURCHASE_AND_CUSTOMER_OSL_UNCHANGED=PASS")
print("AM219_CANDIDATE_COMPILED_PHP_SYNTAX=PASS")
if mode=="check":
    print("AM219_PREFLIGHT_PASS_READONLY")
    sys.exit(0)
if backup.exists():
    raise SystemExit("AM219_STOP_EXISTING_BACKUP_REQUIRES_MANUAL_REVIEW")
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,backup)
try:atomic_write(view,candidate)
except BaseException:
    shutil.copy2(backup,view)
    raise
print("AM219_UI_VIEW_STAGING_INSTALLED")
print("AM219_DATABASE_CONTROLLERS_INVOICES_ROUTES_UNCHANGED")
