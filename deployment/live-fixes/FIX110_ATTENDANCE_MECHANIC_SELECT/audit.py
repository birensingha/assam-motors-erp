#!/usr/bin/env python3
"""FIX110 read-only diagnose attendance Status native select and mechanic picker options."""
from pathlib import Path
import re,sys,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
needles=[
 r'Edit Attendance',r'Save Correction',r'Reset Attendance',r'Correction Reason',
 r'Change Mechanic',r'New Mechanic',r'AM109_MECHANIC_COMBO',
 r'AM109',r'FIX109_MECHANIC_COMBO',
 r'mechanic-picker',r'change-mechanic',r'AttendanceController',
 r'function (?:index|attendance|show|changeMechanic)\(',r'\$staff\s*=',
 r'(?:status|attendance_status).*Absent',r'(?:Present|Absent|Late).*option',
 r'if \(\!\$staff',r'->whereHas\('
]
rx=re.compile('|'.join(needles),re.I)
areas=['resources/views','app/Http/Controllers','app/Services','routes/web.php','public/js','public/css']
found=[]
for loc in areas:
 p=root/loc
 if not p.exists():continue
 files=[p] if p.is_file() else list(p.rglob('*'))
 for q in files:
  if not q.is_file() or q.suffix not in ['.php','.js','.css']:continue
  if q.stat().st_size>1100000:continue
  ls=q.read_text(errors='replace').splitlines()
  hits=[i for i,l in enumerate(ls) if rx.search(l)]
  if hits:found.append((len(hits),q,ls,hits))
for n,p,ls,hits in sorted(found,key=lambda x:(-x[0],str(x[1])))[:45]:
 rel=str(p.relative_to(root))
 print('FIX110_FILE',rel,'HITS',n,'LINES',len(ls),'SHA',hashlib.sha256(p.read_bytes()).hexdigest())
 # Most useful screenshots/logic: output around controls, source creation and filters
 selected=set()
 for i in hits[:24]:selected.update(range(max(0,i-4),min(len(ls),i+7)))
 for i in sorted(selected)[:220]:
  line=ls[i]
  if re.search('password|private.key|secret\\s*[=:]|bearer|api.key',line,re.I):continue
  print('FIX110_CODE',rel,i+1,line[:980].encode('ascii','backslashreplace').decode('ascii'))
print('FIX110_READ_ONLY_FINISHED')
