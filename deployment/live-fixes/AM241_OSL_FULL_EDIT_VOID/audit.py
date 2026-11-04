#!/usr/bin/env python3
"""AM241 READ-ONLY audit: inspect OSL editing, batch, settlement, audit and UI.
No database writes.
"""
from pathlib import Path
import sys,re,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM241_NO_STAGING')
targets={
 'routes/web.php':[(1,26),(205,240),(292,345)],
 'resources/views/purchases/index.blade.php':[(90,165)],
 'resources/views/purchases/osl-create.blade.php':[(135,170),(195,245),(260,296)],
 'resources/views/purchases/show.blade.php':[(1,40)],
 'resources/views/workshop-controls/osl-edit.blade.php':[(1,280)],
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':[(1,67),(280,450)],
 'app/Services/PurchaseService.php':[(158,180),(1085,1175)],
 'app/Services/OslPurchaseTax.php':[(1,145)],
 'app/Models/PurchaseAuditLog.php':[(1,100)]
}
for rel,segments in targets.items():
 p=root/rel
 if not p.is_file():print('AM241_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM241_FILE',rel,'LINES',len(lines))
 for a,b in segments:
  for i in range(a-1,min(b,len(lines))):
   s=lines[i]
   if re.search('password|secret|private.key|api[_-]?key|env\\(',s,re.I):s='[REDACTED]'
   print('AM241_LINE',rel,i+1,s[:900])
print('AM241_SOURCE_AUDIT_PASS')
# No business/customer data; summarize reference types, settlement columns, active vouchers.
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use Illuminate\\Support\\Facades\\DB;
foreach(['workshop_purchases','workshop_purchase_batches','workshop_payment_vouchers','workshop_payment_allocations','workshop_vendor_payments','purchase_audit_logs','job_card_change_audits','job_card_labour','job_cards','customer_invoices'] as $t){
 try{
   $cols=DB::select('SHOW COLUMNS FROM `'.$t.'`');
   echo 'AM241_SCHEMA '.$t.' '.implode(',',array_map(fn($x)=>$x->Field,$cols))."\\n";
 }catch(Throwable $e){echo 'AM241_TABLE_MISSING '.$t."\\n";}
}
$types=DB::table('workshop_payment_vouchers')->selectRaw("source_type, COUNT(*) n")->groupBy('source_type')->get();
foreach($types as $v)echo 'AM241_PAYMENT_TYPE '.($v->source_type??'')." COUNT ".$v->n."\\n";
$osl=DB::table('workshop_purchases')->where('purchase_type','OSL')->selectRaw("COUNT(*) n, COUNT(DISTINCT purchase_batch_no) batches, SUM(CASE WHEN COALESCE(purchase_batch_no,'')='' THEN 1 ELSE 0 END) no_batch")->first();
echo 'AM241_OSL_COUNTS '.json_encode($osl)."\\n";
echo 'AM241_DB_READONLY_PASS'."\\n";
"""
with tempfile.TemporaryDirectory(prefix='am241audit_') as d:
 f=Path(d)/'audit.php';f.write_text(php)
 p=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=45)
 print(p.stdout[:18000]);print('AM241_DB_RESULT',p.returncode,p.stderr[-380:])
print('AM241_READONLY_FINISHED')
