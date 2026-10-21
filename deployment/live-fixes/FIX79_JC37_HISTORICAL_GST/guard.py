#!/usr/bin/env python3
"""FIX79 locate the historical GST guard and exact methods; no files modified."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
key='Historical GST requires review'
for base in ['resources/views','app/Http/Controllers','app/Services','public/legacy/workshop','public/legacy/api']:
 d=root/base
 if not d.is_dir():continue
 for p in d.rglob('*'):
  if not p.is_file() or p.suffix not in ('.php','.js','.html','.blade.php'):continue
  if p.stat().st_size>1200000:continue
  s=p.read_text(errors='replace')
  if key not in s and 'existing gross amount may ALREADY INCLUDE GST' not in s:continue
  lines=s.splitlines()
  print('FIX79_GUARD_FILE',p.relative_to(root),'sha256',hashlib.sha256(p.read_bytes()).hexdigest(),'total_lines',len(lines))
  indices=[i for i,x in enumerate(lines) if key in x or 'already include GST' in x]
  for i in indices[:10]:
   for k in range(max(0,i-42),min(len(lines),i+44)):
    if re.search('password|secret|private.key|api.key|bearer',lines[k],re.I):continue
    print('FIX79_GUARD_SOURCE',k+1,lines[k][:320])
print('FIX79_GUARD_SEARCH_COMPLETE')
