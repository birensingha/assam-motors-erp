#!/usr/bin/env python3
"""AM195 — Replace the lower legacy 'Delete Empty Job Card' with AM193.

Moves the existing AM193 audited delete section from the TOP of the JC
view to the old Delete Empty Job Card panel position, removing the legacy
panel and its unsafe/redundant second submit form.

No routes, controllers, database data, migrations, permissions, financial
tables or delete logic changed. Fail closed if the live view differs from
the expected AM193/AM194 layout or legacy form cannot be isolated safely.

python3 apply.py check STAGING_ROOT
python3 apply.py apply STAGING_ROOT
python3 apply.py rollback STAGING_ROOT
"""
from pathlib import Path
import os,re,sys,tempfile,hashlib,shutil,subprocess

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
view=root/"resources/views/job-cards/show.blade.php"
controller=root/"app/Http/Controllers/Web/AdminUnusedJobCardDeleteController.php"
routes=root/"routes/web.php"
backupdir=root/"storage/app/am-backups/AM195_SINGLE_JC_DELETE_SECTION"
backup=backupdir/"show.blade.php.before_AM195"
manifest=backupdir/"installed.sha256"
marker="AM195_SINGLE_JC_DELETE_SECTION"
am193="{{-- AM193_UNUSED_JOB_CARD_DELETE_ACTION --}}"

def read(p):
    return p.read_text(encoding="utf8",errors="surrogateescape")
def sha(s):
    return hashlib.sha256(s.encode("utf8",errors="surrogateescape")).hexdigest()
def save_atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix=".am195_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if not (root/"artisan").is_file() or not view.is_file() or not controller.is_file() or not routes.is_file():
    raise SystemExit("AM195_STOP_STAGING_OR_AM193_REQUIRED_FILE_MISSING")
source=read(view)

if mode=="rollback":
    if not backup.is_file() or not manifest.is_file():
        raise SystemExit("AM195_STOP_ROLLBACK_BACKUP_MISSING")
    if sha(source)!=read(manifest).strip():
        raise SystemExit("AM195_STOP_LIVE_VIEW_CHANGED_AFTER_INSTALL_NO_OVERWRITE")
    save_atomic(view,read(backup))
    print("AM195_ROLLBACK_ORIGINAL_VIEW_RESTORED")
    print("AM195_BACKEND_AND_AUDIT_UNCHANGED")
    print("AM195_CLEAR_VIEWS")
    sys.exit(0)

if marker in source:raise SystemExit("AM195_STOP_ALREADY_INSTALLED")
if source.count(am193)!=1:
    raise SystemExit("AM195_STOP_AM193_TOP_DELETE_BLOCK_MISSING_OR_DUPLICATED")
if source.count("Delete Empty Job Card")<1:
    raise SystemExit("AM195_STOP_LEGACY_LOWER_DELETE_PANEL_MISSING")
if "AM194_COMPACT_CUSTOMER_CORRECTION" not in source:
    raise SystemExit("AM195_STOP_AM194_CUSTOMER_CORRECTION_NOT_CONFIRMED")
if "am193_deleted_job_card_audits" not in read(controller):
    raise SystemExit("AM195_STOP_AUDITED_BACKEND_UNEXPECTED")
if "erp.job-cards.delete-unused" not in read(routes):
    raise SystemExit("AM195_STOP_AM193_ROUTE_MISSING")

# 1. Identify the original AM193 section exactly. It starts at the
# unique Blade comment, followed by an outer @if and nested error @if.
# We remove its entire balanced Blade conditional, but keep the block
# identical when moving it to the legacy panel's position.
start=source.index(am193)
blade=re.compile(r"@(if|endif)\b")
depth=0
ending=None
for m in blade.finditer(source,start):
    if m.group(1)=="if": depth+=1
    elif m.group(1)=="endif":
        depth-=1
        if depth==0:
            ending=m.end()
            break
        if depth<0:break
if ending is None or depth!=0:
    raise SystemExit("AM195_STOP_CANNOT_BALANCE_AM193_BLADE_CONDITIONAL")
new_control=source[start:ending]
for field in (am193,"erp.job-cards.delete-unused","Permanently Delete Unstarted Job Card",
              "name=\"reason\"","name=\"confirmation\"","@csrf"):
    if field not in new_control:raise SystemExit("AM195_STOP_AM193_FORM_CONTRACT_CHANGED")
