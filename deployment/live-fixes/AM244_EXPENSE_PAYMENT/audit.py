#!/usr/bin/env python3
"""AM244 read-only inspect Expense→Staff Payment reference lookup, no writes."""
from pathlib import Path
import sys,re,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM244_NOT_STAGING')
targets={
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php':[(75,235)],
 'app/Services/ExpensePaymentService.php':[(15,60),(195,265),(287,425)],
 'app/Services/StaffPaymentVoucherService.php':[(1,440)],
 'resources/views/payments/create.blade.php':[(1,340)],
 'resources/views/expenses/show.blade.php':[(1,55)],
 'routes/web.php':[(290,315)]
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():print('AM244_NO_FILE',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM244_FILE',rel,len(lines))
 for lo,hi in ranges:
  for i in range(lo-1,min(hi,len(lines))):
   s=lines[i]
   if re.search(r'password|secret|private.key|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM244_SRC',rel,i+1,s[:470])
for p in (root/'public/js').rglob('*.js'):
 if p.stat().st_size>180000:continue
 s=p.read_text('utf8','replace').splitlines()
 matches=[i for i,line in enumerate(s) if re.search(r'AGAINST_REFERENCE|payee_type|STAFF|EXPENSE_VOUCHER|against.reference|payment.to|source.key',line,re.I)]
 if matches:
  print('AM244_JS_FILE',p.relative_to(root),len(s),len(matches))
  seen=set()
  for i in matches[:35]:
   for n in range(max(0,i-3),min(len(s),i+5)):
    if n in seen:continue
    seen.add(n);print('AM244_JS',p.relative_to(root),n+1,s[n][:390])
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach(['workshop_expenses','workshop_payment_vouchers','workshop_payment_allocations'] as $t){
 echo 'AM244_SCHEMA '.$t.' '.implode(',',array_map(fn($c)=>$c->Field,DB::select('SHOW COLUMNS FROM `'.$t.'`')))."\n";
}
$e=DB::table('workshop_expenses')->where('expense_no','EXP-20261104-000016')->first();
if($e){
 echo 'AM244_TARGET '.json_encode(['id'=>$e->id,'expense_no'=>$e->expense_no,'staff_id'=>$e->staff_id??null,'category'=>$e->expense_category??null,'total'=>$e->total_amount,'paid'=>$e->paid_amount,'status'=>$e->record_status,'job_card_id'=>$e->job_card_id??null,'payee_name'=>$e->payee_name??null])."\n";
 echo 'AM244_ACTIVE_LINKED_PAYMENTS '.DB::table('workshop_payment_vouchers')->where('source_type','EXPENSE_VOUCHER')->where('source_id',$e->id)->where('record_status','ACTIVE')->count()."\n";
}else echo 'AM244_TARGET_NOT_FOUND'."\n";
echo 'AM244_SQL_READONLY_DONE'."\n";
"""
with tempfile.TemporaryDirectory(prefix='am244_') as d:
 file=Path(d)/'audit.php';file.write_text(php)
 result=subprocess.run(['php',str(file),str(root)],cwd=root,capture_output=True,text=True,timeout=40)
 print(result.stdout[:6000],flush=True)
 print('AM244_DB_AUDIT_EXIT',result.returncode,result.stderr[-300:],flush=True)
print('AM244_READONLY_DONE')
