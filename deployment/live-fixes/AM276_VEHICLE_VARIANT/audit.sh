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
echo "AM276_DETAILED_JS"
python3 - <<'PY'
from pathlib import Path
for name in ['resources/views/admin/native/customer-detail.blade.php','resources/views/admin/native/vehicles.blade.php','resources/views/admin/native/parts.blade.php','resources/views/admin/native/partials/customer-vehicle-form.blade.php']:
 s=Path(name).read_text()
 print('DOCUMENT',name,'LENGTH',len(s))
 if 'customer-detail' in name:
  for term in ['amVehicleVariants','amVehicleModels','@include','Add Vehicle','<datalist','amVehicleMakes']:
   start=0
   for ix in range(3):
    pos=s.find(term,start)
    if pos<0:break
    print('SNIPPET',term,repr(s[max(0,pos-450):pos+1100]))
    start=pos+len(term)
 if 'vehicles.blade' in name:
  for term in ['const catalog','const rows','am222Catalog','function options','function filter','function refresh','variant','am222-popup','add vehicle','<form']:
   pos=s.find(term,3500 if term=='variant' else 0)
   if pos>=0:print('SNIPPET',term,repr(s[max(0,pos-230):pos+1000]))
 if 'parts.blade' in name:
  print('PART_TOP',repr(s[:2600]))
PY
echo "AM276_AUDIT_PASS"
