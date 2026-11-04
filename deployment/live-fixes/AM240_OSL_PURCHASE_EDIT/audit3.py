#!/usr/bin/env python3
"""AM240 inspect existing OSL edit route, controller and template. READ ONLY."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM240_NOT_STAGING')
patterns=('erp.fix48.osl-edit','fix48.osl-edit','fix48/osl','OSL Purchase Edit','Edit OSL Purchase','function editOsl')
matches=[]
for d in ('routes','app/Http/Controllers','resources/views'):
 for p in (root/d).rglob('*.php'):
  if p.stat().st_size>350000:continue
  src=p.read_text('utf8','replace').splitlines()
  for i,line in enumerate(src):
   if any(needle.lower() in line.lower() for needle in patterns):
    rel=p.relative_to(root).as_posix()
    matches.append((rel,i))
    print('AM240_OSL_MATCH',rel,i+1,line[:400])
printed=set()
for rel,i in matches:
 p=root/rel
 if not ('Controller' in rel or 'routes/' in rel):continue
 if rel in printed:continue
 printed.add(rel)
 src=p.read_text('utf8','replace').splitlines()
 print('AM240_OSL_TARGET_FILE',rel,'LINES',len(src))
 sections=[j for j,s in enumerate(src) if re.search(r'function.*(?:osl|Osl)|FIX48|fix48|purchase.*edit',s,re.I)]
 printed_lines=set()
 for point in sections[:12]:
  for ix in range(max(0,point-5),min(len(src),point+105)):
   if ix in printed_lines:continue
   printed_lines.add(ix)
   s=src[ix]
   if re.search(r'(password|secret|private.key|api[_-]?key|env\()',s,re.I):s='[REDACTED]'
   print('AM240_OSL_TARGET',rel,ix+1,s[:380])
print('AM240_OSL_ROUTE_READ_ONLY_PASS')
