#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
p=Path(sys.argv[1])/'resources/views/job-cards/show.blade.php'
s=p.read_text(errors='replace').splitlines()
print('FIX90_SOURCE_SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(s))
patterns=('am82-finance-form','am83-grid-header','@forelse($am83RotLabour','class="rot-card','rotByLabour','jc-labour-block','FIX89_ARROW_TYPE_PARTS_DROPDOWN')
for k in patterns: print('FIX90_ANCHOR',repr(k),[i+1 for i,v in enumerate(s) if k in v][:30])
indexes=set()
for i,v in enumerate(s):
 if '@forelse($am83RotLabour' in v or 'class="am82-finance-form"' in v or 'class="am83-grid-header"' in v:
  indexes.update(range(max(0,i-10),min(len(s),i+77)))
for i in sorted(indexes):
 if i>1400:continue
 v=s[i]
 if re.search(r'password|token|private.key|api.key|secret|bearer',v,re.I):continue
 print('FIX90_CODE',i+1,v[:550].encode('ascii','backslashreplace').decode('ascii'))
print('FIX90_AUDIT_COMPLETE')
