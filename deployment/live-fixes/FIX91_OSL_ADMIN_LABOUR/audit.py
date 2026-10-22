#!/usr/bin/env python3
"""FIX91 staging route and Labour/OSL authorization audit (read-only)."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1])
assert (root/'artisan').is_file()
v=root/'resources/views/job-cards/show.blade.php'
s=v.read_text(errors='replace').splitlines()
print('FIX91_SHA',hashlib.sha256(v.read_bytes()).hexdigest(),'LINES',len(s))
for a,b in [(1090,1135),(1210,1270)]:
 print('FIX91_VIEW_RANGE',a,b)
 for i in range(a-1,min(b,len(s))):
  if re.search('password|secret|token|private.key|api.key|bearer',s[i],re.I):continue
  print('FIX91_V',i+1,s[i][:800].encode('ascii','backslashreplace').decode('ascii'))
for rel in ['routes/web.php','app/Http/Controllers/Web/AdminJobCardOslLineController.php',
 'app/Http/Controllers/Web/AdminJobCardBillingCorrectionPageController.php',
 'app/Models/ErpAuthUser.php','app/Http/Controllers/Web/AdminJobCardPageController.php']:
 p=root/rel
 if not p.is_file():print('FIX91_MISSING',rel);continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX91_FILE',rel,'LINES',len(lines))
 if rel.endswith('ErpAuthUser.php'):
  for i,line in enumerate(lines):
   if 'function isAdmin' in line or 'function can' in line:
    for j in range(max(0,i-4),min(len(lines),i+25)):
     print('FIX91_AUTH',j+1,lines[j][:260])
 elif rel.endswith('routes/web.php'):
  for i,line in enumerate(lines):
   if any(x in line for x in ['osl-line','osl.save','job-line','labour.delete','labour.destroy','labour.direct','job-cards.labour','AdminJobCardOslLineController']):
    print('FIX91_ROUTE',i+1,' '.join(lines[max(0,i-1):min(len(lines),i+3)]).strip()[:650])
 elif 'AdminJobCardOslLineController.php' in rel:
  for i,line in enumerate(lines):
   if 'function ' in line or 'abort_unless' in line or 'job_card_change_audits' in line or "'reason'" in line:
    print('FIX91_OSL_CTRL',i+1,line[:280])
 elif 'AdminJobCardPageController' in rel:
  for i,line in enumerate(lines):
   if ('function ' in line and any(z in line.lower() for z in ['labour','admin'])) or 'isAdmin()' in line:
    print('FIX91_JC_CTRL',i+1,line[:300])
# Additional strict financial controls / OSL schema checks.
p=root/'app/Http/Controllers/Web/AdminWorkshopControlPageController.php'
if p.exists():
 lines=p.read_text(errors='replace').splitlines()
 for i,v in enumerate(lines):
  if 'function updateJobLine' in v:
   print('FIX91_FIN_EDITOR_LINES',i+1)
   for j in range(max(0,i-3),min(i+115,len(lines))):
    print('FIX91_FIN',j+1,lines[j][:450].encode('ascii','backslashreplace').decode('ascii'))
p=root/'routes/web.php'
if p.exists():
 lines=p.read_text(errors='replace').splitlines()
 for a,b in [(16,53),(176,210)]:
  for j in range(a-1,min(b,len(lines))):
   print('FIX91_ROUTE_CONTEXT',j+1,lines[j][:350])
for rel in ['app/Services/JobCardTaxTotals.php',
            'app/Http/Controllers/Web/JobCardRotAlterationController.php']:
 p=root/rel
 print('FIX91_FILE_EXISTS',rel,p.exists())
if (root/'app/Services/JobCardTaxTotals.php').exists():
 p=root/'app/Services/JobCardTaxTotals.php'
 lines=p.read_text(errors='replace').splitlines()
 for j,line in enumerate(lines):
  if 'function forJob' in line:
   for i in range(j,min(j+35,len(lines))):print('FIX91_TAX',i+1,lines[i][:330])
print('FIX91_AUDIT_PASS')
