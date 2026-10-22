#!/usr/bin/env python3
"""FIX107 read-only audit current live Estimate Print structure for Parts/Labour sections."""
from pathlib import Path
import sys,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
p=root/'resources/views/estimates/print.blade.php'
raw=p.read_bytes();lines=raw.decode('utf8','replace').splitlines()
print('FIX107_PRINT_SHA',hashlib.sha256(raw).hexdigest(),'LINES',len(lines))
for i,line in enumerate(lines,1):
 if (i<=24 or (i>=85 and i<=280) or 'Tax Basis' in line or 'colspan' in line):
  print('FIX107_PRINT_SOURCE',i,line[:850].encode('ascii','backslashreplace').decode('ascii'))
print('FIX107_AUDIT_DONE')
