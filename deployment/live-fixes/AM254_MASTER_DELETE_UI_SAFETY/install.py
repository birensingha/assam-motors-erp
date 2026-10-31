#!/usr/bin/env python3
"""AM254 P0 accounting master protection: block ROT master delete and remove
Parts/ROT master DELETE forms. check|apply|rollback STAGING_ROOT.
No accounting DB mutation; backs up all source and auto-rollbacks on error.
"""
from pathlib import Path
import sys,re,os,hashlib,json,tempfile,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM254_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM254_STOP_NOT_STAGING')
files={
 'controller':root/'app/Http/Controllers/Web/AdminNativeModuleController.php',
 'parts':root/'resources/views/admin/native/parts.blade.php',
 'labour':root/'resources/views/admin/native/labour.blade.php'
}
backup=root/'storage/app/am-backups/AM254_MASTER_DELETE_UI_SAFETY'
manifest=backup/'hashes.json';marker='AM254_MASTER_DELETE_UI_SAFETY'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,t=tempfile.mkstemp(dir=p.parent,prefix='.am254_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if any(not p.is_file() or p.is_symlink() for p in files.values()):raise SystemExit('AM254_STOP_SOURCE_MISSING')
old={k:read(p) for k,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM254_STOP_NO_BACKUP')
 data=json.loads(read(manifest))
 if any(sha(old[n])!=data['after'][n] for n in files):raise SystemExit('AM254_STOP_SOURCE_CHANGED')
 for n,p in files.items():atomic(p,read(backup/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM254_ROLLBACK_ALL_THREE_FILES');sys.exit(0)
if all(marker in v for v in old.values()):
 print('AM254_ALREADY_INSTALLED');sys.exit(0)
if any(marker in v for v in old.values()):
 raise SystemExit('AM254_STOP_PARTIAL_INSTALL')
if 'AM252_PARTS_MASTER_NO_HARD_DELETE' not in old['controller']:
 raise SystemExit('AM254_STOP_PARTS_SERVER_GUARD_MISSING')
new=dict(old)
before="""    public function deleteLabour(Request $request,int $labour): RedirectResponse
    {
        $this->admin($request); abort_unless(Schema::hasTable('labour_master'),404);
        $row=DB::table('labour_master')->where('id',$labour)->first(); abort_unless($row,404);
        DB::transaction(function()use($labour,$row){ if(Schema::hasTable('part_rot_mapping')) DB::table('part_rot_mapping')->where('rot_code',$row->rot_code)->delete(); DB::table('labour_master')->where('id',$labour)->delete(); });
        return back()->with('success','ROT/Labour master deleted. Existing Job Card history is not removed.');
    }"""
after="""    // AM254_MASTER_DELETE_UI_SAFETY: accounting-master hard deletion is blocked.
    // Earlier code also deleted Part/ROT mappings; leave them and historical JC data intact.
    public function deleteLabour(Request $request,int $labour): RedirectResponse
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('labour_master'),503,'ROT Master unavailable.');
        $row=DB::table('labour_master')->where('id',$labour)->first();
        abort_unless($row,404,'ROT Master not found.');
        \\Illuminate\\Support\\Facades\\Log::warning('AM254_ROT_MASTER_HARD_DELETE_BLOCKED',[
            'rot_id'=>$labour,
            'rot_code'=>(string)($row->rot_code??''),
            'actor_id'=>$request->user()?->id,
        ]);
        return back()->withErrors([
            'delete'=>'ROT / Labour Master hard-delete disabled. Historical JC work, mapping and invoice details must be retained. Use Edit and set Active = No.'
        ]);
    }"""
if new['controller'].count(before)!=1:raise SystemExit('AM254_STOP_ROT_DELETE_CONTROLLER_ANCHOR')
new['controller']=new['controller'].replace(before,after,1)
# Remove the actual danger form from row templates; original Edit form contains Active=No.
patterns={
 'parts':r'<form method="post" action="\{\{ route\(\x27erp\.masters\.parts\.delete\x27,\$r->id\) \}\}" onsubmit="[^"]*">@csrf @method\(\x27DELETE\x27\)<button class="btn small danger">🗑 Delete</button></form>',
 'labour':r'<form method="post" action="\{\{ route\(\x27erp\.masters\.labour\.delete\x27,\$r->id\) \}\}" onsubmit="[^"]*">@csrf @method\(\x27DELETE\x27\)<button class="btn small danger">🗑 Delete</button></form>',
}
for name,pattern in patterns.items():
 matches=list(re.finditer(pattern,new[name]))
 if len(matches)!=1:
  raise SystemExit('AM254_STOP_DELETE_UI_ANCHOR_'+name+'_'+str(len(matches)))
 new[name]=re.sub(pattern,'<span class="muted" title="Accounting history protected">Use Edit → Active = No</span>',new[name],count=1)
if new['parts'].count('stock, price, GST and delete — all actions directly visible.')!=1:
 raise SystemExit('AM254_STOP_PARTS_HEADING')
new['parts']=new['parts'].replace(
 'stock, price, GST and delete — all actions directly visible.',
 'stock, price, GST and Active / Inactive. Hard-delete disabled to protect purchase and inventory history.',1)
if new['labour'].count('Add, edit and delete ROT master entries with standard time, labour rate, SAC/HSN and GST.')!=1:
 raise SystemExit('AM254_STOP_ROT_HEADING')
new['labour']=new['labour'].replace(
 'Add, edit and delete ROT master entries with standard time, labour rate, SAC/HSN and GST.',
 'Add/edit ROT entries. Set Active = No to retire a service; hard-delete is disabled to protect JC history.',1)
new['parts']=new['parts'].replace(
 '<div class="page-head sharp-page-head">',
 '{{-- AM254_MASTER_DELETE_UI_SAFETY: Part hard delete removed; set Active = No. --}}\n<div class="page-head sharp-page-head">',1)
new['labour']=new['labour'].replace(
 '<div class="page-head sharp-page-head">',
 '{{-- AM254_MASTER_DELETE_UI_SAFETY: ROT hard delete removed; set Active = No. --}}\n<div class="page-head sharp-page-head">',1)
for name,s in new.items():
 if s.count(marker)!=1:raise SystemExit('AM254_STOP_PATCH_MARKER_'+name)
for name,route in [('parts','erp.masters.parts.delete'),('labour','erp.masters.labour.delete')]:
 if route in new[name]:raise SystemExit('AM254_STOP_DELETE_FORM_REMAINING_'+name)
 if 'name="active"' not in new[name]:raise SystemExit('AM254_STOP_ACTIVE_EDIT_MISSING_'+name)
with tempfile.TemporaryDirectory(prefix='am254qa_') as temp:
 d=Path(temp);p=d/'controller.php';p.write_text(new['controller'],encoding='utf8',errors='surrogateescape')
 proc=subprocess.run(['php','-l',str(p)],cwd=root,text=True,capture_output=True,timeout=30)
 if proc.returncode:raise SystemExit('AM254_STOP_CONTROLLER_PHP_LINT '+(proc.stdout+proc.stderr)[-450:])
 helper=d/'compile.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 for name in ('parts','labour'):
  source=d/(name+'.blade.php');compiled=d/(name+'.compiled.php')
  source.write_text(new[name],encoding='utf8',errors='surrogateescape')
  p=subprocess.run(['php',str(helper),str(root),str(source),str(compiled)],cwd=root,text=True,capture_output=True,timeout=55)
  if p.returncode:raise SystemExit('AM254_STOP_BLADE_COMPILE_'+name+' '+p.stderr[-450:])
  p=subprocess.run(['php','-l',str(compiled)],cwd=root,text=True,capture_output=True,timeout=30)
  if p.returncode:raise SystemExit('AM254_STOP_BLADE_LINT_'+name+' '+(p.stdout+p.stderr)[-450:])
print('AM254_PART_AND_ROT_MASTER_SERVER_BLADE_PREFLIGHT_PASS')
if mode=='check':print('AM254_CHECK_ONLY_NO_DB_WRITES');sys.exit(0)
if backup.exists() or any(read(files[n])!=old[n] for n in files):
 raise SystemExit('AM254_STOP_BACKUP_COLLISION_OR_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n,p in files.items():
 b=backup/(n+'.before');b.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(b,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files}}),encoding='ascii');os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  res=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if res.returncode:raise RuntimeError('AM254_STOP_CACHE '+res.stderr[-350:])
except BaseException as e:
 for n,p in files.items():atomic(p,old[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM254_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM254_PART_AND_ROT_MASTER_DELETE_UI_REMOVED_AND_ROT_ROUTE_BLOCKED')
print('AM254_NO_DB_MUTATIONS_BROWSER_QA_PENDING')
