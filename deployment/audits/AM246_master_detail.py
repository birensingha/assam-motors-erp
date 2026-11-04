#!/usr/bin/env python3
"""AM246 focused source inspection for item-ledger mapping; READ ONLY."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').exists():raise SystemExit("BAD_ROOT")
exact=[
 'routes/web.php','app/Http/Controllers/Web/AdminNativeModuleController.php',
 'app/Http/Controllers/Web/AdminOslQuickMasterController.php',
 'app/Services/PurchaseService.php','app/Http/Controllers/Web/AdminJobCardPartIssueController.php',
 'app/Http/Controllers/Web/CounterPartsInvoiceController.php',
 'app/Services/JobCardPartsAutoClose.php','resources/views/admin/native/parts.blade.php',
 'resources/views/admin/native/labour.blade.php',
]
for p in (root/'resources/views').rglob('*.blade.php'):
 if 'osl' in p.name.lower() or ('native' in str(p) and 'labour' in p.name.lower()):
  exact.append(str(p.relative_to(root)))
for rel in dict.fromkeys(exact):
 p=root/rel
 if not p.is_file():print('AM246_MISSING',rel);continue
 a=p.read_text('utf8','replace').splitlines()
 print('AM246_DETAIL_FILE',rel,len(a))
 if rel=='routes/web.php':
  indices=[i for i,s in enumerate(a) if re.search(r"masters/|osl-master|osl/master|osl-services|configuration",s,re.I)]
 elif 'views/' in rel:
  indices=[i for i,s in enumerate(a) if re.search(r'@section|<h1|<h2|part|labour|osl|Master|search|<table|name=|</form>|@endsection|active',s,re.I)]
 else:
  indices=[i for i,s in enumerate(a) if re.search(r'function (parts|labour|createPart|updatePart|createLabour|updateLabour|master|index)|stock_movements|inventory_movements|workshop_purchases|job_card_parts|invoice_lines|job_card_labour|osl_master|movement_type|->insert\\(|DB::table',s,re.I)]
 seen=set()
 for n in indices[:140]:
  start=max(0,n-(1 if 'views/' not in rel else 0))
  end=min(len(a),n+3)
  for j in range(start,end):
   if j in seen:continue
   seen.add(j)
   v=a[j]
   if re.search(r'password|secret|private.key|api[_-]?key|env\(',v,re.I):v='[REDACTED]'
   print('AM246_DETAIL',rel,j+1,v[:500])
print('AM246_DETAIL_DONE')
