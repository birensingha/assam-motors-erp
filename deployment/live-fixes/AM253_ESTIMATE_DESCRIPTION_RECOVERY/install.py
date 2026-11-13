#!/usr/bin/env python3
"""AM253 Estimate long description and row recovery. check|prepare|apply|verify|rollback STAGING_ROOT.
Does not create or change saved Estimates. Migration is an explicit separate step after DB backup.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','prepare','apply','verify','rollback'):
    raise SystemExit('AM253_USAGE check|prepare|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM253_NOT_STAGING')
controller='app/Http/Controllers/Web/AdminEstimatePageController.php'
service='app/Services/EstimateService.php'
view='resources/views/estimates/create.blade.php'
asset='public/js/am253-estimate-autosave.js'
migration='database/migrations/2026_11_13_120000_am253_long_estimate_descriptions.php'
allfiles=(controller,service,view)
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM253_LAST_BACKUP.txt'
marker='AM253_ESTIMATE_AUTOSAVE'
insert_js="<script id=\"am253-estimate-autosave\">{!! file_get_contents(public_path('js/am253-estimate-autosave.js')) !!}</script>"
sources={asset:pkg/'files'/asset,migration:pkg/'files'/migration}
for path in [*allfiles]:
    if not (root/path).is_file():raise SystemExit('STOP_AM253_MISSING_SOURCE_'+path)
for path,src in sources.items():
    if not src.is_file():raise SystemExit('STOP_AM253_PACKAGE_MISSING_'+path)

def sha(b):return hashlib.sha256(b).hexdigest()
def run(args,timeout=95):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def write_atomic(p,b):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am253_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def php_lint(raw,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as h:
        h.write(raw);path=Path(h.name)
    try:
        r=run(['php','-l',str(path)],30)
        if r.returncode:raise SystemExit('STOP_AM253_PHP_LINT_'+label+' '+(r.stdout+r.stderr)[-650:])
    finally:path.unlink(missing_ok=True)
def blade_lint(raw,label):
    with tempfile.TemporaryDirectory(prefix='am253_blade_') as d:
        p=Path(d)/'create.blade.php';dest=Path(d)/'compiled.php';p.write_bytes(raw)
        script=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=run(['php','-r',script,str(p),str(dest)],45)
        if r.returncode or not dest.is_file():raise SystemExit('STOP_AM253_BLADE_COMPILE_'+label+' '+(r.stdout+r.stderr)[-600:])
        php_lint(dest.read_bytes(),'COMPILED_'+label)
def db_check(required_type):
    probe=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$t=DB::selectOne("SELECT DATA_TYPE AS dtype,CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_estimate_items' AND COLUMN_NAME='description'");
$j=DB::selectOne("SELECT DATA_TYPE AS dtype FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_card_labour' AND COLUMN_NAME='description'");
if(!$t||!$j)exit(31);
if($argv[1]==='text' && !in_array(strtolower($t->dtype),['text','mediumtext','longtext'],true))exit(32);
if($argv[1]==='text' && !in_array(strtolower($j->dtype),['text','mediumtext','longtext'],true))exit(33);
echo 'AM253_DB_ESTIMATE_AND_JC_DESCRIPTION_SCHEMA_'.$argv[1].'_PASS';"""
    p=run(['php','-r',probe,required_type],35)
    if p.returncode:raise SystemExit('STOP_AM253_DB_SCHEMA_CHECK_'+required_type+' '+(p.stdout+p.stderr)[-440:])
    print(p.stdout.strip())
def replace(s,old,new,label):
    count=s.count(old)
    if count!=1:raise SystemExit('STOP_AM253_SOURCE_ANCHOR_'+label+'_COUNT_'+str(count))
    return s.replace(old,new,1)
