#!/usr/bin/env python3
"""FIX100 read-only Parts select markup/JS/CSS audit."""
from pathlib import Path
import re,hashlib,sys
root=Path(sys.argv[1]);p=root/'resources/views/job-cards/show.blade.php'
lines=p.read_text(errors='replace').splitlines()
print('FIX100_SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines))
terms=['am89-part','am85-part','am85-part-search','am85-part-select','am85-parts','FIX89_ARROW_TYPE_PARTS_DROPDOWN','am89','part-picker','Select Part','am96-shell','am85-part-form','am85-part-add-row','am87-part']
emit=set()
for term in terms:
 hits=[i for i,l in enumerate(lines) if term.lower() in l.lower()]
 print('FIX100_MATCH',term,[h+1 for h in hits[:70]])
 for h in hits[:8]:
  extra=100 if 'FIX89_' in term else 27
  emit.update(range(max(0,h-13),min(len(lines),h+extra)))
for i in sorted(emit):
 l=lines[i]
 if re.search(r'password|bearer|secret\s*[=:]|private.key|api.key',l,re.I):continue
 print('FIX100_LINE',i+1,l[:800].encode('ascii','backslashreplace').decode('ascii'))
print('FIX100_AUDIT_DONE')
