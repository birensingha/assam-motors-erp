#!/usr/bin/env bash
set -euo pipefail
cd "${1:?STAGING_ROOT}";test -f artisan
echo AM277_PARTS_SEARCH_AUDIT
grep -RIn --include='*.php' --include='*.js' -m 2 -E 'vehicle_make|vehicle_model' resources/views/job-cards resources/views/estimates resources/views/purchases resources/views/admin/native app/Http/Controllers/Web app/Services 2>/dev/null | head -110 || true
echo AM277_PART_LOOKUP_ROUTES
php artisan route:list --path=parts | head -35
python3 - <<'PY'
from pathlib import Path
for base in ['resources/views','app/Http/Controllers','app/Services']:
 for p in Path(base).rglob('*.php'):
  if 'test' in str(p):continue
  s=p.read_text(errors='replace')
  if ('vehicle_make' in s or 'vehicle_model' in s) and ('part' in str(p).lower() or 'search' in str(p).lower()):
   print('PARTS_FILE',p,'LEN',len(s))
   for term in ['vehicle_make','vehicle_model','part_name','partsSearch','partSearch','selectPart','partOptions','part-results','parts-dropdown']:
    at=s.find(term)
    if at>=0:print('PARTS_CONTEXT',term,repr(s[max(0,at-300):at+850])[:1200])
PY
echo AM277_READONLY_PASS

echo AM277_TARGETED_SOURCE
python3 - <<'PY'
from pathlib import Path
import re
terms=['purchases.parts.search','/parts/search','parts/search','part-suggest','partSuggestion','partsSearch','searchParts','partsSearch','part_name','part-name','item-suggest','part-dropdown']
paths=[]
for base in ('resources/views','public/js','resources/js','app/Http/Controllers','app/Services'):
 p=Path(base)
 if not p.exists():continue
 for f in p.rglob('*'):
  if not f.is_file() or f.suffix not in ('.php','.js'):continue
  try:s=f.read_text(errors='replace')
  except:continue
  relevant=('purchase' in str(f).lower() or 'estimate' in str(f).lower() or 'job-card' in str(f).lower() or 'part' in str(f).lower())
  if not relevant:continue
  scored=sum(s.lower().count(t.lower()) for t in terms)
  if scored:paths.append((scored,f,s))
for score,f,s in sorted(paths,key=lambda v:-v[0])[:22]:
 print('AM277_TARGET_FILE',f,'SCORE',score,'LEN',len(s))
 selected=[]
 for term in terms[:-2]:
  start=0
  for _ in range(3):
   pos=s.lower().find(term.lower(),start)
   if pos<0:break
   if any(abs(pos-p)<650 for p in selected):start=pos+len(term);continue
   selected.append(pos)
   print('AM277_TARGET_SNIPPET',term,repr(s[max(0,pos-450):pos+2000])[:2500])
   start=pos+len(term)
 print('AM277_MATCH_COUNT',len(selected))
PY
echo AM277_TARGETED_AUDIT_PASS
