#!/usr/bin/env python3
# AM238 read-only source and schema audit, no financial row exports.
from pathlib import Path
import sys,re,os,subprocess
root=Path(sys.argv[1]).resolve()
for name,file in {
 "MULTI":"app/Services/MultiPurchasePaymentService.php",
 "PAYMENT_SERVICE":"app/Services/ExpensePaymentService.php",
 "PAYMENT_CONTROLLER":"app/Http/Controllers/Web/AdminExpensePaymentPageController.php",
 "MODEL":"app/Models/WorkshopPaymentVoucher.php",
 "VIEW":"resources/views/payments/create.blade.php",
 "PAYMENT_SHOW":"resources/views/payments/show.blade.php",
 "VENDOR_LEDGER":"app/Http/Controllers/Web/AdminVendorMasterController.php",
}.items():
 p=root/file
 print("AM238_FILE_"+name+"_EXISTS="+str(p.is_file()))
 if not p.is_file():continue
 lines=p.read_text('utf8',errors='surrogateescape').splitlines()
 if name in ('MULTI','MODEL','PAYMENT_CONTROLLER','PAYMENT_SHOW'):
  positions=list(range(len(lines))) if name!='PAYMENT_SHOW' else [i for i,l in enumerate(lines) if re.search('source_reference|allocation|on.account|amount|voucher',l,re.I)]
 elif name=='PAYMENT_SERVICE':
  positions=list(range(0,min(218,len(lines))))+list(range(260,min(354,len(lines))))+list(range(384,min(460,len(lines))))
 elif name=='VIEW':
  positions=list(range(335,min(389,len(lines))))+list(range(710,min(770,len(lines))))
 else:
  positions=[i for i,l in enumerate(lines) if re.search("vendor.*ledger|payment_vouchers|allocation|on.account",l,re.I)]
 print("AM238_FILE_"+name+"_LINES="+str(len(lines)))
 for i in positions[:420]:
  print("AM238_SOURCE_"+name+"_"+str(i+1)+" "+lines[i][:380])
print("AM238_SCHEMA_START")
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
foreach(['workshop_payment_vouchers','workshop_payment_allocations','workshop_purchases','workshop_purchase_batches','workshop_vendors'] as $name){
 if(!Schema::hasTable($name)){echo 'AM238_MISSING_TABLE='.$name.PHP_EOL;continue;}
 echo 'AM238_COLUMNS_'.$name.'='.implode(',',Schema::getColumnListing($name)).PHP_EOL;
 foreach(DB::select("SHOW INDEX FROM ".$name) as $idx) {
  echo 'AM238_INDEX_'.$name.'='.$idx->Key_name.':'.$idx->Column_name.':'.$idx->Non_unique.PHP_EOL;
 }
}
foreach(DB::select('SHOW TABLES') as $row){
 $name=(string)array_values((array)$row)[0];
 if(preg_match('/vendor|account|ledger|payment|allocation/i',$name))echo 'AM238_RELATED_TABLE='.$name.PHP_EOL;
}"""
p=subprocess.run(['php','-r',php],cwd=root,capture_output=True,text=True,timeout=70)
print(p.stdout[:18000]);print('AM238_SCHEMA_EXIT='+str(p.returncode))
if p.returncode:print('AM238_SCHEMA_ERROR='+p.stderr[-350:])
print('AM238_READONLY_AUDIT_COMPLETE')