if new_control.count("<form")!=1 or new_control.count("</form>")!=1:
    raise SystemExit("AM195_STOP_AM193_FORM_COUNT_UNEXPECTED")
if len(new_control)>8500:raise SystemExit("AM195_STOP_AM193_BLOCK_TOO_LARGE")
remaining=source[:start]+source[ending:]

# 2. Locate the lower legacy "Delete Empty Job Card" card by its
# visible title AND submit text. Discover the smallest balanced HTML
# div/section/article enclosing the form, with NO unrelated controls.
# This avoids brittle guessed CSS selectors or broad regex deletion.
heading=re.search(r"(?i)(?<![a-z])Delete\s+Empty\s+Job\s+Card(?![a-z])",remaining)
submit=re.search(r"(?i)Permanently\s+Delete\s+Empty\s+Job\s+Card",remaining)
if not heading or not submit:
    raise SystemExit("AM195_STOP_LEGACY_FORM_HEADING_OR_SUBMIT_NOT_FOUND")
if abs(heading.start()-submit.start())>10000:
    raise SystemExit("AM195_STOP_LEGACY_FORM_ANCHORS_TOO_FAR_APART")
htmltags=re.compile(r"<\s*(/?)\s*(div|section|article)\b[^>]*>",re.I|re.S)
stack=[]
intervals=[]
for m in htmltags.finditer(remaining):
    close=bool(m.group(1))
    name=m.group(2).lower()
    if not close:
        stack.append((name,m.start()))
        continue
    idx=next((i for i in range(len(stack)-1,-1,-1) if stack[i][0]==name),None)
    if idx is None:continue
    opener=stack[idx][1]
    intervals.append((opener,m.end(),name))
    del stack[idx:]

possible=[]
for a,b,name in intervals:
    if not(a<=heading.start()<b and a<=submit.start()<b):continue
    chunk=remaining[a:b]
    if len(chunk)>11000:continue
    if len(re.findall(r"<form\b",chunk,re.I))!=1:continue
    if len(re.findall(r"</form\s*>",chunk,re.I))!=1:continue
    if "erp.job-cards.delete-unused" in chunk:continue
    if len(re.findall(r"(?i)Permanently\s+Delete\s+Empty\s+Job\s+Card",chunk))!=1:continue
    if len(re.findall(r"(?i)Delete\s+Empty\s+Job\s+Card",chunk))<2:continue
    if any(x in chunk for x in ("Job Card Summary","Credit Note","Estimate/ Reverse","am194-open","am75-quick-actions")):continue
    possible.append((b-a,a,b,name))
if not possible:
    raise SystemExit("AM195_STOP_CANNOT_ISOLATE_LEGACY_DELETE_CARD_NO_FILES_CHANGED")
_,a,b,wrapper=min(possible)
print("AM195_LEGACY_WRAPPER",wrapper,"START_LINE",remaining[:a].count("\n")+1,
      "END_LINE",remaining[:b].count("\n")+1)

# 3. The legacy card is often wrapped in a restrictive @if that only
# allows *zero* inspection/other linked rows. AM193 permits an already
# completed inspection if no real work was started, so NEVER place
# the new control inside such an old conditional.
# Expand removal to the surrounding @if/@endif only if it encloses
# nothing other than the old card and whitespace.
condition_stack=[]
condition_intervals=[]
for m in blade.finditer(remaining):
    if m.group(1)=="if":
        condition_stack.append(m.start())
    elif condition_stack:
        p=condition_stack.pop()
        condition_intervals.append((p,m.end(),m.start()))
containing=sorted((x for x in condition_intervals if x[0]<a and b<x[1]),key=lambda x:x[1]-x[0])
replace_from,replace_to=a,b
position_mode="LEGACY_PANEL_REPLACED"
for cond_begin,cond_end,endif_at in containing:
    prefix=remaining[cond_begin:a]
    suffix=remaining[b:endif_at]
    # Match one explicit if(...) header, allowing nested parentheses;
    # never erase surrounding unrelated UI or other Blade branches.
    if prefix.lstrip().startswith("@if") and "<" not in prefix and "@" not in prefix[3:]:
        # Prefix can include nested function parentheses; it must
        # terminate with a close parenthesis before the card begins.
        if prefix.rstrip().endswith(")") and not suffix.strip():
            replace_from,replace_to=cond_begin,cond_end
            position_mode="LEGACY_CARD_AND_RESTRICTIVE_IF_REPLACED"
            break

