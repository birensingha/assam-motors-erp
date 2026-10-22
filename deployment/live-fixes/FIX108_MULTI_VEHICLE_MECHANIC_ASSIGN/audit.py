#!/usr/bin/env python3
"""FIX108 read-only audit of mechanic assignment guards and route/controller/service."""
from pathlib import Path
import re,hashlib,sys
root=Path(sys.argv[1]);assert (root/'artisan').exists()
roots=['app/Services','app/Http/Controllers','resources/views/job-cards','routes/web.php']
terms=re.compile(r'(already assigned|assigned to another|another job|another rot|already working|busy mechanic|mechanic.*(assign|busy|check.in|available)|(?:assign|available|check.in).*mechanic|active.rot|running.rot|active.session|whereHas.*rot|staff.*assigned|assigned_staff|mechanic_id|assigned_staff_id|conflict)',re.I)
for path in roots:
 p=root/path
 if not p.exists():continue
 ps=[p] if p.is_file() else list(p.rglob('*.php'))
 for q in ps:
  if q.stat().st_size>900000:continue
  lines=q.read_text(errors='replace').splitlines()
  hits=[i for i,x in enumerate(lines) if terms.search(x)]
  if not hits:continue
  rel=str(q.relative_to(root))
  if len(hits)>100 and rel.endswith('show.blade.php'):hits=hits[:35]
  if len(hits)>90:hits=hits[:90]
  print('FIX108_FILE',rel,'HASH',hashlib.sha256(q.read_bytes()).hexdigest(),'LINES',len(lines),'HITS',len(hits))
  selections=set()
  for i in hits:
   selections.update(range(max(0,i-3),min(len(lines),i+5)))
  if len(selections)>390: selections=set(sorted(selections)[:390])
  for i in sorted(selections):
   x=lines[i]
   if re.search(r'password|secret\s*[=:]|private.key|api.key|bearer|token\s*[=:]',x,re.I):continue
   print('FIX108_SOURCE',rel,i+1,x[:650].encode('ascii','backslashreplace').decode('ascii'))
print('FIX108_AUDIT_COMPLETE')
