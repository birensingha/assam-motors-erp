#!/usr/bin/env python3
"""AM246 guarded source installer: only read-only master transaction explorer UI/routes.
Backup all existing files before modifications. check (no writes), apply, rollback.
No database migrations, inventory updates, ledger posting, or financial writes.
"""
from pathlib import Path
import sys,datetime,shutil,tempfile,os
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply STAGING_ROOT | rollback STAGING_ROOT BACKUP')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM246_STOP_WRONG_LARAVEL_ROOT')
here=Path(__file__).resolve().parent
existing={
 'routes/web.php':root/'routes/web.php',
 'resources/views/admin/native/parts.blade.php':root/'resources/views/admin/native/parts.blade.php',
 'resources/views/admin/native/labour.blade.php':root/'resources/views/admin/native/labour.blade.php',
 'resources/views/osl-masters/index.blade.php':root/'resources/views/osl-masters/index.blade.php',
}
new={
 'app/Http/Controllers/Web/AdminMasterItemLedgerController.php':(
    root/'app/Http/Controllers/Web/AdminMasterItemLedgerController.php',here/'AdminMasterItemLedgerController.php'),
 'resources/views/admin/native/item-ledger.blade.php':(
    root/'resources/views/admin/native/item-ledger.blade.php',here/'item-ledger.blade.php'),
}
backup_dir=root/'storage/app/am-backups'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM246_BACKUP_REQUIRED')
 backup=Path(sys.argv[3]).expanduser().resolve()
 if backup.parent!=backup_dir or not backup.name.startswith('AM246_MASTER_ITEM_LEDGER_'):
  raise SystemExit('AM246_STOP_INVALID_BACKUP')
 for rel in existing:
  if not (backup/rel).is_file():raise SystemExit('AM246_STOP_BACKUP_MISSING '+rel)
 for rel,p in existing.items():shutil.copy2(backup/rel,p)
 for rel,(dest,source) in new.items():
  if (backup/rel).is_file():shutil.copy2(backup/rel,dest)
  elif dest.is_file() and 'AM246_MASTER_ITEM_LEDGERS' in dest.read_text('utf8','replace'):dest.unlink()
 print('AM246_ROLLBACK_COMPLETE')
 sys.exit(0)
for rel,p in existing.items():
 if not p.is_file():raise SystemExit('AM246_STOP_MISSING_ORIGINAL '+rel)
for rel,(dest,source) in new.items():
 if not source.is_file():raise SystemExit('AM246_STOP_MISSING_PAYLOAD '+rel)
 if dest.exists():raise SystemExit('AM246_STOP_DESTINATION_ALREADY_EXISTS '+rel)
 if 'AM246_MASTER_ITEM_LEDGERS' not in source.read_text('utf8','replace'):
  raise SystemExit('AM246_STOP_SOURCE_MARKER_MISSING '+rel)
src={rel:p.read_text('utf8','surrogateescape') for rel,p in existing.items()}
if any('AM246_MASTER_ITEM_LEDGERS' in s for s in src.values()):
 print('AM246_ALREADY_APPLIED')
 sys.exit(0)
def change(rel,old,new,name):
 n=src[rel].count(old)
 if n!=1:raise SystemExit('AM246_STOP_ANCHOR_'+name+'_COUNT_'+str(n))
 src[rel]=src[rel].replace(old,new,1)
# Adds under existing /erp auth middleware and preserves every old master route.
change('routes/web.php',
 "Route::get('/masters/parts', [AdminNativeModuleController::class,'parts'])->name('erp.masters.parts');",
 """// AM246_MASTER_ITEM_LEDGERS — searchable transaction audit for Parts, Labour/ROT and OSL Master.
Route::get('/masters/item-ledger', [\App\Http\Controllers\Web\AdminMasterItemLedgerController::class,'index'])->name('erp.masters.item-ledger');
Route::get('/masters/item-ledger/search', [\App\Http\Controllers\Web\AdminMasterItemLedgerController::class,'suggest'])->name('erp.masters.item-ledger.suggest');
Route::get('/masters/parts', [AdminNativeModuleController::class,'parts'])->name('erp.masters.parts');""",
 'route')
