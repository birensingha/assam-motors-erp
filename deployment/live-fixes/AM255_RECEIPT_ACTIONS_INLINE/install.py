#!/usr/bin/env python3
"""AM255 STAGING Money Receipt Register: Open | Print | Void Reason | Void on one row.
Only resources/views/money-receipts/index.blade.php changes.
Usage: python3 install.py check|apply|rollback STAGING_ROOT
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, subprocess, sys, tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM255 install.py check|apply|rollback STAGING_ROOT')
action=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM255_NOT_STAGING')
rel='resources/views/money-receipts/index.blade.php'
view=root/rel
backups=root/'storage/app/am-backups'
pointer=backups/'AM255_RECEIPT_ACTIONS_INLINE_LAST.txt'
marker='AM255_RECEIPT_ACTIONS_ONE_ROW'
def sha(raw):return hashlib.sha256(raw).hexdigest()
def enc(v):return v.encode('utf-8','surrogateescape')
def txt(v):return v.decode('utf-8','surrogateescape')
def run(args,timeout=90):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(path,raw):
    fd,temp=tempfile.mkstemp(dir=path.parent,prefix='.am255_')
    try:
        with os.fdopen(fd,'wb') as f:f.write(raw)
        os.chmod(temp,path.stat().st_mode&0o777)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def one(s,old,new,tag):
    n=s.count(old)
    if n!=1:raise SystemExit('STOP_AM255_ANCHOR_'+tag+'_'+str(n))
    return s.replace(old,new,1)
def blade_lint(raw):
    with tempfile.TemporaryDirectory(prefix='am255blade_') as folder:
        src=Path(folder)/'index.blade.php'
        compiled=Path(folder)/'compiled.php'
        src.write_bytes(raw)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        compiled_result=run(['php','-r',code,str(src),str(compiled)],45)
        if compiled_result.returncode or not compiled.exists():
            raise SystemExit('STOP_AM255_BLADE_COMPILE '+(compiled_result.stdout+compiled_result.stderr)[-400:])
        lint=run(['php','-l',str(compiled)],40)
        if lint.returncode:
            raise SystemExit('STOP_AM255_COMPILED_PHP '+(lint.stdout+lint.stderr)[-400:])
def route_check():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=\Illuminate\Support\Facades\Route::getRoutes();
foreach(['erp.money-receipts.index'=>'GET','erp.money-receipts.show'=>'GET',
'erp.money-receipts.print'=>'GET','erp.money-receipts.void'=>'POST'] as $name=>$method){
 $route=$r->getByName($name);
 if(!$route||!in_array($method,$route->methods(),true))exit(41);
}echo 'AM255_EXISTING_RECEIPT_ROUTES_UNCHANGED';"""
    x=run(['php','-r',code],40)
    if x.returncode or 'AM255_EXISTING_RECEIPT_ROUTES_UNCHANGED' not in x.stdout:
        raise SystemExit('STOP_AM255_EXISTING_RECEIPT_ROUTES '+(x.stdout+x.stderr)[-350:])
    print('AM255_EXISTING_RECEIPT_ROUTES_UNCHANGED')

if action=='rollback':
    if not pointer.exists():raise SystemExit('STOP_AM255_ROLLBACK_BACKUP_POINTER_MISSING')
    b=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in b.parents or not b.name.startswith('AM255_INLINE_RECEIPT_ACTIONS_'):
        raise SystemExit('STOP_AM255_UNTRUSTED_BACKUP_PATH')
    m=json.loads((b/'manifest.json').read_text())
    if not view.is_file() or sha(view.read_bytes())!=m['installed_sha']:
        raise SystemExit('STOP_AM255_LATER_SOURCE_CHANGED')
    atomic(view,(b/'index.blade.php').read_bytes())
    x=run(['php','artisan','view:clear'])
    if x.returncode:raise SystemExit('AM255_BACKUP_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM255_RECEIPT_VIEW_ROLLBACK_DONE')
    sys.exit(0)

if not view.is_file():raise SystemExit('STOP_AM255_VIEW_MISSING')
old=view.read_bytes()
s=txt(old)
if marker in s:
    print('AM255_ALREADY_INSTALLED')
    sys.exit(0)
if 'AM250_RECEIPT_VOID_UI' not in s:
    raise SystemExit('STOP_AM255_AM250_EXISTING_VOID_UI_MISSING')
