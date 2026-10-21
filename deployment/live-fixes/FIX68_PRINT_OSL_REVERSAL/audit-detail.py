#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
def dump(p,lo,hi):
 f=root/p
 if not f.exists():print("MISSING",p);return
 a=f.read_text(errors='replace').splitlines()
 print("FILE",p,"lines",len(a),"sha",hashlib.sha256(f.read_bytes()).hexdigest(),"SLICE",lo,hi)
 for i in range(lo-1,min(hi,len(a))):
  if re.search(r'(password|private.key|secret|token|api.key)',a[i],re.I):continue
  print("S",i+1,a[i][:340])
for p in sorted((root/'resources/views/job-cards').glob('*.blade.php')):print("JC_VIEW",p.name)
for p in sorted((root/'resources/views/invoices').glob('*.blade.php')):print("INV_VIEW",p.name)
for n,l in enumerate((root/'routes/web.php').read_text(errors='replace').splitlines(),1):
 if re.search(r'job.cards.*print|job.cards.*summary|printJob|printInvoice|customer.cost|invoice.print|osl.store',l,re.I):
  print("ROUTE",n,l[:350])
for p,start,end in [
('resources/views/job-cards/print.blade.php',1,190),
('resources/views/invoices/print.blade.php',1,130),
('resources/views/purchases/osl-create.blade.php',77,102),
('resources/views/purchases/osl-create.blade.php',130,268),
('app/Http/Controllers/Web/AdminPurchasePageController.php',86,109),
('app/Services/PurchaseService.php',712,816),
('app/Services/PurchaseService.php',965,1059),
('app/Services/PurchaseService.php',855,878),
('app/Http/Controllers/Web/AdminJobCardPageController.php',330,420),
]:
 dump(p,start,end)
dump('resources/views/invoices/print.blade.php',145,220)
dump('app/Http/Controllers/Web/AdminJobCardPageController.php',235,330)
dump('app/Services/PurchaseService.php',845,877)
dump('resources/views/purchases/osl-create.blade.php',1,42)
dump('resources/views/purchases/osl-create.blade.php',77,100)
dump('app/Http/Controllers/Web/AdminWorkshopControlPageController.php',271,380)
print("FIX68_TARGETED_READONLY_COMPLETE")