change('resources/views/admin/native/parts.blade.php',
 '<span class="muted" title="Accounting history protected">Use Edit → Active = No</span>',
 '''<a class="btn small" style="background:#eaf4ff;border:1px solid #bed8f0;color:#15538d;text-decoration:none" href="{{ route('erp.masters.item-ledger',['type'=>'PART','id'=>$r->id]) }}">📖 Transactions</a><span class="muted" title="Accounting history protected">Use Edit → Active = No</span>''',
 'parts_row_button')
change('resources/views/admin/native/labour.blade.php',
 '<span class="muted" title="Accounting history protected">Use Edit → Active = No</span>',
 '''<a class="btn small" style="background:#eaf4ff;border:1px solid #bed8f0;color:#15538d;text-decoration:none" href="{{ route('erp.masters.item-ledger',['type'=>'LABOUR','id'=>$r->id]) }}">📖 Transactions</a><span class="muted" title="Accounting history protected">Use Edit → Active = No</span>''',
 'labour_row_button')
change('resources/views/osl-masters/index.blade.php',
 "@section('content')",
 """@section('content')
{{-- AM246_MASTER_ITEM_LEDGERS — same OSL master, read-only supplier and JC billing transaction explorer. --}}
<div style="margin:3px 0 14px;padding:11px 14px;background:#edf5ff;border:1px solid #bfd7ef;border-radius:10px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
  <span style="font-size:12px;font-weight:700;color:#16446f">OSL Service Transaction History · Vendor Purchase · Job Card Customer Billing</span>
  <a href="{{ route('erp.masters.item-ledger',['type'=>'OSL']) }}" style="display:inline-block;background:#175ba1;color:white;text-decoration:none;border-radius:8px;padding:9px 14px;font-size:12px;font-weight:800">📖 Search OSL Item Ledger</a>
</div>""",
 'osl_master_link')
# Header shortcut also in each native master; preserves original page search/edit.
for rel,tag in [('resources/views/admin/native/parts.blade.php','PART'),('resources/views/admin/native/labour.blade.php','LABOUR')]:
 anchor="@section('content')"
 snippet="""@section('content')
{{-- AM246_MASTER_ITEM_LEDGERS — read-only item transaction drilldown. --}}
<div style="display:flex;justify-content:flex-end;margin:0 0 9px">
 <a href="{{ route('erp.masters.item-ledger',['type'=>'"""+tag+"""']) }}" style="font-weight:800;font-size:12px;padding:9px 15px;border-radius:8px;background:#185da6;text-decoration:none;color:white">📖 Search All """+('Parts' if tag=='PART' else 'ROT / Labour')+""" Transactions</a>
</div>"""
 change(rel,anchor,snippet,tag+'_master_header_link')
print('AM246_PREFLIGHT_PASS')
if mode=='check':
 print('AM246_NO_CHANGES_CHECK_ONLY')
 sys.exit(0)
backup=backup_dir/('AM246_MASTER_ITEM_LEDGER_'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,mode=0o750,exist_ok=False)
for rel,p in existing.items():
 b=backup/rel;b.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,b)
written=[]
try:
 for rel,p in existing.items():
  fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am246_',suffix='.tmp')
  try:
   with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as file:file.write(src[rel])
   os.chmod(tmp,p.stat().st_mode&0o777)
   os.replace(tmp,p)
   written.append(rel)
  finally:
   if os.path.exists(tmp):os.unlink(tmp)
 for rel,(dest,source) in new.items():
  dest.parent.mkdir(parents=True,exist_ok=True)
  shutil.copy2(source,dest);os.chmod(dest,0o644);written.append(rel)
except Exception:
 for rel,p in existing.items():shutil.copy2(backup/rel,p)
 for rel,(dest,source) in new.items():
  if dest.exists():dest.unlink()
 raise
print('AM246_INSTALLED')
print('AM246_BACKUP='+str(backup))
print('AM246_READONLY_FEATURE_NO_DATABASE_WRITES')
