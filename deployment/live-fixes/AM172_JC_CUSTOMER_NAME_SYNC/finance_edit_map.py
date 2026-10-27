#!/usr/bin/env python3
"""READ-ONLY AM172 finance correction readiness map.
Prints only route/controller method structures, never customer records or amounts.
"""
from pathlib import Path
import re,sys
if len(sys.argv)!=2:raise SystemExit("Usage: finance_edit_map.py LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("NOT_LARAVEL_ROOT")
print("AM172_FINANCE_READ_ONLY_BEGIN")
paths=[
 "routes/web.php",
 "app/Http/Controllers/Web/AdminMoneyReceiptController.php",
 "app/Http/Controllers/Web/AdminExpensePaymentPageController.php",
 "app/Services/ExpensePaymentService.php",
 "resources/views/money-receipts/index.blade.php",
 "resources/views/money-receipts/create.blade.php",
 "resources/views/payments/index.blade.php",
 "resources/views/payments/show.blade.php",
 "resources/views/customers/ledger.blade.php",
]
methods=re.compile(r"^\s*(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(",re.I)
routes=re.compile(r"(?:money.receipts|money-receipts|customer.ledger|payments|payment.voucher)",re.I)
ui=re.compile(r"route\([^)]*(?:receipt|payment|voucher|ledger)|(?:edit|correct|void|reverse|allocate)",re.I)
security=re.compile(r"password|secret|api.key|bearer|\.env|auth.token",re.I)
for name in paths:
 p=root/name
 if not p.is_file():print("FILE_MISSING",name);continue
 ls=p.read_text(encoding="utf8",errors="replace").splitlines()
 print("FILE",name,"LINES",len(ls))
 if name=="routes/web.php":
  hits=[(i+1,l) for i,l in enumerate(ls) if routes.search(l)]
  for n,s in hits[:35]:print(" ROUTE",n,"[redacted]" if security.search(s) else s[:220].strip())
 elif "/Controllers/" in name or "/Services/" in name:
  hits=[(i+1,m.group(1)) for i,l in enumerate(ls) if (m:=methods.match(l))]
  print(" METHODS",",".join(f"{n}:{v}" for n,v in hits[:50]))
  for i,l in enumerate(ls):
   if re.search(r"function\s+(?:edit|update|void|reverse|reallocat|correct)\w*\s*\(",l,re.I):
    print(" ACTION",i+1,l[:210].strip())
 else:
  hits=[(i+1,l) for i,l in enumerate(ls) if ui.search(l)]
  for n,s in hits[:10]:print(" VIEW",n,"[redacted]" if security.search(s) else s[:210].strip())
print("AM172_FINANCE_READ_ONLY_COMPLETE")
