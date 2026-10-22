#!/usr/bin/env python3
"""FIX93 read-only inspect live OSL sections and routes (no customer rows)."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);p=root/'resources/views/job-cards/show.blade.php'
lines=p.read_text(errors='replace').splitlines()
print('FIX93_SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines))
for key in ["OSL Jobs / Outside Services","New OSL Purchase","OSL Register","$am83OslLabour","$am83OslPurchases","$am83Osl","@forelse($labour","am91-add-osl","function show(row)","function send(url,bodyData","am90-table-body","jc55-osl","<div class=\"card section-gap"]:
 hits=[i for i,l in enumerate(lines) if key in l]
 print('FIX93_ANCHOR',repr(key),[i+1 for i in hits])
 print('FIX93_EXCERPT_BEGIN',key)
 ranges=[]
 if "OSL Jobs / Outside Services" in key or 'New OSL Purchase' in key or 'OSL Register' in key:
  ranges=[(max(0,h-25),min(len(lines),h+125)) for h in hits[:1]]
 elif key in ['$am83OslPurchases','$am83OslLabour','$am83Osl']:
  ranges=[(max(0,h-5),min(len(lines),h+12)) for h in hits[:5]]
 elif '@forelse($labour' in key:
  ranges=[(max(0,h-10),min(len(lines),h+62)) for h in hits[:1]]
 elif 'am91-add-osl' in key or 'function show(row)' in key:
  ranges=[(max(0,h-15),min(len(lines),h+70)) for h in hits[:1]]
 else:ranges=[]
 seen=set()
 for a,b in ranges:
  for i in range(a,b):
   if i in seen:continue
   seen.add(i);line=lines[i]
   if re.search('password|private.key|bearer|api.key|secret',line,re.I):continue
   print('FIX93_CODE',i+1,line[:460].encode('ascii','backslashreplace').decode('ascii'))
 print('FIX93_EXCERPT_END')
r=(root/'routes/web.php').read_text(errors='replace').splitlines()
for i,l in enumerate(r):
 if any(k in l.lower() for k in ['osl', 'purchase']):
  if any(k in l.lower() for k in ['route::','purchase', 'osl']):
   print('FIX93_ROUTE',i+1,l[:450])
print('FIX93_AUDIT_OK')
