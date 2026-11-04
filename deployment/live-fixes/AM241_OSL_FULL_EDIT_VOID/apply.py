#!/usr/bin/env python3
"""AM241 safe Laravel installer: full OSL Edit and audited batch void.
Only controllers/routes/views change. Never invokes Save/Void or writes database.
"""
from pathlib import Path
import sys,datetime,shutil,tempfile,os
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply STAGING_ROOT or rollback STAGING_ROOT BACKUP')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM241_STOP_INVALID_STAGING_ROOT')
pmap={
 'routes/web.php':root/'routes/web.php',
 'resources/views/purchases/index.blade.php':root/'resources/views/purchases/index.blade.php',
 'resources/views/purchases/show.blade.php':root/'resources/views/purchases/show.blade.php',
}
controller=root/'app/Http/Controllers/Web/AdminOslPurchaseBatchController.php'
view=root/'resources/views/purchases/osl-batch-edit.blade.php'
backup_root=root/'storage/app/am-backups'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM241_STOP_BACKUP_MISSING')
 backup=Path(sys.argv[3]).expanduser().resolve()
 if backup.parent!=backup_root or not backup.name.startswith('AM241_OSL_FULL_EDIT_'):
  raise SystemExit('AM241_STOP_INVALID_BACKUP')
 for rel,p in pmap.items():
  if not (backup/rel).is_file():raise SystemExit('AM241_STOP_BACKUP_INCOMPLETE '+rel)
 for rel,p in pmap.items():shutil.copy2(backup/rel,p)
 for k,p in [('controller',controller),('view',view)]:
  if (backup/(k+'.original')).is_file():
   shutil.copy2(backup/(k+'.original'),p)
  elif p.is_file() and 'AM241_OSL_' in p.read_text('utf8','replace'):
   p.unlink()
 print('AM241_ROLLBACK_COMPLETE')
 sys.exit(0)
if controller.exists() or view.exists():
 raise SystemExit('AM241_STOP_EXISTING_OSL_BATCH_EDITOR_FILES')
for rel,p in pmap.items():
 if not p.is_file():raise SystemExit('AM241_STOP_MISSING '+rel)
source={name:path.read_text('utf8','surrogateescape') for name,path in pmap.items()}
marker='AM241_OSL_FULL_EDIT_VOID'
if marker in source['routes/web.php']:
 print('AM241_ALREADY_APPLIED')
 sys.exit(0)
anchor="    Route::get('/workshop-tools/osl/{purchase}/edit', [AdminWorkshopControlPageController::class,'editOsl'])"
if source['routes/web.php'].count(anchor)!=1:raise SystemExit('AM241_STOP_ROUTE_ANCHOR_MISMATCH')
routes="""    // AM241_OSL_FULL_EDIT_VOID — full OSL supplier bill edit, audited whole-bill Void.
    Route::get('/purchases/osl-batches/{purchase}/edit', [\\App\\Http\\Controllers\\Web\\AdminOslPurchaseBatchController::class,'edit'])->whereNumber('purchase')->name('erp.purchases.osl-batch.edit');
    Route::put('/purchases/osl-batches/{purchase}', [\\App\\Http\\Controllers\\Web\\AdminOslPurchaseBatchController::class,'update'])->whereNumber('purchase')->name('erp.purchases.osl-batch.update');
    Route::delete('/purchases/osl-batches/{purchase}', [\\App\\Http\\Controllers\\Web\\AdminOslPurchaseBatchController::class,'void'])->whereNumber('purchase')->name('erp.purchases.osl-batch.void');
"""
source['routes/web.php']=source['routes/web.php'].replace(anchor,routes+anchor,1)
old_register="route('erp.fix48.osl-edit',$r['id'])"
old_show="route('erp.fix48.osl-edit',$purchase['id'])"
if source['resources/views/purchases/index.blade.php'].count(old_register)!=1:
 raise SystemExit('AM241_STOP_REGISTER_OSL_EDIT_LINK_CHANGED')
if source['resources/views/purchases/show.blade.php'].count(old_show)!=1:
 raise SystemExit('AM241_STOP_VIEW_OSL_EDIT_LINK_CHANGED')
source['resources/views/purchases/index.blade.php']=source['resources/views/purchases/index.blade.php'].replace(old_register,"route('erp.purchases.osl-batch.edit',$r['id'])",1)
source['resources/views/purchases/show.blade.php']=source['resources/views/purchases/show.blade.php'].replace(old_show,"route('erp.purchases.osl-batch.edit',$purchase['id'])",1)
package=Path(__file__).resolve().parent
ctrl=package/'AdminOslPurchaseBatchController.php'
blade=package/'osl-batch-edit.blade.php'
if not ctrl.is_file() or not blade.is_file():raise SystemExit('AM241_STOP_PACKAGE_MISSING')
if 'AM241_OSL_BATCH_EDIT_VOID' not in ctrl.read_text('utf8') or 'AM241_OSL_FULL_EDIT_VOID' not in blade.read_text('utf8'):
 raise SystemExit('AM241_STOP_PACKAGE_MARKERS_MISSING')
if source['routes/web.php'].count(marker)!=1:raise SystemExit('AM241_STOP_BAD_ROUTE_OUTPUT')
print('AM241_PREFLIGHT_PASS')
if mode=='check':
 print('AM241_CHECK_NO_WRITES')
 sys.exit(0)
time=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S')
backup=backup_root/('AM241_OSL_FULL_EDIT_'+time)
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
for rel,p in pmap.items():
 (backup/rel).parent.mkdir(parents=True,exist_ok=True)
 shutil.copy2(p,backup/rel)
for k,p in [('controller',controller),('view',view)]:
 if p.exists():shutil.copy2(p,backup/(k+'.original'))
written=[]
try:
 for rel,p in pmap.items():
  fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am241_',suffix='.tmp')
  try:
   with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(source[rel])
   os.chmod(tmp,p.stat().st_mode&0o777)
   os.replace(tmp,p)
   written.append(rel)
  finally:
   if os.path.exists(tmp):os.unlink(tmp)
 for key,dest,src in [('controller',controller,ctrl),('view',view,blade)]:
  shutil.copy2(src,dest)
  os.chmod(dest,0o644)
  written.append(key)
except Exception:
 for rel,p in pmap.items():shutil.copy2(backup/rel,p)
 for k,p in [('controller',controller),('view',view)]:
  if (backup/(k+'.original')).exists():shutil.copy2(backup/(k+'.original'),p)
  elif p.exists():p.unlink()
 raise
print('AM241_APPLIED')
print('AM241_BACKUP='+str(backup))
print('AM241_NO_FINANCIAL_OR_DATABASE_MUTATIONS_FROM_INSTALLER')
