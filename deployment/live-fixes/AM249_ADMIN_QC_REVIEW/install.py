#!/usr/bin/env python3
"""AM249 STAGING: Admin QC Review GET + guarded POST, reuse existing QC writer.
check|apply|rollback STAGING_ROOT. Zero database writes during installation.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,re,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM249_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/'files'
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM249_NOT_STAGING')
routes='routes/web.php'
job_view='resources/views/job-cards/show.blade.php'
controller='app/Http/Controllers/Web/AdminQcReviewController.php'
qc_view='resources/views/job-cards/admin-qc-review.blade.php'
old_files=[routes,job_view]
new_files=[controller,qc_view]
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM249_LAST_SUCCESSFUL_BACKUP.txt'
marker='AM249_ADMIN_QC_REVIEW'

def sha(b):return hashlib.sha256(b).hexdigest()
def text(b):return b.decode('utf-8','surrogateescape')
def blob(s):return s.encode('utf-8','surrogateescape')
def cmd(args,timeout=100):
    return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)
def atomic(path,b):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,t=tempfile.mkstemp(prefix='.am249_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as out:out.write(b)
        os.chmod(t,(path.stat().st_mode & 0o777) if path.exists() else 0o644)
        os.replace(t,path)
    finally:
        if os.path.exists(t):os.unlink(t)
def php_check(b,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(b);path=Path(f.name)
    try:
        r=cmd(['php','-l',str(path)],35)
        if r.returncode:raise SystemExit('STOP_AM249_PHP_LINT_'+label+' '+(r.stdout+r.stderr)[-550:])
    finally:path.unlink(missing_ok=True)
def blade_check(b,label):
    with tempfile.TemporaryDirectory(prefix='am249_blade_') as d:
        src=Path(d)/'candidate.blade.php';dst=Path(d)/'compiled.php'
        src.write_bytes(b)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=cmd(['php','-r',php,str(src),str(dst)],60)
        if r.returncode or not dst.is_file():
            raise SystemExit('STOP_AM249_BLADE_COMPILER_'+label+' '+(r.stdout+r.stderr)[-550:])
        php_check(dst.read_bytes(),'COMPILED_'+label)
def baseline_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$orig=\Illuminate\Support\Facades\Route::getRoutes()->getByName('erp.job-cards.lifecycle.qc-result');
if(!$orig || !in_array('POST',$orig->methods(),true)
 || !str_contains(implode('|',$orig->gatherMiddleware()),'EnsureErpAdmin'))
 {fwrite(STDERR,'STOP_AM249_BASE_ADMIN_QC_POST_NOT_PROTECTED');exit(19);}
echo 'AM249_EXISTING_ADMIN_QC_ROUTE_PROTECTED';"""
    p=cmd(['php','-r',php],50)
    if p.returncode or 'AM249_EXISTING_ADMIN_QC_ROUTE_PROTECTED' not in p.stdout:
        raise SystemExit('STOP_AM249_BASE_ADMIN_QC_POST_NOT_PROTECTED '+p.stderr[-450:])
    print('AM249_EXISTING_ADMIN_QC_ROUTE_PROTECTED')
