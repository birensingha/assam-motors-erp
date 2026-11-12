#!/usr/bin/env python3
"""AM252 — label original vs complaint rework ROT + reject rework re-billing.
check|apply|verify|rollback STAGING_ROOT. UI and controller guards only; no DB writes.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,re,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","verify","rollback"):
    raise SystemExit("USAGE_AM252 check|apply|verify|rollback STAGING_ROOT")
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():raise SystemExit("STOP_AM252_NOT_STAGING")
routes=[
 "resources/views/job-cards/show.blade.php",
 "app/Http/Controllers/Web/AdminRotBillingController.php",
 "app/Http/Controllers/Web/JobCardRotAlterationController.php",
 "app/Http/Controllers/Web/AdminWorkshopControlPageController.php"
]
view,rot_billing,alteration,amounts=routes
badge="resources/views/job-cards/partials/am252-rework-rot-badge.blade.php"
payload=pkg/"files"/badge
backups=root/"storage/app/am-backups"
pointer=backups/"AM252_LAST_SUCCESSFUL_BACKUP.txt"
marker="AM252_REWORK_BILLING_LOCK"
include="@include('job-cards.partials.am252-rework-rot-badge')"
badge_anchor='<span class="am83-source {{ $line->source_estimate_item_id ? \'estimate\' : \'extra\' }}">{{ $line->source_estimate_item_id ? \'FROM APPROVED ESTIMATE\' : \'ADDITIONAL / DIRECT\' }}</span>'
billing_anchor="if(!$line)throw ValidationException::withMessages(['billing_included'=>'Labour line not found on Job Card.']);"
alter_anchor="abort_unless($line,404);"
amounts_anchor="DB::transaction(function()use($job,$kind,$line,$table,$qtyCol,$rateCol,$data,$user){"
def digest(b):return hashlib.sha256(b).hexdigest()
def run(argv,timeout=100):
    return subprocess.run(argv,cwd=root,text=True,capture_output=True,timeout=timeout)
def atomic(file,b):
    file.parent.mkdir(parents=True,exist_ok=True)
    fd,name=tempfile.mkstemp(prefix=".am252_",dir=file.parent)
    try:
        with os.fdopen(fd,"wb") as f:f.write(b)
        os.chmod(name,(file.stat().st_mode & 0o777) if file.exists() else 0o644)
        os.replace(name,file)
    finally:
        if os.path.exists(name):os.unlink(name)
def php_lint(raw,label):
    with tempfile.NamedTemporaryFile("wb",suffix=".php",delete=False) as handle:
        handle.write(raw);p=Path(handle.name)
    try:
        r=run(["php","-l",str(p)],35)
        if r.returncode:raise SystemExit("STOP_AM252_PHP_LINT_"+label+" "+(r.stdout+r.stderr)[-720:])
    finally:p.unlink(missing_ok=True)
def blade_lint(raw,label):
    with tempfile.TemporaryDirectory(prefix="am252_blade_") as d:
        p=Path(d)/"template.blade.php";out=Path(d)/"compiled.php";p.write_bytes(raw)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=run(["php","-r",php,str(p),str(out)],50)
        if r.returncode or not out.is_file():
            raise SystemExit("STOP_AM252_BLADE_COMPILE_"+label+" "+(r.stdout+r.stderr)[-750:])
        php_lint(out.read_bytes(),"COMPILED_"+label)
def verify():
    for name in routes+[badge]:
        if not (root/name).is_file():raise SystemExit("STOP_AM252_VERIFY_MISSING_"+name)
    if (root/view).read_text("utf8","replace").count(include)!=1:
        raise SystemExit("STOP_AM252_JC_BADGE_INCLUDE_MISSING_OR_DUPLICATE")
    if "AM252_REWORK_ROT_IDENTITY_BADGE" not in (root/badge).read_text("utf8","replace"):
        raise SystemExit("STOP_AM252_BADGE_MISSING")
    for rel in (rot_billing,alteration,amounts):
        if (root/rel).read_text("utf8","replace").count(marker)!=1:
            raise SystemExit("STOP_AM252_SERVER_GUARD_MISSING_"+rel)
        php_lint((root/rel).read_bytes(),"VERIFIED_"+Path(rel).stem)
    probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$link=DB::table('am_complaint_rework_links')->where('complaint_id',1)->first();
if(!$link || !$link->rework_rot_session_id)exit(21);
$job=DB::table('job_cards')->where('id',$link->job_card_id)->first();
if(!$job)exit(22);
foreach(['original_rot_session_id','rework_rot_session_id'] as $kind){
 $id=$link->{$kind};$rot=\App\Models\RotSession::find($id);
 $line=DB::table('job_card_labour')->where('job_card_id',$job->id)->where('rot_session_id',$id)->first();
 if(!$rot||!$line)exit(23);
 $h=view('job-cards.partials.am252-rework-rot-badge',compact('job','rot','line'))->render();
 if($kind==='rework_rot_session_id') {
  if(!str_contains($h,'REPEAT COMPLAINT REWORK #1') || !str_contains($h,'INTERNAL NO CUSTOMER CHARGE')
   || (int)$line->billing_included!==0 || abs((float)$line->total)>0.005)exit(25);
 } else if(!str_contains($h,'ORIGINAL WORK'))exit(26);
}
echo 'AM252_JC11_BOTH_ROT_BADGES_RENDER_AND_NO_CHARGE_DB_PASS';"""
    r=run(["php","-r",probe],60)
    if r.returncode or "AM252_JC11_BOTH_ROT_BADGES_RENDER_AND_NO_CHARGE_DB_PASS" not in r.stdout:
        raise SystemExit("STOP_AM252_JC11_RENDER_OR_NO_CHARGE_DB "+(r.stdout+r.stderr)[-900:])
    print("AM252_JC11_BOTH_ROT_BADGES_RENDER_AND_NO_CHARGE_DB_PASS")
    print("AM252_REWORK_BILLING_SERVER_GUARDS_PRESENT")
    print("AM252_STAGING_VERIFIED_READONLY")
