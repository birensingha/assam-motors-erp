#!/usr/bin/env python3
"""FIX75 read-only Job Card Show action button source audit."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]); assert (root/'artisan').is_file()
p=root/'resources/views/job-cards/show.blade.php'
raw=p.read_bytes();lines=raw.decode('utf-8','surrogateescape').splitlines()
print('FIX75_SOURCE_SHA256',hashlib.sha256(raw).hexdigest(),'TOTAL_LINES',len(lines))
for i,line in enumerate(lines,1):
 if re.search(r'Estimate\s*(/|or|&)|Revise|Reverse|Purchase for Job|Expense(s)? for Job|One-Page Printable Job Card Summary|Billing Correction / Return to JC|section\(|<header|toolbar|content|breadcrumb',line,re.I):
  print('FIX75_ACTION_ANCHOR',i,line[:240].encode('utf-8','backslashreplace').decode('utf-8'))
for k,l in enumerate(lines[:210],1):
 print('FIX75_SOURCE_LINE',k,l[:280].encode('utf-8','backslashreplace').decode('utf-8'))
print('FIX75_READ_ONLY_SUCCESS')
