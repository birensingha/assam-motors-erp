#!/usr/bin/env python3
"""FIX78 read-only print templates and existing @page chrome URL footer issue.
No financial data or static print contents modified.
"""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
folders=[root/'resources/views',root/'public/legacy/workshop',root/'public/legacy/admin']
files=[]
for folder in folders:
 if not folder.exists():continue
 for p in folder.rglob('*'):
  if not p.is_file() or p.suffix.lower() not in ('.php','.html','.blade.php','.css','.js'):continue
  if 'vendor/' in str(p) or p.stat().st_size>600000:continue
  rel=str(p.relative_to(root))
  if re.search(r'(print|invoice|credit-note|estimate|receipt|job-card|pdf)',rel,re.I):
   text=p.read_text(errors='replace')
   markers=[(i,l[:210]) for i,l in enumerate(text.splitlines(),1) if re.search(r'@page|@media print|footer|www\.assammotors\.com|window\.print|<title|</body>|PRINT_FORMAT',l,re.I)]
   if markers:
    files.append((rel,p,markers))
for rel,p,markers in files[:85]:
 raw=p.read_bytes()
 print('FIX78_CANDIDATE',rel,'SHA',hashlib.sha256(raw).hexdigest(),'LINES',len(raw.splitlines()))
 for n,t in markers[:12]:
  if not re.search(r'(csrf|password|secret|token)',t,re.I):print('FIX78_MARK',n,t)
print('FIX78_TEMPLATE_COUNT',len(files))
for rel,p,markers in files:
 if re.search(r'(invoices/print|invoices/credit-note|job-cards/summary-print|money-receipts/print|estimates/print|purchases/.+print)',rel,re.I):
  s=p.read_text(errors='replace').splitlines()
  print('FIX78_TARGET',rel,'SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINECOUNT',len(s))
  for n,l in enumerate(s,1):
   if n<=65 or n>=len(s)-24:
    if not re.search(r'csrf|password|secret|token',l,re.I): print('FIX78_SOURCE',rel,n,l[:275])
print('FIX78_READ_ONLY_PASS')