if mode=="verify":verify();sys.exit(0)
if mode=="rollback":
    if not pointer.is_file():raise SystemExit("STOP_AM252_NO_BACKUP")
    target=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in target.parents or not target.name.startswith("AM252_IDENTITY_BILLING_"):
        raise SystemExit("STOP_AM252_INVALID_BACKUP_PATH")
    record=json.loads((target/"manifest.json").read_text())
    for rel,hashval in record["installed"].items():
        if not (root/rel).is_file() or digest((root/rel).read_bytes())!=hashval:
            raise SystemExit("STOP_AM252_FILES_MODIFIED_NO_ROLLBACK_"+rel)
    for rel in routes:atomic(root/rel,(target/rel).read_bytes())
    (root/badge).unlink()
    run(["php","artisan","optimize:clear"],80)
    print("AM252_ROLLBACK_SOURCE_ONLY_BUSINESS_DATA_UNCHANGED")
    sys.exit(0)

for rel in routes:
    if not (root/rel).is_file():raise SystemExit("STOP_AM252_REQUIRED_SOURCE_MISSING_"+rel)
if (root/badge).exists():raise SystemExit("STOP_AM252_ALREADY_OR_PARTIALLY_INSTALLED_USE_VERIFY")
if not payload.is_file():raise SystemExit("STOP_AM252_BADGE_PAYLOAD_MISSING")
before={rel:(root/rel).read_bytes() for rel in routes}
text={rel:data.decode("utf8","surrogateescape") for rel,data in before.items()}
for rel,anchor in ((view,badge_anchor),(rot_billing,billing_anchor),(alteration,alter_anchor),(amounts,amounts_anchor)):
    if text[rel].count(anchor)!=1:
        raise SystemExit("STOP_AM252_ANCHOR_CHANGED_"+rel+"_COUNT_"+str(text[rel].count(anchor)))
    if marker in text[rel] or include in text[rel]:
        raise SystemExit("STOP_AM252_ALREADY_INSTALLED_OR_SOURCE_MARKER_"+rel)
