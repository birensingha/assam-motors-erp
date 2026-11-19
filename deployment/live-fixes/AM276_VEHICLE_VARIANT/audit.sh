#!/usr/bin/env bash
set -euo pipefail
cd "${1:?STAGING_ROOT}";test -f artisan
echo "AM276_ROUTES"
php artisan route:list --path=vehicle --except-vendor | head -85
echo "AM276_VEHICLE_FILES"
find resources/views -type f \( -iname '*vehicle*' -o -iname '*customer*' -o -iname '*part*' \) | head -75
echo "AM276_VARIANT_REFERENCES"
rg -n -i -m 4 'Exact Variant Not Listed|variant.*select|vehicle.variant|vehicle_variants|vehicle-variant' resources/views app/Http/Controllers app/Services routes/web.php | head -110 || true
echo "AM276_FORM_CONTEXT"
python3 - <<'PY'
from pathlib import Path
for root in (Path('resources/views'),Path('app/Http/Controllers'),Path('app/Services')):
 for p in root.rglob('*.php'):
  try:t=p.read_text()
  except:continue
  if 'Exact Variant Not Listed' in t or ('Vehicle Master' in t and 'variant' in t.lower()):
   print('SOURCE',p,'LEN',len(t))
   for term in ['Exact Variant Not Listed','name="variant"','name="vehicle_variant"','vehicle_variant','variantOptions','variant_list','variant-dropdown']:
    start=0
    for j in range(3):
     i=t.find(term,start)
     if i<0:break
     print('CONTEXT',term,t[max(0,i-900):i+1500].replace('\n',' ')[:2350])
     start=i+len(term)
PY
echo "AM276_AUDIT_PASS"
