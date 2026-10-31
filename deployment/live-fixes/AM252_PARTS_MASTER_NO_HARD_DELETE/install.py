#!/usr/bin/env python3
"""AM252 P0: stop all Parts Master HARD DELETE server-side. Use existing Active=No edit.
check|apply|rollback STAGING_ROOT; no DB DML/DDL, atomic source backup, cache preflight.
"""
from pathlib import Path
import sys,os,tempfile,hashlib,json,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM252_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM252_STOP_NOT_STAGING')
controller=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
backup=root/'storage/app/am-backups/AM252_PARTS_MASTER_NO_HARD_DELETE'
manifest=backup/'hashes.json'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,t=tempfile.mkstemp(prefix='.am252_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if not controller.is_file() or controller.is_symlink():raise SystemExit('AM252_STOP_SOURCE_MISSING')
old=read(controller)
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM252_STOP_BACKUP_MISSING')
 state=json.loads(read(manifest))
 if sha(old)!=state['after']:raise SystemExit('AM252_STOP_CONCURRENT_EDIT')
 atomic(controller,read(backup/'controller.before'))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM252_ROLLBACK_RESTORED');sys.exit(0)
marker='AM252_PARTS_MASTER_NO_HARD_DELETE'
if marker in old:
 if old.count(marker)==1 and "Master hard-delete disabled" in old:
  print('AM252_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM252_STOP_PARTIAL')
before="    public function deletePart(Request $request,int $part): RedirectResponse { $this->admin($request); DB::table('parts_master')->where('id',$part)->delete(); return back()->with('success','Part deleted.'); }"
after="""    // AM252_PARTS_MASTER_NO_HARD_DELETE: P0 financial master integrity safety switch.
    // A direct hard delete bypassed historical purchases and inventory movements.
    // No part (even currently unused) can be hard-deleted by this route until a
    // complete FK/ledger/audit preservation policy has been separately verified.
    public function deletePart(Request $request,int $part): RedirectResponse
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('parts_master'),503,'Parts Master unavailable.');
        $entry=DB::table('parts_master')->where('id',$part)->first();
        abort_unless($entry,404,'Part not found.');
        \\Illuminate\\Support\\Facades\\Log::warning('AM252_PART_MASTER_HARD_DELETE_BLOCKED',[
            'part_id'=>$part,
            'actor_id'=>$request->user()?->id,
            'part_code'=>(string)($entry->part_code??''),
        ]);
        return back()->withErrors([
            'delete'=>'Master hard-delete disabled to preserve purchases, stock movements, invoices and audit history. To stop future use, open Edit Part and set Active = No. Existing records remain unchanged.'
        ]);
    }"""
if old.count(before)!=1:raise SystemExit('AM252_STOP_EXPECTED_DELETE_FUNCTION_'+str(old.count(before)))
new=old.replace(before,after,1)
if new.count(marker)!=1 or "where('id',$part)->delete()" in new:
 raise SystemExit('AM252_STOP_DELETE_STILL_ENABLED')
with tempfile.TemporaryDirectory(prefix='am252qa_') as d:
 f=Path(d)/'Native.php';f.write_text(new,encoding='utf8',errors='surrogateescape')
 proc=subprocess.run(['php','-l',str(f)],cwd=root,capture_output=True,text=True,timeout=30)
 if proc.returncode:raise SystemExit('AM252_PHP_LINT_FAILED '+(proc.stdout+proc.stderr)[-650:])
print('AM252_PARTS_HARD_DELETE_SERVER_BLOCK_PHP_LINT_PASS')
if mode=='check':print('AM252_CHECK_ONLY_NO_DB_WRITES');sys.exit(0)
if backup.exists() or read(controller)!=old:raise SystemExit('AM252_STOP_BACKUP_OR_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
b=backup/'controller.before';b.write_text(old,encoding='utf8',errors='surrogateescape');os.chmod(b,0o600)
try:
 atomic(controller,new)
 manifest.write_text(json.dumps({'after':sha(new)}),encoding='ascii');os.chmod(manifest,0o600)
 proc=subprocess.run(['php','artisan','route:list'],cwd=root,capture_output=True,text=True,timeout=90)
 if proc.returncode:raise RuntimeError('AM252_ROUTE_LIST '+proc.stderr[-440:])
 if 'erp.masters.parts.delete' not in proc.stdout:raise RuntimeError('AM252_DELETE_ROUTE_NOT_REGISTERED')
except BaseException as e:
 atomic(controller,old)
 raise SystemExit('AM252_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM252_PART_MASTER_HARD_DELETE_BLOCK_INSTALLED_NO_FINANCIAL_WRITES')
print('AM252_INCIDENT_PB_RECONCILIATION_PENDING')