billing_guard=r"""
        // AM252_REWORK_BILLING_LOCK: no corrective Labour may become billable.
        if (\Illuminate\Support\Facades\Schema::hasTable('am_complaint_rework_links')
            && !empty($line->rot_session_id)
            && DB::table('am_complaint_rework_links')
                ->where('job_card_id',$job)
                ->where('rework_rot_session_id',(string)$line->rot_session_id)->exists()) {
            throw ValidationException::withMessages([
                'billing_included'=>'Complaint rework is permanently INTERNAL NO CUSTOMER CHARGE. This ROT cannot be made billable. Original work is separate.'
            ]);
        }
"""
alter_guard=r"""
        // AM252_REWORK_BILLING_LOCK: generic ROT alteration cannot reprice or reassign corrective work.
        if (Schema::hasTable('am_complaint_rework_links')
            && !empty($line->rot_session_id)
            && DB::table('am_complaint_rework_links')
                ->where('job_card_id',$job)
                ->where('rework_rot_session_id',(string)$line->rot_session_id)->exists()) {
            throw ValidationException::withMessages([
                'alteration_reason'=>'Complaint rework ROT is INTERNAL NO CHARGE. Generic ROT financial/mechanic alterations are locked; use native work timer and complaint investigation.'
            ]);
        }
"""
amounts_guard=r"""
        // AM252_REWORK_BILLING_LOCK: refuse billing-edit changes to corrective Labour.
        if ($kind==='labour' && Schema::hasTable('am_complaint_rework_links')) {
            $am252Line=DB::table('job_card_labour')->where('job_card_id',$job)
              ->where('id',$line)->lockForUpdate()->first(['rot_session_id']);
            if ($am252Line && !empty($am252Line->rot_session_id)
                && DB::table('am_complaint_rework_links')
                  ->where('job_card_id',$job)
                  ->where('rework_rot_session_id',(string)$am252Line->rot_session_id)->exists()) {
                throw ValidationException::withMessages([
                    'reason'=>'Complaint rework ROT is locked as INTERNAL NO CUSTOMER CHARGE. Repricing this line is not permitted.'
                ]);
            }
        }
"""
patched={
 view:(text[view].replace(badge_anchor,badge_anchor+"\n"+include,1)).encode("utf8","surrogateescape"),
 rot_billing:(text[rot_billing].replace(billing_anchor,billing_anchor+"\n"+billing_guard,1)).encode("utf8","surrogateescape"),
 alteration:(text[alteration].replace(alter_anchor,alter_anchor+"\n"+alter_guard,1)).encode("utf8","surrogateescape"),
 amounts:(text[amounts].replace(amounts_anchor,amounts_anchor+"\n"+amounts_guard,1)).encode("utf8","surrogateescape"),
 badge:payload.read_bytes(),
}
blade_lint(before[view],"ORIGINAL_JOB_CARD")
blade_lint(patched[view],"UPDATED_JOB_CARD")
blade_lint(patched[badge],"NEW_ROT_BADGE")
for rel in (rot_billing,alteration,amounts):
    php_lint(before[rel],"ORIGINAL_"+Path(rel).stem)
    php_lint(patched[rel],"PATCHED_"+Path(rel).stem)
print("AM252_PHP_BLADE_GUARD_PREFLIGHT_PASS")
print("AM252_NO_DB_OR_ROT_MUTATION_ON_DEPLOY")
print("AM252_PREFLIGHT_PASS")
if mode=="check":print("AM252_CHECK_READ_ONLY");sys.exit(0)
backup=backups/("AM252_IDENTITY_BILLING_"+datetime.now().strftime("%Y%m%d_%H%M%S")+"_"+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for rel,data in before.items():
    p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data);os.chmod(p,0o600)
written=[]
try:
    for rel,data in patched.items():
        p=root/rel
        if rel in before and p.read_bytes()!=before[rel]:
            raise RuntimeError("SOURCE_CHANGED_BEFORE_APPLY_"+rel)
        atomic(p,data);written.append(rel)
    for command in (["php","artisan","optimize:clear"],["php","artisan","view:cache"]):
        r=run(command,125)
        if r.returncode:raise RuntimeError("ARTISAN_CACHE_FAILED "+(r.stdout+r.stderr)[-650:])
    verify()
except Exception as exc:
    for rel in written:
        if rel in before:atomic(root/rel,before[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(["php","artisan","optimize:clear"],90)
    raise SystemExit("STOP_AM252_ROLLED_BACK_FILES "+str(exc))
(backup/"manifest.json").write_text(json.dumps({"installed":{rel:digest(v) for rel,v in patched.items()}},indent=2))
os.chmod(backup/"manifest.json",0o600)
pointer.write_text(str(backup)+"\n");os.chmod(pointer,0o600)
print("AM252_ORIGINAL_VS_COMPLAINT_ROT_TAGS_AND_BILLING_LOCK_INSTALLED")
print("AM252_BACKUP",backup)
print("AM252_BROWSER_CHECK_REQUIRED /erp/job-cards/11")
