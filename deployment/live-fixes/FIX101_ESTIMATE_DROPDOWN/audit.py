#!/usr/bin/env python3
"""FIX101 read-only estimate item dropdown bounds and event handling audit."""
from pathlib import Path
import re,hashlib,sys
root=Path(sys.argv[1]); assert (root/'artisan').exists()
queries=[
'Client preview is only convenience',
'Estimate Lines',
'Search ROT code or operation',
'Server recalculates discount',
'Assam default GST split',
'addEstimateLine',
'estimate-autocomplete',
'rot-suggest',
]
dirs=['resources/views','public','resources/js','app/Http/Controllers']
hits=[]
for rel in dirs:
 base=root/rel
 if not base.exists():continue
 for p in base.rglob('*'):
  if not p.is_file() or p.suffix.lower() not in ('.php','.js','.css','.html','.blade'):continue
  if any(k in p.parts for k in ['vendor','node_modules','storage','build','cache']):continue
  try:
   if p.stat().st_size>1_800_000:continue
   lines=p.read_text(errors='replace').splitlines()
  except Exception:continue
  found=[(i,q) for i,x in enumerate(lines) for q in queries if q.lower() in x.lower()]
  if found:
   hits.append((str(p.relative_to(root)),p,lines,found))
for label,p,lines,found in hits[:15]:
 print('FIX101_FILE',label,'SHA256',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines))
 print('FIX101_MATCHES',[(i+1,q) for i,q in found[:40]])
 excerpt=set()
 for i,q in found[:10]:excerpt.update(range(max(0,i-18),min(len(lines),i+40)))
 for i in sorted(excerpt):
  l=lines[i]
  if re.search(r'password|bearer|api.key|secret\s*[=:]|private.key',l,re.I):continue
  print('FIX101_SOURCE',label,i+1,l[:570].encode('ascii','backslashreplace').decode('ascii'))
print('FIX101_FOUND_FILES',len(hits))
print('FIX101_AUDIT_DONE')
