#!/usr/bin/env python3
"""AM150 staging: JC OSL typed names become master on save; show real Laravel
Admin edit/delete validation errors instead of generic failure.
check|apply STAGING_ROOT; source backup, no database migration.
"""
import os,sys,hashlib,tempfile
from pathlib import Path
from datetime import datetime
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):raise SystemExit('AM150_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').exists():raise SystemExit('AM150_NOT_STAGING')
files={'controller':root/'app/Http/Controllers/Web/AdminJobCardOslLineController.php',
       'view':root/'resources/views/job-cards/show.blade.php'}
for p in files.values():
 if not p.is_file() or p.is_symlink():raise SystemExit('AM150_MISSING_SOURCE')
original={k:p.read_bytes() for k,p in files.items()}
s={k:v.decode('utf8','surrogateescape') for k,v in original.items()}
if 'AM150_TYPED_OSL_MASTER' in s['controller'] or 'AM150_REAL_VALIDATION' in s['view']:
 if 'AM150_TYPED_OSL_MASTER' in s['controller'] and 'AM150_REAL_VALIDATION' in s['view']:
  print('AM150_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM150_PARTIAL_INSTALL')
if 'AM146_OSL_MASTER' not in s['controller'] or 'AM146_OSL_MASTER' not in s['view']:
 raise SystemExit('AM150_REQUIRES_AM146')
old="          $qty=round((float)$d['quantity'],2);"
new=r'''          // AM150_TYPED_OSL_MASTER: all newly saved OSL services are reusable
          // in JC and Supplier Purchase Master immediately. Never create stock.
          if(!$master){
              $name=mb_strtoupper(trim((string)$d['description']));
              $master=DB::table('workshop_osl_master')
                  ->whereRaw('LOWER(TRIM(osl_name))=?',[mb_strtolower($name)])
                  ->lockForUpdate()->first();
              if($master && (int)$master->active!==1)
                  throw ValidationException::withMessages(['osl_master_id'=>'Matching OSL Master is inactive. Activate it before saving.']);
              if(!$master){
                  $masterId=DB::table('workshop_osl_master')->insertGetId([
                      'osl_code'=>'OSL-Q-'.strtoupper(substr(str_replace('-','',(string)\Illuminate\Support\Str::uuid()),0,14)),
                      'osl_name'=>$name,'category'=>'OUTSIDE LABOUR',
                      'default_cost'=>0,'gst_percent'=>0,'active'=>1,
                      'created_at'=>now(),'updated_at'=>now(),
                  ]);
                  $master=DB::table('workshop_osl_master')->where('id',$masterId)->first();
              }
          }
          $qty=round((float)$d['quantity'],2);'''
if s['controller'].count(old)!=1:raise SystemExit('AM150_BAD_CONTROLLER_ANCHOR')
s['controller']=s['controller'].replace(old,new,1)
old="""       if(!res.ok)throw new Error('Server refused: HTTP '+res.status);"""
new="""       // AM150_REAL_VALIDATION: Laravel validation errors must be visible.
       if(!res.ok){
         var details=await res.json().catch(function(){return null;});
         var msgs=details&&details.errors?Object.values(details.errors).flat():[];
         throw new Error(String(msgs[0]||(details&&details.message)||('Server HTTP '+res.status)).slice(0,300));
       }"""
if s['view'].count(old)!=1:raise SystemExit('AM150_BAD_FETCH_ANCHOR')
s['view']=s['view'].replace(old,new,1)
old="headers:{'Accept':'text/html'},redirect:'follow',cache:'no-store'"
new="headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},redirect:'follow',cache:'no-store'"
if s['view'].count(old)!=1:raise SystemExit('AM150_BAD_ACCEPT_ANCHOR')
s['view']=s['view'].replace(old,new,1)
changed={k:v.encode('utf8','surrogateescape') for k,v in s.items()}
for k,p in files.items():
 print('AM150_'+k.upper()+'_HASH '+hashlib.sha256(original[k]).hexdigest()+' -> '+hashlib.sha256(changed[k]).hexdigest())
if mode=='check':
 out=os.environ.get('AM150_PREVIEW_DIR')
 if out:
  for k,b in changed.items():
   p=Path(out)/(k+'.'+('php' if k=='controller' else 'blade.php'));p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
 print('AM150_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
if any(files[k].read_bytes()!=original[k] for k in files):raise SystemExit('AM150_SOURCE_CHANGED')
backup=root/'storage/app'/('am150_osl_backup_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(mode=0o700,parents=True)
for k,b in original.items():(backup/(k+'.bak')).write_bytes(b)
for k,b in original.items():
 if (backup/(k+'.bak')).read_bytes()!=b:raise SystemExit('AM150_BACKUP_FAIL')
installed=[]
try:
 for k,b in changed.items():
  p=files[k];fd,tmp=tempfile.mkstemp(prefix='.am150_',dir=p.parent)
  with os.fdopen(fd,'wb') as fp:fp.write(b)
  os.chmod(tmp,p.stat().st_mode&0o777)
  if p.read_bytes()!=original[k]:raise RuntimeError('AM150_SOURCE_CHANGED_DURING_DEPLOY')
  os.replace(tmp,p);installed.append(k)
 print('AM150_BACKUP='+str(backup))
 print('AM150_STAGING_UI_CONTROLLER_APPLIED_NO_DB_MUTATION')
except Exception:
 for k in installed:files[k].write_bytes(original[k])
 raise
