#!/usr/bin/env python3
"""AM242 READ ONLY inspect Universal Payment Voucher, staff/customer pickers and expense routes."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_NOT_STAGING')
folders=['app/Http/Controllers/Web','app/Services','resources/views','routes']
needles=[r'Payment To',r'ON ACCOUNT',r'AGAINST REFERENCE',r'Payment Against',r'STAFF',r'CUSTOMER',r'workshop/payment',r'payment-voucher',r'function.*(?:voucher|payment|expense)',r'Select Vendor Name',r'Salary Advance']
files=[]
for folder in folders:
 for p in (root/folder).rglob('*.php'):
  if p.stat().st_size>650000:continue
  lines=p.read_text('utf8','replace').splitlines()
  matches=[(i,x) for i,x in enumerate(lines,1) if any(re.search(n,x,re.I) for n in needles)]
  if not matches:continue
  fname=p.relative_to(root).as_posix()
  if 'payment' in fname.lower() or 'expense' in fname.lower() or 'staff' in fname.lower() or 'routes/web.php'==fname:
    print('AM242_FILE',fname,'LINES',len(lines),'MATCHES',len(matches))
    for i,s in matches[:60]:
      if re.search(r'(password|secret|private.key|api[_-]?key|env\()',s,re.I):s='[REDACTED]'
      print('AM242_MATCH',fname,i,s[:500])
    files.append(fname)
print('AM242_LIST_MATCHES_DONE')
for fname in files:
 if 'payment' not in fname.lower() and 'expense' not in fname.lower():continue
 p=root/fname;lines=p.read_text('utf8','replace').splitlines()
 if len(lines)>1300:continue
 for i,s in enumerate(lines,1):
  if i>520:break
  if re.search(r'(password|secret|private.key|api[_-]?key|env\()',s,re.I):s='[REDACTED]'
  print('AM242_SOURCE',fname,i,s[:400])
print('AM242_AUDIT_COMPLETE_NO_CHANGES')
