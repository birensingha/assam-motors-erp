#!/usr/bin/env python3
"""AM253 read-only inspect Parts Master delete control and expense head/customer delete links."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM253_STOP_NOT_STAGING')
for name in ['resources/views/admin/native/parts.blade.php','resources/views/admin/native/labour.blade.php',
             'resources/views/dashboard/configuration.blade.php']:
 p=root/name
 if not p.is_file():continue
 print('AM253_VIEW_BEGIN',name)
 lines=p.read_text('utf8','replace').splitlines()
 for i,line in enumerate(lines):
  if re.search(r'delet|activ|edit|route|form|<button|error|success',line,re.I):
   print('AM253_LINE',i+1,line[:900])
 print('AM253_VIEW_END',name)
print('AM253_ROUTE_CHECK_BEGIN')
for name in ['routes/web.php','routes/api.php']:
 p=root/name
 if not p.is_file():continue
 for i,line in enumerate(p.read_text('utf8','replace').splitlines()):
  if re.search(r'(customer|vendor|expense|head|part|labour).*(delete|destroy)|(delete|destroy).*(customer|vendor|expense|head|part|labour)',line,re.I):
   print('AM253_ROUTE',name,i+1,line[:420])
print('AM253_READONLY_COMPLETE')
