#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
needles=re.compile(r'ROT Master|ROT Code|SAC/HSN|SAC Code|HSN / SAC|SAC|HSN|rot.master|rot-master|rot_master|labour.master',re.I)
routes=root/'routes/web.php'
for i,l in enumerate(routes.read_text(errors='replace').splitlines(),1):
 if re.search(r'rot.?master|rot.?code|labour.?master',l,re.I):print('FIX76_ROUTE',i,l[:260])
folders=['app/Http/Controllers/Web','app/Services','resources/views/masters','resources/views/configuration','resources/views/rot','resources/views/labour','resources/views','database/migrations']
rows=[]
for d in folders:
 path=root/d
 if not path.exists():continue
 for p in path.rglob('*'):
  if not p.is_file() or p.suffix not in ('.php','.js','.sql'):continue
  if len(p.parts)>14:continue
  s=p.read_bytes().decode('utf8','replace');lines=s.splitlines()
  hits=[(i,l) for i,l in enumerate(lines,1) if needles.search(l)]
  if hits and ('views' not in str(p) or re.search('rot|labour|master|catalog|invoice|estimate|job',str(p),re.I)):
   rows.append((str(p.relative_to(root)),p,len(lines),hits,hashlib.sha256(p.read_bytes()).hexdigest()))
# Deduplicate and limit
seen=set()
for path,p,count,hits,sha in rows:
 if path in seen:continue
 seen.add(path)
 print('FIX76_SOURCE',path,'LINES',count,'HITS',len(hits),'SHA',sha)
 for i,l in hits[:16]:
  print('FIX76_HIT',i,l[:240].strip())
for path,p,count,hits,sha in rows:
 if not re.search('rot.*(master|catalog)|(?:master|catalog).*rot',path,re.I):continue
 print('FIX76_DEEP_SOURCE',path)
 lines=p.read_bytes().decode('utf8','replace').splitlines()
 for i,l in enumerate(lines[:min(260,len(lines))],1):
  if re.search('password|secret|token|API_KEY',l,re.I):continue
  print('FIX76_LINE',i,l[:290])
print('FIX76_SOURCE_AUDIT_COMPLETE')
