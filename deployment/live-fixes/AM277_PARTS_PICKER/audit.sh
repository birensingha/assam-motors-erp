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
