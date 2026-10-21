#!/usr/bin/env python3
"""FIX77 read-only locate Money Receipt form / JavaScript / validation / routes."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
p=root/'routes/web.php'
for i,l in enumerate(p.read_text(errors='replace').splitlines(),1):
 if re.search('money.receipt|money_receipt|receipt.create|receipt.store',l,re.I):print('FIX77_ROUTE',i,l[:350])
dirs=['resources/views','app/Http/Controllers/Web','app/Services','public/js']
targets=[]
for d in dirs:
 folder=root/d
 if not folder.exists():continue
 for p in folder.rglob('*'):
  if not p.is_file() or p.suffix not in ('.php','.js','.blade.php'):continue
  if p.stat().st_size>900000:continue
  try:s=p.read_text(errors='replace')
  except OSError:continue
  if ('Positive receipt amount required' in s or
      'CASH RECEIVED' in s or
      'ONLINE / BANK RECEIVED' in s or
      'Amount Received' in s and 'Invoice Outstanding' in s or
      'New Money Receipt' in s or
      'against_reference' in s and 'receipt' in p.name.lower()):
   path=str(p.relative_to(root))
   print('FIX77_FILE',path,'SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(s.splitlines()))
   lines=s.splitlines()
   hits=[i for i,l in enumerate(lines) if re.search('Positive receipt amount required|cash_received|online_received|amount_received|money.receipt|receipt_amount|Cash Received|Online / Bank Received|Amount Received|new Money Receipt|submit|save|number_format',l,re.I)]
   for i in hits[:75]:
    print('FIX77_HIT',path,i+1,lines[i][:280])
   targets.append((p,hits))
for p,hits in targets:
 path=str(p.relative_to(root))
 if len(hits)>0 and any(t in path for t in ('money-receipt','MoneyReceipt','money_receipt','ReceiptPage')):
  lines=p.read_text(errors='replace').splitlines()
  if len(lines)<=290:spans=[(1,len(lines))]
  else:spans=[(max(1,hits[0]-17),min(len(lines),hits[0]+88))]
  for lo,hi in spans:
   print('FIX77_SLICE',path,lo,hi)
   for j in range(lo-1,hi):
    line=lines[j]
    if re.search('password|secret|private_key|api_key|bearer|token',line,re.I):continue
    print('FIX77_CODE',j+1,line[:330])
print('FIX77_READONLY_COMPLETE')