def prepare_patch():
    original={rel:(root/rel).read_bytes() for rel in allfiles}
    current={rel:original[rel].decode('utf8','surrogateescape') for rel in allfiles}
    if marker in current[view] or insert_js in current[view]:
        raise SystemExit('STOP_AM253_ALREADY_PATCHED_USE_VERIFY')
    current[controller]=replace(current[controller],
      "'items.*.description'=>['required','string','max:255']",
      "'items.*.description'=>['required','string','max:2000']",
      'CONTROLLER_VALIDATE_2000')
    # Explicit withInput guarantees a failed POST retains all submitted fields.
    current[controller]=replace(current[controller],
      'Validator::make($payload,[',
      '$am253Validator=Validator::make($payload,[',
      'VALIDATOR_VARIABLE')
    current[controller]=replace(current[controller],
      '])->validate();',
      """]); 
        // AM253_PRESERVE_VALIDATION_INPUT: retain all dynamic Estimate lines.
        if ($am253Validator->fails()) {
            return redirect()->back()->withErrors($am253Validator)
                ->withInput($request->except('_token'));
        }""",
      'EXPLICIT_WITH_INPUT')
    current[service]=replace(current[service],
      "mb_substr(trim((string)($row['description'] ?? '')),0,255)",
      "mb_substr(trim((string)($row['description'] ?? '')),0,2000)",
      'SERVICE_NO_255_TRUNCATION')
    current[view]=replace(current[view],
      "$estimateInitialData = old('items', $initial);",
      """// AM253_NORMALISE_OLD_ITEMS: sparse numeric keys JSON-encode as object,
      // which older JS discarded because Array.isArray(initial) was false.
      $am253PostedItems=old('items');
      $estimateInitialData=is_array($am253PostedItems)
          ? array_values($am253PostedItems) : $initial->values()->all();""",
      'SPARSE_POSTED_ROWS')
    current[view]=replace(current[view],
      "<script id=\"fix105-estimate-editor\">{!! file_get_contents(public_path('js/estimate-entry.js')) !!}</script>",
      """<script id="fix105-estimate-editor">{!! file_get_contents(public_path('js/estimate-entry.js')) !!}</script>
{{-- AM253_ESTIMATE_AUTOSAVE: retain rows in current browser tab and permit recovery after reload. --}}
"""+insert_js,
      'INLINE_BROWSER_DRAFT_BACKUP')
    # Preserve selected Kind on a failed validation, not only original estimate.
    for code in ('INITIAL','REVISED','SUPPLEMENTARY'):
        current[view]=replace(current[view],
          "@selected(($estimate->estimate_kind ?? $defaultKind)==='"+code+"')",
          "@selected(old('estimate_kind',$estimate->estimate_kind ?? $defaultKind)==='"+code+"')",
          'PRESERVE_KIND_'+code)
    changed={rel:current[rel].encode('utf8','surrogateescape') for rel in allfiles}
    php_lint(original[controller],'BASE_CONTROLLER');php_lint(changed[controller],'NEW_CONTROLLER')
    php_lint(original[service],'BASE_SERVICE');php_lint(changed[service],'NEW_SERVICE')
    blade_lint(original[view],'BASE_ESTIMATE_BLADE');blade_lint(changed[view],'UPDATED_ESTIMATE_BLADE')
    print('AM253_PHP_BLADE_PREFLIGHT_PASS')
    return original,changed

def verify():
    if not (root/asset).is_file():raise SystemExit('STOP_AM253_AUTOSAVE_ASSET_NOT_INSTALLED')
    for rel in allfiles:
        if not (root/rel).is_file():raise SystemExit('STOP_AM253_VERIFY_FILE_MISSING_'+rel)
    blade=(root/view).read_text('utf8','replace')
    controller_text=(root/controller).read_text('utf8','replace')
    service_text=(root/service).read_text('utf8','replace')
    for val in (insert_js,'array_values($am253PostedItems)','old('):
        if val not in blade:raise SystemExit('STOP_AM253_VERIFY_VIEW_NO_'+val[:22])
    if "items.*.description'=>['required','string','max:2000']" not in controller_text or \
         'withInput($request->except' not in controller_text:
        raise SystemExit('STOP_AM253_CONTROLLER_GUARD_MISSING')
    if "mb_substr(trim((string)($row['description'] ?? '')),0,2000)" not in service_text:
        raise SystemExit('STOP_AM253_SILENT_TRUNCATION_STILL_PRESENT')
    db_check('text')
    # Pure calculation, no business writes. Check chars are saved intact.
    check=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$line=trim(str_repeat('Long service description ',20));$svc=$app->make(\App\Services\EstimateService::class);$out=$svc->calculate([['item_type'=>'LABOUR','description'=>$line,'qty'=>1,'rate'=>0,'discount_percent'=>0,'tax_percent'=>0]]);
