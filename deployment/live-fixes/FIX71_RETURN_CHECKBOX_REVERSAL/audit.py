#!/usr/bin/env python3
"""FIX71 read-only proof of JC Summary / Return-to-JC checkbox CSS."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
for name in ["resources/views/job-cards/show.blade.php","resources/views/job-cards/credit-correction.blade.php","resources/views/job-cards/return-to-jc.blade.php","resources/views/job-cards/summary-print.blade.php","resources/views/layouts/erp.blade.php"]:
 p=root/name
 if not p.exists():print("FIX71_NOT_FOUND",name);continue
 raw=p.read_bytes();txt=raw.decode('utf8',errors='replace');lines=txt.splitlines()
 print("FIX71_FILE",name,"sha256",hashlib.sha256(raw).hexdigest(),"lines",len(lines))
 q=re.compile(r'Advance.{0,32}Reversal|Advanced.{0,32}Reversal|Reversal.{0,32}Centre|Job Card Reversal|am63-consent|confirmed_finance|return-modal|FIX69|FIX63|P19_JOBCARD_ADMIN_PANELS_BOTTOM|checkbox|form.*input|input\[type',re.I)
 hits=[i for i,l in enumerate(lines) if q.search(l)]
 for i in hits[:65]:
  st=max(0,i-2);en=min(len(lines),i+3)
  print("FIX71_HIT",name,i+1)
  for k in range(st,en):
   print("FIX71_CODE",k+1,lines[k][:250])
 print("FIX71_HIT_COUNT",name,len(hits))
print("FIX71_READ_ONLY_SUCCESS")
