#!/usr/bin/env python3
"""Hide Generation and Nickname from both invoice print modes, without changing any records."""
import hashlib,os,shutil,stat,sys,time
from pathlib import Path
root=Path(sys.argv[1]).resolve()
p=root/'resources/views/invoices/print.blade.php'
if not (root/'artisan').is_file() or not p.is_file():
    raise SystemExit('SAFE_STOP: staging invoice template missing')
s=p.read_text(encoding='utf-8')
sha=hashlib.sha256(s.encode()).hexdigest()
print('BEFORE_SHA_PREFIX',sha[:16])
marker='AM_INVOICE_HIDE_GENERATION_NICKNAME_V1'
if marker in s:
    print('ALREADY_APPLIED')
    raise SystemExit(0)
if sha[:16]!='3548e3b93610ecf6' or 'AM_INVOICE_VEHICLE_MASTER_V1' not in s:
    raise SystemExit('SAFE_STOP: staging print file differs from last verified version; must re-audit')
changes=[
    ('  <dt>Generation</dt><dd>{{ $amGeneration!==\'\'?$amGeneration:\'—\' }}</dd>\n',''),
    ('  <dt>Nickname</dt><dd>{{ $amNickname!==\'\'?$amNickname:\'—\' }}</dd>\n',''),
    ("$amGeneration=trim((string)($amCatalog->generation_name??''));\n",''),
    ("$amNickname=trim((string)($amVehicle->nickname??''));\n",'')
]
for a,b in changes:
    if s.count(a)!=1:
        raise SystemExit('SAFE_STOP: expected one generation/nickname anchor; got '+str(s.count(a)))
    s=s.replace(a,b,1)
s=s.replace('// AM_INVOICE_VEHICLE_MASTER_V1', '// '+marker+'\n// AM_INVOICE_VEHICLE_MASTER_V1',1)
for must in ['Chassis / VIN','Engine No.','Engine CC','Engine Code','Transmission','Registration Date','Vehicle Identity / Specifications']:
    if must not in s: raise SystemExit('SAFE_STOP: required print field disappeared: '+must)
for obsolete in ['<dt>Generation</dt>','<dt>Nickname</dt>','$amGeneration=','$amNickname=']:
    if obsolete in s: raise SystemExit('SAFE_STOP: unwanted field still present')
if s.count('AM_HSN_PANEL_V1')!=1 or s.count('AM_INVOICE_ROW_V2')!=1:
    raise SystemExit('SAFE_STOP: invoice template unexpectedly modified')
backup=p.with_name(p.name+'.before-hide-gen-nickname-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(p,backup)
tmp=p.with_name(p.name+'.hide-gen-nickname.tmp')
tmp.write_text(s,encoding='utf-8')
os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
os.replace(tmp,p)
print('GENERATION_NICKNAME_REMOVED','BACKUP='+backup.name,'AFTER_SHA_PREFIX='+hashlib.sha256(s.encode()).hexdigest()[:16])
