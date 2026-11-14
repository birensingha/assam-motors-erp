#!/usr/bin/env python3
"""AM256 read-only audit of exact Expense voucher and service/controller/view/route.
No passwords, bank account values, staff private data or DB writes.
"""
from pathlib import Path
import re,sys,subprocess
if len(sys.argv)!=2:raise SystemExit("AM256_USAGE audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():raise SystemExit("STOP_AM256_NOT_STAGING")
files=[
('app/Http/Controllers/Web/AdminExpensePaymentPageController.php',[(1,280)]),
('app/Services/ExpensePaymentService.php',[(1,440)]),
('resources/views/expenses/show.blade.php',[(1,265)]),
('resources/views/expenses/index.blade.php',[(1,65)]),
('app/Models/WorkshopExpense.php',[(1,130)]),
]
danger=re.compile(r'(?i)password|secret|token|APP_KEY|DB_PASSWORD|private.key|authentication|api[_-]key|cookie|csrf_token\(')
for rel,ranges in files:
 p=root/rel
 if not p.is_file():print("AM256_MISSING",rel);continue
 ls=p.read_text('utf8','replace').splitlines()
 print("AM256_FILE",rel,"TOTAL",len(ls))
 for lo,hi in ranges:
  for i in range(lo-1,min(len(ls),hi)):
   x=ls[i].rstrip()
   if danger.search(x):x="[REDACTED]"
   print("AM256_CODE",rel,i+1,x[:570])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;use Illuminate\Support\Facades\Route;
foreach(['workshop_expenses','workshop_expense_items','workshop_payment_vouchers','workshop_expense_audits','workshop_payment_allocations','workshop_expense_lines','job_card_expenses','staff_finance_ledger','staff_finance_journals'] as $t){
 if(!Schema::hasTable($t)){echo "AM256_TABLE_MISSING ".$t."\n";continue;}
 echo "AM256_TABLE ".$t."\n";
 foreach(DB::select("SELECT COLUMN_NAME AS c,DATA_TYPE AS t,IS_NULLABLE AS nul,COLUMN_DEFAULT AS def
 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION",[$t]) as $c)
   echo "AM256_COLUMN ".$t." ".$c->c." ".$c->t." NULL=".$c->nul."\n";
}
$exp=DB::table('workshop_expenses')->where('expense_no','EXP-20261114-000026')->first();
if(!$exp){echo "AM256_TARGET_NOT_FOUND\n";}
else{
 $whitelist=['id','expense_no','expense_date','expense_category','record_status','payment_status','amount','base_amount','gst_percent','gst_amount','total_amount','paid_amount','balance_amount','staff_id','job_card_id','vendor_id','expense_against','created_at','updated_at'];
 $row=[];foreach($whitelist as $c)if(property_exists($exp,$c))$row[$c]=$exp->$c;
 echo "AM256_TARGET=".json_encode($row)."\n";
 foreach(['workshop_payment_vouchers','workshop_payment_allocations'] as $t){
  if(!Schema::hasTable($t))continue;
  $cols=Schema::getColumnListing($t);
  if(in_array('source_type',$cols,true)&&in_array('source_id',$cols,true)){
   $q=DB::table($t)->where('source_id',$exp->id)->where('source_type','EXPENSE_VOUCHER');
   echo "AM256_ASSOCIATED_PAYMENT_COUNT_".$t."=".$q->count()."\n";
   if(in_array('amount',$cols,true))echo "AM256_ASSOCIATED_PAYMENT_SUM_".$t."=".$q->sum('amount')."\n";
  }
 }
}
foreach(Route::getRoutes() as $r){
 $uri=$r->uri();$name=(string)($r->getName()??'');
 if(str_contains($uri,'expenses')||str_contains($name,'expenses')){
  echo "AM256_EXPENSE_ROUTE=".json_encode(['methods'=>$r->methods(),'uri'=>$uri,'name'=>$name,'action'=>$r->getActionName(),'middleware'=>$r->gatherMiddleware()])."\n";
 }
}
echo "AM256_DB_AUDIT_READONLY_COMPLETE\n";"""
r=subprocess.run(['php','-r',php],cwd=root,text=True,capture_output=True,timeout=50)
if r.returncode:raise SystemExit("STOP_AM256_SCHEMA_AUDIT_FAILED "+(r.stdout+r.stderr)[-650:])
print(r.stdout[:17000])
print("AM256_NO_DATA_WRITES_NO_LOGIN_OR_PAYMENT_ATTEMPTS")