if(!isset($out['items'][0]['description'])||$out['items'][0]['description']!==$line)exit(53);
echo 'AM253_LONG_ESTIMATE_DESCRIPTION_CALC_NO_TRUNCATION_PASS';"""
    r=run(['php','-r',check],35)
    if r.returncode:raise SystemExit('STOP_AM253_SERVICE_CALC '+(r.stdout+r.stderr)[-500:])
    print(r.stdout.strip())
    print('AM253_DATA_PRESERVATION_AND_STORAGE_VERIFIED')

if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM253_NO_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupbase.resolve() not in backup.parents or not backup.name.startswith('AM253_LONG_DESCRIPTION_'):
        raise SystemExit('STOP_AM253_BAD_BACKUP')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,h in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=h:
            raise SystemExit('STOP_AM253_SOURCE_CHANGED_REFUSE_ROLLBACK_'+rel)
    for rel in allfiles:write_atomic(root/rel,(backup/rel).read_bytes())
    (root/asset).unlink()
    run(['php','artisan','view:clear'],60)
    print('AM253_SOURCE_RESTORED_DB_TEXT_COLUMNS_INTENTIONALLY_PRESERVED')
    sys.exit(0)

if mode=='prepare':
    if (root/migration).exists():
        if sha((root/migration).read_bytes())!=sha(sources[migration].read_bytes()):
            raise SystemExit('STOP_AM253_MIGRATION_FILE_CONFLICT')
        print('AM253_MIGRATION_ALREADY_PREPARED')
    else:
        php_lint(sources[migration].read_bytes(),'MIGRATION')
        write_atomic(root/migration,sources[migration].read_bytes())
        print('AM253_MIGRATION_PREPARED_FILE_ONLY')
    print('AM253_MIGRATION_NOT_RUN_BACKUP_REQUIRED')
    sys.exit(0)

before,after=prepare_patch()
if mode=='check':
    db_check('original')
    print('AM253_CHECK_READ_ONLY_PREFLIGHT_PASS')
    sys.exit(0)
db_check('text')
if (root/asset).exists():raise SystemExit('STOP_AM253_ASSET_ALREADY_PRESENT')
backup=backupbase/('AM253_LONG_DESCRIPTION_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for rel,data in before.items():
    dest=backup/rel;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(data);os.chmod(dest,0o600)
updated={**after,asset:sources[asset].read_bytes()}
written=[]
try:
    for rel,blob in updated.items():
        if rel in before and (root/rel).read_bytes()!=before[rel]:
            raise RuntimeError('AM253_SOURCE_CHANGED_'+rel)
        write_atomic(root/rel,blob);written.append(rel)
    for argv in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=run(argv,120)
        if r.returncode:raise RuntimeError('AM253_VIEW_CACHE_FAILED '+(r.stdout+r.stderr)[-550:])
    verify()
except Exception as error:
    for rel in written:
        if rel in before:write_atomic(root/rel,before[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','view:clear'],70)
    raise SystemExit('STOP_AM253_APPLY_RESTORED_SOURCE_'+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':{rel:sha(b) for rel,b in updated.items()}},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM253_ESTIMATE_LONG_DESCRIPTION_AND_ROW_RECOVERY_INSTALLED')
print('AM253_SOURCE_BACKUP',backup)
print('AM253_BROWSER_RECOVERY_VALIDATION_TEST_PENDING')
