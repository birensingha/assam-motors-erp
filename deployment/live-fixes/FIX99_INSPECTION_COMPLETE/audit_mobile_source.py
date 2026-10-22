#!/usr/bin/env python3
"""Read-only locate CURRENT Android 30 point inspection button source across site."""
from pathlib import Path
import sys,os,re,hashlib
root=Path(sys.argv[1]);domain=root.parent
print('FIX99_MOBILE_SCAN_ROOT',root.name,'DOMAIN',domain.name)
terms=['Complete 30-Point Inspection','Complete 30–Point Inspection',
       'Complete 30‑Point Inspection','Remarks / Reading',
       'Observations / findings','Steering / Suspension',
       'inspection checklist','inspectionChecklist','submitInspection',
       'completeInspection']
found=[];n=0;extension={'.js','.jsx','.ts','.tsx','.vue','.dart','.kt','.java','.php','.html','.blade','.xml','.json'}
# Limit scan to files under user's staging/other source directories; no secrets.
for base,dirs,files in os.walk(domain):
 rel=Path(base).relative_to(domain)
 dirs[:]=[d for d in dirs if d not in ['vendor','node_modules','build','.git','.gradle','storage','cache','logs','backups','uploads','gradle'] and not d.startswith('.')]
 if len(rel.parts)>9:
  dirs.clear();continue
 for fname in files:
  p=Path(base)/fname
  if not (p.suffix in extension or p.name.endswith('.blade.php')):continue
  try:
   if p.stat().st_size>4_000_000:continue
   txt=p.read_text(errors='replace')
  except Exception:continue
  n+=1
  matches=[t for t in terms if t.lower() in txt.lower()]
  if matches:
   # For weak single "inspection checklist", return only sources containing form/button code
   if len(matches)==1 and matches[0].lower()=='inspection checklist' and 'complete' not in txt.lower():
    continue
   found.append((str(p.relative_to(domain)),matches))
   print('FIX99_MOBILE_SOURCE',p.relative_to(domain),'HITS',matches,'BYTES',p.stat().st_size)
   if any(k in matches for k in terms[:5]):
    lines=txt.splitlines()
    for i,l in enumerate(lines):
     if any(t.lower() in l.lower() for t in terms[:5]):
      print('FIX99_MOBILE_LINE',p.relative_to(domain),i+1,l[:260].encode('ascii','backslashreplace').decode('ascii'))
      if i+1>=20000:break
 if n>4500 or len(found)>95:break
print('FIX99_MOBILE_FILES_SCANNED',n,'MATCHES',len(found))
print('FIX99_MOBILE_SOURCE_AUDIT_DONE')
