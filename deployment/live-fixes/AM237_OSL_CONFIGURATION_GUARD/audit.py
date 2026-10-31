#!/usr/bin/env python3
"""AM237 read-only configuration + OSL Purchase & JC selectors audit."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM237_STOP_NOT_STAGING')
prefixes=['resources/views','app/Http/Controllers/Web','routes']
needles=[r'Parts Master',r'ROT Master',r'Configuration',r'OSL Master Register',r'oslBillingOptions',r'function createOsl',r'function oslCreate',r'saveOsl',r'new OSL',r'job_card_labour',r'osl_master_id',r'labour_line_id']
print('AM237_INDEX_BEGIN')
for prefix in prefixes:
  path=root/prefix
  for f in ([path] if path.is_file() else path.rglob('*.php')):
    if not f.is_file():continue
    lines=f.read_text('utf8','replace').splitlines()
    hits=[i for i,l in enumerate(lines) if any(re.search(x,l,re.I) for x in needles)]
    if hits and (prefix!='resources/views' or any(x in str(f).lower() for x in ('config','purchase','layout','nav'))):
      print('AM237_FILE',f.relative_to(root),'LINES',len(lines),'HITS',','.join(str(i+1) for i in hits[:60]))
print('AM237_INDEX_END')
ranges={
 'resources/views/purchases/osl-create.blade.php':[(1,215)],
 'resources/views/configuration/index.blade.php':[(1,260)],
 'app/Http/Controllers/Web/AdminPurchasePageController.php':[(1,250)],
 'app/Http/Controllers/Web/AdminConfigurationPageController.php':[(1,230)],
 'app/Services/PurchaseService.php':[(880,1022)],
}
for path,spans in ranges.items():
 p=root/path
 if not p.is_file():continue
 lines=p.read_text('utf8','replace').splitlines()
 for a,b in spans:
  print('AM237_SOURCE_BEGIN',path,a,b)
  for i in range(a-1,min(b,len(lines))):
    line=lines[i]
    if re.search(r'(?i)password|secret|token|authorization',line):continue
    print('AM237_LINE',i+1,line[:600])
  print('AM237_SOURCE_END',path)
print('AM237_READONLY_COMPLETE')
