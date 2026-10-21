#!/usr/bin/env python3
"""Read-only FIX72 exact implementation preflight: lifecycle, original invoice print, current financial row identity."""
from pathlib import Path
import hashlib,sys,re
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
files=[
('app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php','reopenClosed',95),
('app/Http/Controllers/Web/AdminInvoiceCreditNoteController.php','function post',140),
('app/Http/Controllers/Web/AdminReturnToJobCardController.php','function post',90),
('resources/views/invoices/print.blade.php','@php',100),
('resources/views/invoices/credit-note.blade.php','@php',105),
('app/Services/JobCardTaxTotals.php','class JobCardTaxTotals',125),
('resources/views/job-cards/credit-correction.blade.php','am63CanReturn',25),
]
for name,anchor,n in files:
 p=root/name
 if not p.exists():print('FIX72_MISSING',name);continue
 text=p.read_bytes().decode('utf8',errors='replace');rows=text.splitlines()
 print('FIX72_SOURCE',name,'hash',hashlib.sha256(p.read_bytes()).hexdigest(),'lines',len(rows))
 at=next((i for i,x in enumerate(rows) if anchor in x),-1)
 if at<0:continue
 for i in range(max(0,at-3),min(len(rows),at+n)):
  x=rows[i]
  if re.search(r'password|secret|private_key|api_key|authorization',x,re.I):continue
  print('FIX72_CODE',name,i+1,x[:320])
print('FIX72_SOURCE_READONLY_PASS')
