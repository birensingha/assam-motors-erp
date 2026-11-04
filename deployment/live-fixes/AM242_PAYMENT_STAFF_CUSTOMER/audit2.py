#!/usr/bin/env python3
"""AM242 focused source ranges + count-only customer master census (READ ONLY)."""
from pathlib import Path
import re,sys,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_BAD_ROOT')
targets={
'app/Http/Controllers/Web/AdminExpensePaymentPageController.php':[(1,126)],
'resources/views/payments/create.blade.php':[(200,270),(470,600),(625,765),(790,890),(900,1050),(1080,1180),(1380,1540),(1550,1740),(1740,1860)],
'app/Services/StaffPaymentVoucherService.php':[(26,75)],
'app/Services/StaffFinanceLedgerController.php':[(1,50)],
'app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php':[(1,135)],
'resources/views/expenses/create.blade.php':[(1,145)],
'app/Services/ExpensePaymentService.php':[(45,145),(304,370)],
'app/Http/Controllers/Web/AdminMoneyReceiptController.php':[(1,95)],
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():print('AM242_FOCUSED_MISSING',rel);continue
 l=p.read_text('utf8','replace').splitlines()
 print('AM242_FOCUSED_FILE',rel,len(l))
 for a,b in ranges:
  for i in range(a-1,min(b,len(l))):
   s=l[i]
   if re.search(r'password|secret|private[_-]?key|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM242_FOCUSED',rel,i+1,s[:550])
print('AM242_FOCUSED_SOURCE_END',flush=True)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach(['staff','customer_master','am_customer_master','am_erp_customers','customers','job_cards','workshop_customers','customer_ledger','workshop_staff_finance_journals','workshop_expenses'] as $t){
 try{
  $total=DB::table($t)->count();
  echo "AM242_CENSUS $t $total\n";
  if($t==='staff'){
   $active=DB::table($t)->whereIn('status',['active','ACTIVE','Active',1])->count();
   echo "AM242_CENSUS_STAFF_ACTIVE $active\n";
  }
 }catch(Throwable $e){echo "AM242_CENSUS_NO_TABLE $t\n";}
}
echo "AM242_CENSUS_COMPLETE\n";
"""
with tempfile.TemporaryDirectory(prefix='am242focus_') as d:
 f=Path(d)/'stats.php';f.write_text(php)
 p=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=40)
 print(p.stdout,flush=True);print('AM242_CENSUS_EXIT',p.returncode,p.stderr[-220:],flush=True)
print('AM242_FOCUSED_AUDIT_FINISHED')
