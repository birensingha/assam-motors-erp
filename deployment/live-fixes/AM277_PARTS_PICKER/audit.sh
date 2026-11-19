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

echo AM277_FOCUSED_SOURCE
python3 - <<'PY'
from pathlib import Path
docs={
 'app/Http/Controllers/Web/AdminPurchasePageController.php':['public function partSearch','public function create('],
 'app/Http/Controllers/Web/AdminEstimatePageController.php':['public function create('],
 'app/Http/Controllers/Web/CounterPartsInvoiceController.php':['public function create('],
 'app/Http/Controllers/Web/AdminJobCardPageController.php':['partsMaster','PartMaster::'],
 'resources/views/purchases/create.blade.php':['part-option','part-results','part-search','partsSearch','@json($parts)','AM_PURCHASE_PART_SEARCH_URL'],
 'resources/views/purchases/edit.blade.php':['part-option','part-results','part-search'],
 'public/js/purchase-edit.js':['part-option','part-results','function render','part.name','part.code','part_label','part-search'],
 'public/js/purchase-create.js':['part-option','part-results','part_name','part.code'],
 'resources/views/estimates/create.blade.php':['@json($parts)','part_name','partOptions','part-option','Select Part'],
 'resources/views/counter-sales/create.blade.php':['@json($parts)','part_name','partOptions','part-option'],
 'resources/views/job-cards/show.blade.php':['partsMaster','am85-part-select','am85-part-master','am85-part-form','am85-part-search']
}
for name,terms in docs.items():
 p=Path(name)
 if not p.exists():continue
 s=p.read_text(errors='replace')
 print('AM277_DOC',name,'LEN',len(s))
 spots=[]
 for term in terms:
  i=s.find(term)
  if i>=0 and all(abs(i-a)>450 for a in spots):
   spots.append(i)
   print('AM277_FOCUS',term,repr(s[max(0,i-180):i+3400])[:3700])
PY
echo AM277_FOCUSED_AUDIT_PASS

echo AM277_EDITOR_DETAILS
python3 - <<'PY'
from pathlib import Path
pairs={
 'public/js/purchase-entry.js':['function drawResults','const meta =','partLabel(p)','function selectPart','const title =','function fetchParts'],
 'public/js/estimate-entry.js':['function renderSuggestions','function showSuggestions','function renderOptions','description','suggestion','function selectItem','part-option','part.code','const matches'],
 'resources/views/job-cards/show.blade.php':['id="am85-part','am85-parts','am85-part-selector','am85-part-picker','am85-select','am85-part-form'],
 'resources/views/counter-sales/create.blade.php':['<select data-part','<select','data-part'],
}
for name,needles in pairs.items():
 p=Path(name)
 if not p.exists():print('AM277_MISSING',name);continue
 s=p.read_text(errors='replace')
 print('AM277_EDITOR_FILE',name,'LEN',len(s))
 for term in needles:
  pos=s.find(term)
  if pos>=0:print('AM277_EDITOR_SNIPPET',term,repr(s[max(0,pos-250):pos+2800])[:3200])
PY
echo AM277_EDITOR_DETAIL_PASS

echo AM277_LAST_UI_SNIPPETS
python3 - <<'PY'
from pathlib import Path
for path,terms in {
 'public/js/estimate-entry.js':['estimate-ref-menu','menu.innerHTML','menu.replaceChildren','menu.append','itemLabel(c)','itemLabel(r)','row.querySelector'],
 'resources/views/job-cards/show.blade.php':['id="am85-part-options"','am85-part-options','AM100','am100','am85-part-search','am85-part-id']
}.items():
 data=Path(path).read_text(errors='replace').splitlines()
 print('AM277_LAST_FILE',path,len(data))
 positions=[]
 for term in terms:
  indices=[i for i,l in enumerate(data) if term.lower() in l.lower()]
  for i in indices[:5]:
   if not any(abs(i-j)<12 for j in positions):positions.append(i)
 for i in sorted(positions)[:18]:
  a=max(0,i-12);b=min(len(data),i+36)
  print('AM277_BLOCK',i+1,'TERM',data[i][:140])
  for n in range(a,b):
   print('L',n+1,data[n][:400])
PY
echo AM277_LAST_UI_PASS
