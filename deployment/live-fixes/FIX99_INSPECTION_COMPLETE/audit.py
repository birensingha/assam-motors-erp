#!/usr/bin/env python3
"""FIX99 read-only find actual 30-point inspection complete button, JS handler and backend."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
areas=[root/'android-staff-app/app/src/main',root/'resources/views/staff',root/'resources/views',
       root/'app/Http/Controllers',root/'routes',root/'public']
phrases=('Complete 30-Point Inspection','Complete 30 Point Inspection',
         'Complete 30‑Point Inspection','Complete 30–Point Inspection',
         'completeInspection','submitInspection','complete30Point',
         'inspectionComplete','30-Point Inspection','30 point inspection')
found=[]
scanned=0
for area in areas:
 if not area.exists():continue
 for p in area.rglob('*'):
  if not p.is_file() or p.suffix not in ('.kt','.java','.html','.js','.php') or p.stat().st_size>3000000:continue
  if any(x in p.parts for x in ('vendor','node_modules','.gradle','build','storage')):continue
  scanned+=1
  try: lines=p.read_text(errors='replace').splitlines()
  except Exception:continue
  hits=[i for i,l in enumerate(lines) if any(t.lower() in l.lower() for t in phrases)]
  if hits:
   found.append((str(p.relative_to(root)),len(lines),hits))
   print('FIX99_FILE',p.relative_to(root),'LINES',len(lines),'SHA',hashlib.sha256(p.read_bytes()).hexdigest())
   keep=set()
   for i in hits[:12]:keep.update(range(max(0,i-16),min(len(lines),i+38)))
   for i in sorted(keep):
    if re.search(r'password|bearer|private.key|api.key|secret\\s*[=:]|jwt.secret',lines[i],re.I):continue
    print('FIX99_CODE',p.relative_to(root),i+1,lines[i][:350].encode('ascii','backslashreplace').decode('ascii'))
   if len(found)>=12:break
 if len(found)>=12:break
print('FIX99_FILES_SCANNED',scanned,'EXACT_MATCH_FILES',len(found))
# Find route declarations for current inspection app endpoints
for rel in ('routes/api.php','routes/web.php'):
 p=root/rel
 if not p.exists():continue
 lines=p.read_text(errors='replace').splitlines()
 for i,l in enumerate(lines):
  if 'inspection' in l.lower() and any(w in l.lower() for w in ('route::','post(', 'put(', 'patch(', 'delete(')):
   print('FIX99_ROUTE',rel,i+1,l[:350])
print('FIX99_AUDIT_DONE')
