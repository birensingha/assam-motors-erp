#!/usr/bin/env python3
"""FIX99 inspect actual staff assigned-inspection page and form JS; no writes."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1])
for rel in ['resources/views/staff/assigned-inspection.blade.php',
 'resources/views/staff/assigned-inspection.blade.php.php',
 'resources/views/layouts/staff.blade.php',
 'app/Support/VehicleInspectionChecklist.php']:
 p=root/rel;print('FIX99_UI_FILE',rel,'EXISTS',p.exists())
 if not p.exists():continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX99_UI_SHA',rel,hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines))
 if 'assigned-inspection' in rel or 'VehicleInspection' in rel:
  indexes=range(len(lines))
 else:
  indexes=[i for i,s in enumerate(lines) if any(t in s.lower() for t in ['complete 30','inspection','form','submit','overlay','footer','disabled','button'])]
 for i in list(indexes)[:480]:
  l=lines[i]
  if re.search(r'password|bearer|api.key|secret\s*[=:]|private.key',l,re.I):continue
  print('FIX99_UI_CODE',rel,i+1,l[:680].encode('ascii','backslashreplace').decode('ascii'))
print('FIX99_UI_AUDIT_DONE')
