#!/usr/bin/env python3
"""Find exact ROT Edit Labour modal used in screenshot (read only)."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
needles=['Edit Labour','Labour Master mein permanent save hoga','Save Labour','STANDARD HOURS','ROT DESCRIPTION']
for folder in ['resources/views','public','app/Http/Controllers','resources/js']:
 d=root/folder
 if not d.exists():continue
 matched=0
 for p in d.rglob('*'):
  if not p.is_file() or p.suffix not in ['.blade.php','.php','.js','.html','.vue']:continue
  if p.stat().st_size>800000:continue
  s=p.read_text(errors='replace')
  terms=[n for n in needles if n.casefold() in s.casefold()]
  if not terms:continue
  lines=s.splitlines()
  print('FIX76_MODAL_FILE',str(p.relative_to(root)),'SHA256',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines),'matches',terms)
  hits=[i for i,l in enumerate(lines) if any(n.casefold() in l.casefold() for n in terms)]
  for i in hits[:12]:
   print('FIX76_MODAL_LINE',i+1,lines[i][:260])
  matched+=1
 print('FIX76_MODAL_FOLDER_MATCHES',folder,matched)
print('FIX76_MODAL_READONLY_DONE')