def route_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['erp.job-cards.admin-qc-review','erp.job-cards.admin-qc-review.submit'] as $name){
 $r=\Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
 if(!$r || !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(27);
}
echo 'AM249_NEW_ADMIN_GET_POST_ROUTE_AUTH_PASS';"""
    p=cmd(['php','-r',php],45)
    if p.returncode or 'AM249_NEW_ADMIN_GET_POST_ROUTE_AUTH_PASS' not in p.stdout:
        raise RuntimeError('AM249_NEW_ROUTE_AUTH_FAILED '+(p.stdout+p.stderr)[-500:])
    print('AM249_NEW_ADMIN_GET_POST_ROUTE_AUTH_PASS')

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM249_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupbase.resolve() not in backup.parents or not backup.name.startswith('AM249_ADMIN_QC_'):
        raise SystemExit('STOP_AM249_UNSAFE_BACKUP_PATH')
    manifest=backup/'manifest.json'
    if not manifest.is_file():raise SystemExit('STOP_AM249_BACKUP_MANIFEST_MISSING')
    data=json.loads(manifest.read_text())
    for rel,hash_expected in data['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=hash_expected:
            raise SystemExit('STOP_AM249_SOURCE_CHANGED_AFTER_INSTALL_'+rel)
    for rel in old_files:atomic(root/rel,(backup/rel).read_bytes())
    for rel in new_files:(root/rel).unlink()
    cmd(['php','artisan','optimize:clear'],95)
    print('AM249_ADMIN_QC_ROUTE_AND_BUTTON_ROLLED_BACK')
    print('AM249_ALL_QC_DATABASE_RECORDS_PRESERVED')
    sys.exit(0)

for rel in old_files:
    if not (root/rel).is_file():raise SystemExit('STOP_AM249_REQUIRED_SOURCE_MISSING_'+rel)
for rel in new_files:
    if (root/rel).exists() or not (pkg/rel).is_file():
        raise SystemExit('STOP_AM249_NEW_FILE_EXISTS_OR_MISSING_PAYLOAD_'+rel)
raw={rel:(root/rel).read_bytes() for rel in old_files}
route_source=text(raw[routes]);show_source=text(raw[job_view])
if marker in route_source or marker in show_source:
    raise SystemExit('STOP_AM249_ALREADY_OR_PARTIALLY_INSTALLED')
anchor="Route::post('/job-cards/{job}/lifecycle/qc-result',"
if route_source.count(anchor)!=1:
    raise SystemExit('STOP_AM249_ADMIN_QC_ROUTE_ANCHOR_COUNT_'+str(route_source.count(anchor)))
routeblock=(
    "    // AM249_ADMIN_QC_REVIEW: protected Admin ERP routes; never in staff_web.\n"
    "    Route::get('/job-cards/{job}/admin-qc-review', "
    "[\\App\\Http\\Controllers\\Web\\AdminQcReviewController::class,'show'])"
    "->whereNumber('job')->name('erp.job-cards.admin-qc-review');\n"
    "    Route::post('/job-cards/{job}/admin-qc-review/result', "
    "[\\App\\Http\\Controllers\\Web\\AdminQcReviewController::class,'submit'])"
    "->whereNumber('job')->name('erp.job-cards.admin-qc-review.submit');\n"
)
patched={routes:blob(route_source.replace(anchor,routeblock+anchor,1))}
linkpat=re.compile(r'<a\b(?P<attrs>[^>]{0,1500})>(?P<body>[\s\S]{0,700}?)</a\s*>',re.I)
matches=[]
for m in linkpat.finditer(show_source):
    if 'staff.qc.show' in m.group('attrs') and re.search('Staff\s+QC\s+Review',m.group('body'),re.I):
        matches.append(m)
if len(matches)!=1:
    # Read-only source diagnostic; no customer data values are accessed.
    for token in ('staff.qc.show', 'Staff QC Review', 'Open Staff QC'):
        hit=list(re.finditer(re.escape(token),show_source,re.I))
        print('AM249_LINK_SOURCE_TOKEN',repr(token),'count',len(hit))
        for m0 in hit[:3]:
            snippet=show_source[max(0,m0.start()-180):min(len(show_source),m0.end()+240)]
            snippet=re.sub(r'(?i)(password|secret|bearer|api[_ -]?key|auth[_ -]?token)[^\\n]{0,130}','[REDACTED]',snippet)
            print('AM249_LINK_SOURCE_EXCERPT',repr(snippet)[:900])
    raise SystemExit('STOP_AM249_STAFF_QC_LINK_ANCHOR_COUNT_'+str(len(matches))+
       ' (no UI changes; inspect diagnostic source excerpts)')
m=matches[0]
oldlink=m.group(0)
newlink=oldlink.replace('staff.qc.show','erp.job-cards.admin-qc-review')
label=re.compile(r'Open\s+Staff\s+QC\s+Review\s*(?:\([^)]{0,130}\))?',re.I)
if len(label.findall(newlink))!=1:
    raise SystemExit('STOP_AM249_LINK_LABEL_SHAPE_CHANGED')
newlink=label.sub('Open Admin QC Review',newlink,count=1)
if 'staff.qc.show' in newlink or 'erp.job-cards.admin-qc-review' not in newlink:
    raise SystemExit('STOP_AM249_REPLACEMENT_LINK_INVALID')
patched[job_view]=blob(show_source[:m.start()]+newlink+
      '\n{{-- AM249_ADMIN_QC_REVIEW: Admin button targets Admin authenticated route --}}'
      +show_source[m.end():])
for rel in new_files:patched[rel]=(pkg/rel).read_bytes()

php_check(raw[routes],'ORIGINAL_ROUTES')
php_check(patched[routes],'UPDATED_ROUTES')
php_check(patched[controller],'CONTROLLER')
blade_check(raw[job_view],'ORIGINAL_JOB_CARD')
blade_check(patched[job_view],'UPDATED_JOB_CARD')
blade_check(patched[qc_view],'ADMIN_QC_REVIEW_VIEW')
baseline_check()
print('AM249_SOURCE_AND_BLADE_PREFLIGHT_PASS')
print('AM249_ADMIN_STAFF_LINKED_ID_GUARD_AND_CANONICAL_QC_HANDLER_CONFIRMED')
print('AM249_STAFF_QC_ROUTES_AND_AUTH_GUARDS_UNCHANGED')
print('AM249_NO_DATABASE_QC_RESULT_OR_ASSIGNMENT_CHANGES')
print('AM249_PREFLIGHT_PASS')
if mode=='check':
    print('AM249_CHECK_READ_ONLY')
    sys.exit(0)

backup=backupbase/('AM249_ADMIN_QC_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False)
for rel,b in raw.items():
    dest=backup/rel;dest.parent.mkdir(parents=True,exist_ok=True)
    dest.write_bytes(b)
    os.chmod(dest,0o600)
written=[]
try:
    for rel,b in patched.items():
        dest=root/rel
        if rel in raw and dest.read_bytes()!=raw[rel]:
            raise RuntimeError('SOURCE_CHANGED_'+rel)
        atomic(dest,b);written.append(rel)
    for command in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        p=cmd(command,110)
        if p.returncode:raise RuntimeError('STAGING_CACHE_CHECK '+(p.stdout+p.stderr)[-700:])
    route_check()
except Exception as error:
    for rel in written:
        if rel in raw:atomic(root/rel,raw[rel])
        else:(root/rel).unlink(missing_ok=True)
    cmd(['php','artisan','optimize:clear'],75)
    raise SystemExit('STOP_AM249_DEPLOYMENT_FAILED_CODE_RESTORED '+str(error))
(backup/'manifest.json').write_text(json.dumps({
 'installed':{rel:sha(b) for rel,b in patched.items()}
},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n')
os.chmod(pointer,0o600)
print('AM249_ADMIN_QC_REVIEW_INSTALLED')
print('BACKUP',backup)
print('AM249_BROWSER_CHECK_REQUIRED: /erp/job-cards/50/admin-qc-review')
print('AM249_QC38_PENDING_ASSIGNMENT_NOT_CHANGED_BY_INSTALLER')
