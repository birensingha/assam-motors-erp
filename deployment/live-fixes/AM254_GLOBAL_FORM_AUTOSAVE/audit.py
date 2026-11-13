#!/usr/bin/env python3
"""Read-only inspect STAGING layout and common ERP form patterns; no user data or credentials."""
from pathlib import Path
import re,sys,collections
if len(sys.argv)!=2:raise SystemExit('AM254_USAGE audit.py STAGING_ROOT')
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').exists():raise SystemExit('STOP_AM254_NOT_STAGING')
views=root/'resources/views'
for rel in ['layouts/erp.blade.php','layouts/staff.blade.php','layouts/staff-portal.blade.php','layouts/app.blade.php','auth/login.blade.php','estimates/create.blade.php']:
 p=views/rel
 if not p.is_file():print('AM254_LAYOUT_MISSING',rel);continue
 ls=p.read_text('utf8','replace').splitlines()
 print('AM254_VIEW',rel,'TOTAL_LINES',len(ls))
 if rel.startswith('layouts'):
  relevant=set()
  for i,l in enumerate(ls):
   if re.search(r'@auth|@guest|@stack|@yield|</body>|<body|<script|auth.*id|logout|@endsection|@section|asset\(',l,re.I):
    relevant.update(range(max(0,i-2),min(len(ls),i+3)))
  for i in sorted(relevant)[-150:]:
   l=ls[i].strip()
   if re.search(r'password|secret|token|private.key|csrf_token|APP_KEY',l,re.I):l='[REDACTED]'
   print('AM254_LAYOUT_CODE',rel,i+1,l[:470])
 elif rel=='estimates/create.blade.php':
  for i in (73,74,75,76,287,288,291,292,293,294,295,329,330,335,339,340,387,388):
   if i<len(ls):print('AM254_ESTIMATE_CODE',i+1,ls[i].strip()[:440])
count=collections.Counter()
forms=[]
for p in views.rglob('*.blade.php'):
 if p.stat().st_size>800000:continue
 s=p.read_text('utf8','replace')
 found=len(re.findall(r'<form\b',s,re.I))
 if found:
  rel=str(p.relative_to(views))
  count[rel.split('/')[0]]+=found
  if len(forms)<130:forms.append((rel,found, bool(re.search(r'@extends\([\'"]layouts\.erp',s)),bool(re.search(r'@extends\([\'"]layouts\.staff',s))))
print('AM254_FORM_COUNT_BY_FIRST_FOLDER',dict(count))
for rel,n,erp,staff in forms:print('AM254_FORM_FILE',rel,n,'ERP_LAYOUT',erp,'STAFF_LAYOUT',staff)
print('AM254_AUDIT_COMPLETED_READ_ONLY')
