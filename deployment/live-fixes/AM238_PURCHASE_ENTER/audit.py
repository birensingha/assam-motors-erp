#!/usr/bin/env python3
"""AM238 read-only inspect current purchase entry keyboard + JC handlers."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM238_NOT_STAGING')
for rel in ('public/js/purchase-entry.js','resources/views/purchases/create.blade.php'):
 p=root/rel
 if not p.is_file():raise SystemExit('AM238_MISSING '+rel)
 lines=p.read_text('utf8','replace').splitlines()
 print('AM238_FILE',rel,'TOTAL_LINES',len(lines))
 if rel.endswith('.js'):
  wanted=[i for i,s in enumerate(lines) if re.search(r'function wireEnterFlow|function wireJobFilter|function wire\(|function addLine|key(?:down|up)|\bEnter\b|am234|AM234|\.allocation|am60|\.job-filter',s,re.I)]
  blocks=[]
  for index in wanted:
   if index>30:blocks.append((max(0,index-5),min(len(lines),index+10)))
  blocks.append((190,min(len(lines),320)))
  blocks.append((430,min(len(lines),560)))
  for a,b in sorted(blocks):
   if a<0:continue
   for i in range(a,b):
    if i>=len(lines):break
    # De-dup adjacent sections: read-only text, no secrets.
    if not any(x<=i<y for x,y in blocks if x<a and y>i):
     s=lines[i]
     if re.search(r'password|secret|api[_-]?key|private[_-]?key|env\(',s,re.I):s='[REDACTED]'
     print('AM238_JS',i+1,s[:320])
 else:
  for i,s in enumerate(lines,1):
   if 423<=i<=460 or (re.search('purchase-entry.js|AM234_PURCHASE_JC_PICKER|line-template',s) and i>350):
    print('AM238_VIEW',i,s[:450])
print('AM238_SOURCE_AUDIT_PASS_NO_WRITES')
