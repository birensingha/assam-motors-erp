#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1])
files=['resources/views/estimates/create.blade.php',
       'resources/views/layouts/erp.blade.php',
       'public/js/erp.js',
       'public/css/erp.css',
       'resources/js/estimate.js']
for rel in files:
 p=root/rel
 print('FIX101_CHECK_FILE',rel,p.exists())
 if not p.exists():continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX101_DETAIL_SHA',rel,hashlib.sha256(p.read_bytes()).hexdigest(),'lines',len(lines))
 if 'estimates/create' in rel:
  windows=[(0,len(lines))]
 else:
  hits=[i for i,l in enumerate(lines) if any(q.lower() in l.lower() for q in ['line-scroll','estimate','autocomplete','suggestion','dropdown'])]
  windows=[(max(0,i-6),min(len(lines),i+11)) for i in hits[:15]]
 indexes=set()
 for a,b in windows:indexes.update(range(a,b))
 for i in sorted(indexes):
  l=lines[i]
  if re.search(r'password|bearer|secret\s*[=:]|private.key',l,re.I):continue
  print('FIX101_DETAIL',rel,i+1,l[:600].encode('ascii','backslashreplace').decode('ascii'))
# Find scripts linked to estimate by filename.
for folder in ['public','resources']:
 area=root/folder
 if not area.exists():continue
 paths=[]
 for p in area.rglob('*'):
  if p.is_file() and p.suffix.lower() in ['.js','.css','.php'] and p.stat().st_size<500000:
   try:
    s=p.read_text(errors='replace')
   except:continue
   if any(t in s for t in ['estimate-ref-dropdown','estimate-autocomplete','estimate-suggestions',
                           'estimate-line-table','am-estimate-input','AM_ESTIMATE_DATA','estimate-ref-search']):
    paths.append(str(p.relative_to(root)))
 print('FIX101_ESTIMATE_ASSETS',folder,paths[:50])
print('FIX101_DETAIL_COMPLETE')