# If old panel is nested in a conditional with other content, do NOT
# accidentally gate AM193 or destroy adjacent workflow; place the
# new AM193 block after that conditional, preserving all other content.
if replace_from==a and containing:
    doc=remaining[:a]+remaining[b:]
    place=containing[0][1]-(b-a)
    inserted=doc[:place]+"\n{{-- "+marker+" --}}\n"+new_control+"\n"+doc[place:]
    position_mode="LOWER_AFTER_RESTRICTIVE_CONDITION"
else:
    inserted=remaining[:replace_from]+"\n{{-- "+marker+" --}}\n"+new_control+"\n"+remaining[replace_to:]

if inserted.count(marker)!=1 or inserted.count(am193)!=1:
    raise SystemExit("AM195_STOP_RESULT_MARKERS_INVALID")
if inserted.count("route('erp.job-cards.delete-unused'")!=1:
    raise SystemExit("AM195_STOP_AUDITED_DELETE_FORM_NOT_UNIQUE")
if "Delete Empty Job Card" in inserted or "Permanently Delete Empty Job Card" in inserted:
    raise SystemExit("AM195_STOP_LEGACY_DELETE_FORM_REMAINS")
if inserted.count("Permanently Delete Unstarted Job Card")!=1:
    raise SystemExit("AM195_STOP_NEW_DELETE_FORM_NOT_UNIQUE")
if inserted.find(am193)<source.find(am193):
    raise SystemExit("AM195_STOP_NEW_FORM_NOT_MOVED_DOWN")
for keep in ("AM194_COMPACT_CUSTOMER_CORRECTION",
             "FIX75_JC_THREE_ACTION_BAR",
             "AM193_UNUSED_JOB_CARD_DELETE_ACTION",
             "route('erp.job-cards.customer-name-sync'",
             "route('erp.job-cards.credit-correction'"):
    if keep in source and keep not in inserted:
        raise SystemExit("AM195_STOP_UNRELATED_WORKFLOW_LOST_"+keep)

# Check full Blade output against actual installed Laravel compiler.
with tempfile.TemporaryDirectory(prefix="am195check_") as d:
    tmp=Path(d)
    candidate=tmp/"job-show.blade.php"
    compiled=tmp/"compiled.php"
    validator=tmp/"compile.php"
    candidate.write_text(inserted,encoding="utf8",errors="surrogateescape")
    validator.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding="utf8")
    run=subprocess.run(["php",str(validator),str(root),str(candidate),str(compiled)],
                       capture_output=True,text=True,timeout=35)
    if run.returncode or not compiled.exists():
        raise SystemExit("AM195_STOP_BLADE_COMPILE "+run.stderr[-500:])
    lint=subprocess.run(["php","-l",str(compiled)],
                        capture_output=True,text=True,timeout=30)
    if lint.returncode:
        raise SystemExit("AM195_STOP_BLADE_PHP_LINT "+(lint.stdout+lint.stderr)[-600:])

print("AM195_PLACEMENT",position_mode)
print("AM195_OLD_LOWER_EMPTY_ONLY_FORM_REMOVED True")
print("AM195_UPPER_AM193_FORM_MOVED_TO_LOWER_LOCATION True")
print("AM195_ONLY_ONE_AUDITED_DELETE_FORM True")
print("AM195_INSPECTION_COMPLETED_ELIGIBILITY_UNCHANGED True")
print("AM195_ADMIN_FINANCIAL_LOCKS_AUDITS_UNCHANGED True")
print("AM195_VIEW_COMPILE_PHP_LINT_PASS")
print("AM195_BACKEND_ROUTES_DB_CHANGES None")
if mode=="check":
    print("AM195_CHECK_PASS_NO_WRITES")
    sys.exit(0)

if backup.exists() or manifest.exists():
    raise SystemExit("AM195_STOP_BACKUP_ALREADY_EXISTS")
backupdir.mkdir(parents=True,exist_ok=True)
os.chmod(backupdir,0o700)
shutil.copy2(view,backup)
os.chmod(backup,0o600)
save_atomic(view,inserted)
manifest.write_text(sha(inserted)+"\n",encoding="ascii")
os.chmod(manifest,0o600)
print("AM195_SINGLE_JOBCARD_DELETE_PANEL_INSTALLED")
print("AM195_NEXT_PHP_ARTISAN_VIEW_CLEAR")
