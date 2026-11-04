#!/usr/bin/env python3
"""AM235 style correction for layouts.erp without styles stack. No financial writes."""
from pathlib import Path
import sys,shutil,datetime
if len(sys.argv)<3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: polish.py check|apply STAGING_ROOT [BACKUP]')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM235_STYLE_INVALID_STAGING')
p=root/'resources/views/purchases/reconciliation.blade.php'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM235_STYLE_BACKUP_REQUIRED')
 b=Path(sys.argv[3]).resolve()
 if b.parent!=root/'storage/app/am-backups' or not b.name.startswith('AM235_STYLE_'):
  raise SystemExit('AM235_STYLE_INVALID_BACKUP')
 source=b/'reconciliation.blade.php'
 if not source.is_file():raise SystemExit('AM235_STYLE_BACKUP_MISSING')
 shutil.copy2(source,p)
 print('AM235_STYLE_ROLLBACK_PASS');sys.exit(0)
s=p.read_text('utf8','surrogateescape')
if 'AM235_STYLE_INLINE_FOR_ERP_LAYOUT' in s:
 print('AM235_STYLE_ALREADY_INLINE');sys.exit(0)
if s.count("@push('styles')")!=1 or s.count('@endpush')!=1 or s.count("@section('content')")!=1:
 raise SystemExit('AM235_STYLE_SOURCE_MISMATCH')
a=s.index("@push('styles')")
b=s.index('@endpush',a)+len('@endpush')
block=s[a:b].replace("@push('styles')",'',1).rsplit('@endpush',1)[0].strip()
s=s[:a]+s[b:]
anchor="@section('content')"
s=s.replace(anchor,anchor+"\n{{-- AM235_STYLE_INLINE_FOR_ERP_LAYOUT --}}\n"+block+"\n",1)
if s.count('AM235_STYLE_INLINE_FOR_ERP_LAYOUT')!=1 or s.count('<style>')!=1:
 raise SystemExit('AM235_STYLE_OUTPUT_NOT_SAFE')
print('AM235_STYLE_PREFLIGHT_PASS')
if mode=='check':print('AM235_STYLE_CHECK_NO_WRITES');sys.exit(0)
backup=root/'storage/app/am-backups'/('AM235_STYLE_'+datetime.datetime.utcnow().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
shutil.copy2(p,backup/'reconciliation.blade.php')
tmp=p.with_suffix('.am235style.tmp')
tmp.write_text(s,encoding='utf8',errors='surrogateescape')
tmp.chmod(p.stat().st_mode&0o777)
tmp.replace(p)
print('AM235_STYLE_APPLIED')
print('AM235_STYLE_BACKUP='+str(backup))