style=r'''
/* AM255_RECEIPT_ACTIONS_ONE_ROW — compact Open | Print | Reason | Void */
.p58-reg .wrap-table{overflow-x:auto}
.p58-reg th:last-child,
.p58-reg td.am255-actions-cell{min-width:366px;white-space:nowrap}
.p58-reg td.am255-actions-cell{vertical-align:middle}
.p58-reg .am255-actions-row{
  display:flex !important;flex-wrap:nowrap !important;
  align-items:center;justify-content:flex-start;
  column-gap:5px;white-space:nowrap;width:max-content;max-width:none;
}
.p58-reg .am255-actions-row .btn58{
  min-height:34px;padding:7px 10px;border-radius:7px;
  line-height:1.1;white-space:nowrap;flex-shrink:0;
}
.p58-reg .am255-void-form{
  display:inline-flex !important;flex-wrap:nowrap !important;
  align-items:center;gap:5px;margin:0 !important;
  padding:0 !important;white-space:nowrap;flex:0 0 auto
}
.p58-reg .am255-void-form .am255-reason{
  width:145px !important;min-width:145px !important;
  max-width:145px !important;height:34px;padding:6px 8px;
  font-size:11px;line-height:1.2;border:1px solid #bdcfe1;
  border-radius:7px;box-sizing:border-box;
}
.p58-reg .am255-void-form button{
  color:#a52129 !important;border-color:#e4a1a6 !important;
}
'''
s=one(s,
    '</style>\n<div class="p58-reg">',
    style+'</style>\n<div class="p58-reg">',
    'STYLE')
s=one(s,
    '''      <td>
     <a class="btn58 alt" href="{{ route('erp.money-receipts.show',$p->id) }}">Open</a>
     <a class="btn58 alt" target="_blank" rel="noopener" href="{{ route('erp.money-receipts.print',$p->id) }}">Print</a>''',
    '''      <td class="am255-actions-cell">
     <div class="am255-actions-row">
     <a class="btn58 alt" href="{{ route('erp.money-receipts.show',$p->id) }}">Open</a>
     <a class="btn58 alt" target="_blank" rel="noopener" href="{{ route('erp.money-receipts.print',$p->id) }}">Print</a>''',
    'ACTION_CELL')
s=one(s,
    '''<form action="{{ route('erp.money-receipts.void',$p->id) }}" method="post" style="display:inline-flex;flex-wrap:wrap;gap:4px;align-items:center;margin:4px 0"''',
    '''<form class="am255-void-form" action="{{ route('erp.money-receipts.void',$p->id) }}" method="post"''',
    'VOID_FORM')
s=one(s,
    '''<input type="text" name="reason" required minlength="8" maxlength="300" aria-label="Mandatory Money Receipt void reason"
         placeholder="Reason: duplicate entry etc." style="width:175px;max-width:100%;font-size:11px;padding:7px">''',
    '''<input class="am255-reason" type="text" name="reason" required minlength="8" maxlength="300"
         aria-label="Mandatory Money Receipt void reason" title="Mandatory reason for receipt cancellation"
         placeholder="Void reason">''',
    'REASON_INPUT')
s=one(s,
    '''<button type="submit" class="btn58 alt" style="color:#a52129;border-color:#e4a1a6">Void</button>''',
    '''<button type="submit" class="btn58 alt">Void</button>''',
    'VOID_BUTTON')
s=one(s,
    '''     @endif
    </td>
    </tr>
   @empty''',
    '''     @endif
     </div>
    </td>
    </tr>
   @empty''',
    'ACTION_ROW_END')
new=enc(s)
if old==new:raise SystemExit('STOP_AM255_NO_DIFFERENCE')
if s.count('am255-actions-row')!=3:raise SystemExit('STOP_AM255_ACTION_ROW_MISSING_OR_DUPLICATED')
blade_lint(old)
blade_lint(new)
route_check()
print('AM255_RECEIPT_BLADE_AND_EXISTING_ROUTES_PASS')
if action=='check':
    print('AM255_READONLY_PREFLIGHT_PASS')
    sys.exit(0)
backup=backups/('AM255_INLINE_RECEIPT_ACTIONS_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o700,parents=True,exist_ok=False)
(backup/'index.blade.php').write_bytes(old)
try:
    if view.read_bytes()!=old:raise RuntimeError('STAGING_RECEIPT_VIEW_MODIFIED_DURING_CHECK')
    atomic(view,new)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        res=run(cmd,110)
        if res.returncode:raise RuntimeError('BLADE_CACHE_ERROR '+(res.stdout+res.stderr)[-400:])
except BaseException as e:
    atomic(view,old)
    run(['php','artisan','view:clear'])
    raise SystemExit('STOP_AM255_PATCH_ROLLED_BACK_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed_sha':sha(new)},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM255_RECEIPT_OPEN_PRINT_REASON_VOID_ONE_ROW_INSTALLED')
print('AM255_ONLY_BLADE_VIEW_CHANGED_NO_DATABASE_OR_ACCOUNTING_EDIT')
print('AM255_BACKUP='+str(backup))
