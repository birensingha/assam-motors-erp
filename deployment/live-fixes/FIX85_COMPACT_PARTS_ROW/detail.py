#!/usr/bin/env python3
from pathlib import Path
import hashlib,sys
r=Path(sys.argv[1]);p=r/'resources/views/job-cards/show.blade.php'
s=p.read_bytes().decode('utf8','surrogateescape').splitlines()
print('FIX85_TARGET_SHA',hashlib.sha256(p.read_bytes()).hexdigest(),len(s))
for i in range(582,min(735,len(s))):
 print('FIX85_T',i+1,s[i][:550].encode('ascii','backslashreplace').decode('ascii'))
print('FIX85_TARGET_AUDIT_DONE')
