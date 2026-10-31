#!/usr/bin/env python3
"""AM228 read-only Purchase Register UI location audit.
No DB, no writes, no secrets.
"""
from pathlib import Path
import re,sys
if len(sys.argv)!=2:raise SystemExit('AM228_USAGE_STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM228_STOP_NOT_STAGING')
candidates=[
'resources/views/purchases/index.blade.php',
'resources/views/purchases/register.blade.php',
'resources/views/purchases/list.blade.php',
'resources/views/workshop/purchases.blade.php',
'resources/views/workshop/purchase-register.blade.php',
]
views=root/'resources/views'
for file in views.rglob('*.blade.php'):
 if 'purchase' in str(file.relative_to(views)).lower() and str(file.relative_to(root)) not in candidates:
  candidates.append(str(file.relative_to(root)))
for p in candidates:
 file=root/p
 if not file.is_file():continue
 lines=file.read_text('utf8','replace').splitlines()
 matches=[i for i,l in enumerate(lines) if re.search(r'Direct Parts Sale Invoice|All Types|Purchase Register|New OSL Purchase|New Parts Purchase|name=["\\x27]type|type_filter',l,re.I)]
 if not matches:continue
 print('AM228_VIEW',p,'TOTAL_LINES',len(lines),'MATCH_COUNT',len(matches))
 seen=set()
 for i in matches[:20]:
  for j in range(max(0,i-4),min(len(lines),i+5)):
   if j in seen:continue
   seen.add(j)
   line=lines[j].strip()
   if re.search(r'(?i)pass(word)?|token|secret|csrf',line):continue
   print('AM228_LINE',j+1,line[:350])
print('AM228_READONLY_COMPLETE')
