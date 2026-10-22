#!/usr/bin/env python3
"""FIX87 read-only layout audit. Prints code only; never customer data/credentials."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1])
for name in ['resources/views/job-cards/show.blade.php','app/Http/Controllers/Web/AdminJobCardPageController.php']:
 p=root/name
 if not p.is_file(): print('MISSING',name);continue
 s=p.read_text(errors='replace').splitlines()
 print('FILE',name,'LINES',len(s),'SHA256',hashlib.sha256(p.read_bytes()).hexdigest())
 keys=['am85-part-form','am85-part-add-row','jc-parts-block','am83-workspace',
      'Labour Item Selection','OSL Jobs / Outside Services','Delete Reason',
      'reason','line-scroll','jc-itemised-billing','am86-sap-listbox',
      'parts.direct','parts.delete','parts.update','labour.direct','labour.delete',
      'labour.update','function addPart','function updatePart','function deletePart']
 hit={k:[i+1 for i,line in enumerate(s) if k.lower() in line.lower()] for k in keys}
 print('ANCHORS',repr(hit))
 if 'show.blade.php' in name:
  locs=[i for k in ['am85-part-form','am85-part-add-row','Labour Item Selection','OSL Jobs / Outside Services','Delete Reason','am83-workspace'] for i in hit.get(k,[])];
  chunks=[(max(0,i-18),min(len(s),i+66)) for i in locs if i<1500]
  chunks.append((max(0,next((i for i,line in enumerate(s) if 'FIX85_COMPACT_PARTS_TABLE_ROW' in line),len(s))-6), min(len(s),next((i for i,line in enumerate(s) if 'FIX85_COMPACT_PARTS_TABLE_ROW' in line),len(s))+40)))
 else:
  chunks=[(max(0,i-12),min(len(s),i+85)) for k in ['parts.direct','labour.direct','function addPart','function updatePart','function deletePart'] for i in hit.get(k,[])[:2]]
 indexes=set()
 for a,b in chunks:indexes.update(range(a,b))
 for i in sorted(indexes):
  line=s[i]
  if re.search(r'password|secret|bearer|api.key|private.key|token',line,re.I):continue
  print('CODE',name,i+1,line[:490].encode('ascii','backslashreplace').decode('ascii'))
print('FIX87_AUDIT_PASS')
