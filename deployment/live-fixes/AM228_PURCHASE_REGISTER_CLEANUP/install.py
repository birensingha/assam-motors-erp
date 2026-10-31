#!/usr/bin/env python3
"""AM228 STAGING Purchase Register cleanup. check|apply|rollback STAGING_ROOT.
Remove only the separate Direct Parts Sale Invoice CTA and All Types selector.
No route/controller/database changes; auditable backup and rollback.
"""
from pathlib import Path
import sys,os,hashlib,subprocess,tempfile,json
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('AM228_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM228_STOP_NOT_STAGING')
view=root/'resources/views/purchases/index.blade.php'
backup=root/'storage/app/am-backups/AM228_PURCHASE_REGISTER_CLEANUP'
original_file=backup/'index.blade.php.before'
manifest=backup/'checksums.json'
marker='AM228_PURCHASE_REGISTER_CLEANUP'
def read(path):return path.read_text('utf8',errors='surrogateescape')
def digest(data):return hashlib.sha256(data.encode('utf8','surrogateescape')).hexdigest()
def atomic(path,data):
 fd,temp=tempfile.mkstemp(prefix='.am228_',dir=path.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(data)
  os.chmod(temp,path.stat().st_mode&0o777)
  os.replace(temp,path)
 finally:
  if os.path.exists(temp):os.unlink(temp)
if not view.is_file() or view.is_symlink():raise SystemExit('AM228_STOP_REGISTER_VIEW_MISSING')
raw=read(view)
if mode=='rollback':
 if not original_file.is_file() or not manifest.is_file():raise SystemExit('AM228_STOP_NO_BACKUP')
 if digest(raw)!=json.loads(read(manifest))['after']:raise SystemExit('AM228_STOP_VIEW_CHANGED_SINCE_DEPLOY')
 atomic(view,read(original_file))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM228_PURCHASE_REGISTER_ROLLBACK_COMPLETE')
 sys.exit(0)
if marker in raw:
 if raw.count(marker)==1 and 'Direct Parts Sale Invoice' not in raw and 'All Types' not in raw:
  print('AM228_ALREADY_INSTALLED')
  sys.exit(0)
 raise SystemExit('AM228_STOP_PARTIAL_DEPLOY')
button_lines=[
 "{{-- P31_COUNTER_PARTS_INVOICE: direct Parts Sale without any Job Card. --}}",
 '<div style="display:flex;justify-content:flex-end;gap:9px;margin:10px 0 12px">',
 """<a href="{{ route('erp.counter-sales.create') }}" class="btn primary" style="background:#10835e;color:#fff;padding:11px 16px;border-radius:9px;font-weight:800">🧾 + Direct Parts Sale Invoice</a>""",
 '</div>',
]
original_lines=raw.splitlines(keepends=True)
matched=[i for i in range(len(original_lines)-3)
 if [line.strip() for line in original_lines[i:i+4]]==button_lines]
if len(matched)!=1:raise SystemExit('AM228_STOP_DIRECT_SALE_BUTTON_BLOCK_'+str(len(matched)))
button_index=matched[0]

select="""<select name="type"><option value="">All Types</option><option value="PART" @selected($filters['type']==='PART')>PART</option><option value="OSL" @selected($filters['type']==='OSL')>OSL</option></select>"""
style=".p18-purchase-register .p18-filter select[name=\"type\"]{flex:0 1 175px;min-width:140px}"
mobile=".p18-purchase-register .p18-filter select[name=\"type\"]{flex:1 1 140px}"
# Exact strong source anchors to prevent any edits on an unexpected staging version.
for label,anchor in [('ALL_TYPES_FILTER',select),('TYPE_STYLE',style),('MOBILE_TYPE_STYLE',mobile)]:
 if raw.count(anchor)!=1:
  raise SystemExit('AM228_STOP_ANCHOR_'+label+'_COUNT_'+str(raw.count(anchor)))
for necessary in ['<h1>Purchase Register</h1>',"route('erp.purchases.osl.create')",
                  "route('erp.purchases.create')",'<input name="q"', '>Search</button>',
                  "route('erp.purchases.index')",'<table class="data-table">']:
 if necessary not in raw:raise SystemExit('AM228_STOP_REQUIRED_REGISTER_CONTROL_MISSING')
newline='\r\n' if original_lines[button_index].endswith('\r\n') else '\n'
replacement='{{-- AM228_PURCHASE_REGISTER_CLEANUP: Direct Parts Sale CTA removed from Purchase Register only. --}}'+newline
new=''.join(original_lines[:button_index]+[replacement]+original_lines[button_index+4:])
new=new.replace(select,'',1).replace(style,'',1).replace(mobile,'',1)
# No re-routing/deletion of the counter-sale workflow elsewhere.
if new.count(marker)!=1 or 'Direct Parts Sale Invoice' in new or 'All Types' in new:
 raise SystemExit('AM228_STOP_CANDIDATE_INVARIANT')
# Validate original live view before writing it, including Blade compilation.
with tempfile.TemporaryDirectory(prefix='am228_qa_') as tmp:
 temp=Path(tmp)
 v=temp/'index.blade.php';compiled=temp/'compiled.php';helper=temp/'compile.php'
 v.write_text(new,encoding='utf8',errors='surrogateescape')
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 proc=subprocess.run(['php',str(helper),str(root),str(v),str(compiled)],
   cwd=root,text=True,capture_output=True,timeout=55)
 if proc.returncode:raise SystemExit('AM228_STOP_BLADE_COMPILE '+proc.stderr[-400:])
 proc=subprocess.run(['php','-l',str(compiled)],cwd=root,text=True,capture_output=True,timeout=30)
 if proc.returncode:raise SystemExit('AM228_STOP_COMPILED_PHP_LINT '+(proc.stdout+proc.stderr)[-400:])
 if marker not in read(compiled):raise SystemExit('AM228_STOP_RENDER_MARKER_MISSING')
 print('AM228_BLADE_PHP_COMPILE_PASS')
if mode=='check':
 print('AM228_CHECK_PASS_NO_FILE_OR_FINANCIAL_WRITES')
 sys.exit(0)
if backup.exists():raise SystemExit('AM228_STOP_BACKUP_COLLISION')
if read(view)!=raw:raise SystemExit('AM228_STOP_CONCURRENT_VIEW_CHANGE')
backup.mkdir(mode=0o700,parents=True,exist_ok=False)
original_file.write_text(raw,encoding='utf8',errors='surrogateescape');os.chmod(original_file,0o600)
try:
 atomic(view,new)
 manifest.write_text(json.dumps({'before':digest(raw),'after':digest(new)}),encoding='ascii')
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  proc=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
  if proc.returncode:raise RuntimeError('AM228_STOP_VIEW_CACHE '+proc.stderr[-400:])
except BaseException as e:
 atomic(view,raw)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM228_APPLY_FAILED_AND_ROLLED_BACK '+str(e))
print('AM228_PURCHASE_REGISTER_STAGING_UI_APPLIED_NO_DB_WRITES')
