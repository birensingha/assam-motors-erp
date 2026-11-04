#!/usr/bin/env python3
"""AM242 verify CUSTOMER ledger semantics, payment void and staff UI; read-only."""
from pathlib import Path
import sys,re,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_STOP_BAD_ROOT')
ranges={
 'app/Http/Controllers/Web/AdminMoneyReceiptController.php':[(165,252),(260,341)],
 'app/Services/ExpensePaymentService.php':[(365,424)],
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php':[(200,225)],
 'resources/views/payments/create.blade.php':[(346,421),(425,449),(1020,1100)],
 'resources/views/payments/show.blade.php':[(1,40)],
 'app/Services/StaffPaymentVoucherService.php':[(31,58)],
}
for rel,parts in ranges.items():
 p=root/rel
 if not p.is_file():continue
 l=p.read_text('utf8','replace').splitlines()
 for a,b in parts:
  for i in range(a-1,min(b,len(l))):
   s=l[i]
   if re.search('password|secret|api[_-]?key|private[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM242_LEDGER_SRC',rel,i+1,s[:600])
print('AM242_LEDGER_SOURCE_DONE',flush=True)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach(DB::table('customer_ledger')->select('entry_type')->selectRaw("COUNT(*) n,ROUND(SUM(COALESCE(debit,0)),2) debits,ROUND(SUM(COALESCE(credit,0)),2) credits")->groupBy('entry_type')->get() as $r){
 echo 'AM242_LEDGER_TYPE '.($r->entry_type??'NULL').' COUNT '.$r->n.' DEBIT '.$r->debits.' CREDIT '.$r->credits."\n";
}
$x=DB::table('customer_ledger')->selectRaw('COUNT(*) as n')->whereNotNull('customer_id')->whereNotIn('customer_id',DB::table('am_customer_master')->select('id'))->first();
echo 'AM242_LEDGER_ORPHANS '.($x->n??0)."\n";
echo 'AM242_LEDGER_COUNTS_END'."\n";
"""
with tempfile.TemporaryDirectory(prefix='am242ledger_') as d:
 f=Path(d)/'ledger.php';f.write_text(php)
 p=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=40)
 print(p.stdout);print('AM242_LEDGER_DB_EXIT',p.returncode,p.stderr[-240:])
print('AM242_LEDGER_AUDIT_FINISHED')
