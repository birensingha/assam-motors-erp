#!/usr/bin/env python3
"""AM251 read-only capture exact part/customer delete and UI routes for safe financial guard."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM251_STOP_NOT_STAGING')
chunks={
 'app/Http/Controllers/Web/AdminNativeModuleController.php':[(800,855),(904,954),(994,1045)],
 'resources/views/dashboard/parts.blade.php':[(1,270)],
 'resources/views/dashboard/customers.blade.php':[(1,240)],
 'routes/web.php':[(75,190)],
 'app/Http/Controllers/Web/NewAdminConsoleController.php':[(1,85)]
}
for name,sections in chunks.items():
 p=root/name
 if not p.is_file():continue
 src=p.read_text('utf8','replace').splitlines()
 for a,b in sections:
  print('AM251_SECTION',name,a,b)
  for i in range(a-1,min(b,len(src))):
   if re.search(r'(password|secret|bearer|authorization)',src[i],re.I):continue
   print('AM251_LINE',i+1,src[i][:850])
print('AM251_MASTER_ROUTE_INDEX')
for name in ['routes/web.php','resources/views/dashboard/configuration.blade.php']:
 p=root/name
 if not p.is_file():continue
 for i,line in enumerate(p.read_text('utf8','replace').splitlines()):
  if re.search(r'(part|customer|expense|vendor|head).*(delete|destroy)|(delete|destroy).*(part|customer|expense|vendor|head)',line,re.I):
   print('AM251_ROUTE',name,i+1,line[:650])

for name in ['resources/views/admin/native/parts.blade.php','resources/views/admin/native/labour.blade.php']:
 p=root/name
 if not p.is_file():continue
 print('AM252_VIEW_BEGIN',name)
 for i,line in enumerate(p.read_text('utf8','replace').splitlines()):
  if re.search(r'password|secret|bearer|authorization',line,re.I):continue
  if re.search(r'delet|activ|edit|error|success|form|route',line,re.I):
   print('AM252_VIEW_LINE',i+1,line[:800])
 print('AM252_VIEW_END',name)
print('AM251_NO_DB_CALLS')
