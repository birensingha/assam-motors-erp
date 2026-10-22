#!/usr/bin/env python3
from pathlib import Path
import sys,hashlib,re
root=Path(sys.argv[1]);p=root/'resources/views/job-cards/show.blade.php'
s=p.read_text(errors='replace').splitlines()
print('FIX96_SOURCE_SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(s))
terms=['am82-rot-search','am82-rot-select','am83-inline-add','am83-workflow-strip','ONE WORKSPACE','Approved Estimate se transferred','am91-add-actions','class="jc-entry-form"','am82-rot-options','rot-search','am82-new-std','am82-add-labour-total','FIX95_ROT_ONE_ROW_NO_BILLING_BOX']
for term in terms:
 hits=[i for i,l in enumerate(s) if term in l]
 print('FIX96_ANCHOR',term,hits[:30])
 seen=set()
 for h in hits[:5]:
  for n in range(max(0,h-15),min(len(s),h+33)):
   if n in seen:continue
   seen.add(n)
   if re.search(r'secret|password|bearer|private.key|api.key',s[n],re.I):continue
   print('FIX96_CODE',n+1,s[n][:600].encode('ascii','backslashreplace').decode('ascii'))
print('FIX96_AUDIT_DONE')