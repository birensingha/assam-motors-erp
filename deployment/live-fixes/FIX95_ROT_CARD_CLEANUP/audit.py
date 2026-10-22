#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);p=root/'resources/views/job-cards/show.blade.php'
s=p.read_text(errors='replace').splitlines()
print('FIX95_VIEW_SHA256',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(s))
terms=['Customer Billing','Original ROT/Labour','Set No Charge','Internal','rot-actions','Re-open + Reset End Time','Edit ROT Start','Re-open','Reopen','am-v647-time-editor','rot-billing','rot-bill','billable','rot-card']
for k in terms:
 hits=[i for i,l in enumerate(s) if k.lower() in l.lower()]
 print('FIX95_ANCHOR',k,hits[:35])
 windows=[]
 if k.lower() in ['customer billing','set no charge','original rot/labour','re-open + reset end time','rot-actions','rot-billing','am-v647-time-editor']:
  windows=[(max(0,i-17),min(len(s),i+40)) for i in hits[:8]]
 seen=set()
 for a,b in windows:
  for i in range(a,b):
   if i in seen:continue
   seen.add(i)
   if re.search(r'password|secret|bearer|private.key|api.key',s[i],re.I):continue
   print('FIX95_CODE',i+1,s[i][:550].encode('ascii','backslashreplace').decode('ascii'))
print('FIX95_AUDIT_COMPLETE')