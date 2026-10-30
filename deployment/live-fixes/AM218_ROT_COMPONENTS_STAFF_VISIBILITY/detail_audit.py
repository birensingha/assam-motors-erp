#!/usr/bin/env python3
"""AM218 narrow read-only source excerpt; source contains no DB records in output."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM218_DETAIL_STOP_NOT_STAGING')
files={
 'API':(root/'public/legacy/api/staff-rot-v3.php',[(80,174)]),
 'PORTAL':(root/'public/legacy/staff.js',[(632,684)]),
}
for kind,(p,regions) in files.items():
 if not p.is_file():print('AM218_DETAIL_FILE_MISSING',kind);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM218_DETAIL_BEGIN',kind,len(lines))
 for low,high in regions:
  for line_no in range(low,min(high,len(lines))+1):
   content=lines[line_no-1]
   if re.search(r'(?i)password|secret|bearer|private.key|cookie|authorization|DB_PASS',content):
    content='[REDACTED_SENSITIVE_SOURCE]'
   print('AM218_SOURCE',kind,line_no,content[:1100])
 print('AM218_DETAIL_END',kind)
print('AM218_DETAIL_READ_ONLY_PASS')
