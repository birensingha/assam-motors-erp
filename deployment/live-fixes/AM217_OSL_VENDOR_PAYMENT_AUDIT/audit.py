#!/usr/bin/env python3
"""AM217 STAGING OSL Vendor Payment audit. READ ONLY; no UPDATE/INSERT/DELETE.
Usage: python3 audit.py STAGING_ROOT [PURCHASE_REFERENCE] [VENDOR_NAME]
Do not pass credentials. Output is metadata and boolean checks, not customer rows.
"""
from pathlib import Path
import hashlib, re, subprocess, sys, tempfile

if len(sys.argv) not in (2, 3, 4):
    raise SystemExit("AM217_USAGE audit.py STAGING_ROOT [PURCHASE_REFERENCE] [VENDOR_NAME]")
root = Path(sys.argv[1]).expanduser().resolve()
if root.name != 'assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM217_STOP_NOT_STAGING')
purchase = sys.argv[2] if len(sys.argv) >= 3 else ''
vendor = sys.argv[3] if len(sys.argv) >= 4 else ''
if len(purchase) > 80 or len(vendor) > 190:
    raise SystemExit('AM217_STOP_QUERY_TOO_LONG')

files = [
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
 'app/Http/Controllers/Web/AdminNativeModuleController.php',
 'app/Services/ExpensePaymentService.php',
 'resources/views/payments/create.blade.php',
 'resources/views/payments/show.blade.php',
 'resources/views/workshop-controls/osl-edit.blade.php',
 'routes/web.php'
]
print('AM217_SOURCE_STRUCTURE_BEGIN')
for f in files:
    p=root/f
    if not p.is_file():
        print('MISSING',f)
        continue
    content=p.read_text('utf8','replace')
    print('SOURCE',f,'SHA256',hashlib.sha256(p.read_bytes()).hexdigest()[:16])
    if f.endswith('.php') and '/Controllers/' in f or '/Services/' in f:
        methods=re.findall(r'\bfunction\s+(\w+)\s*\(',content)
        print('METHOD_NAMES',','.join(methods[:75]))
    elif f.endswith('.blade.php'):
        names=sorted(set(re.findall(r'\bname=[\'\"]([a-zA-Z][a-zA-Z_0-9-]*)[\'\"]',content)))
        print('FORM_INPUT_NAMES',','.join(names[:95]))
print('AM217_SOURCE_STRUCTURE_END')

print('AM217_FINANCE_METHOD_FEATURE_MAP_BEGIN')
feature_map = {
 'app/Services/ExpensePaymentService.php':[
   'vendors','payableSources','resolveSource','savePayment','createVoucher','voidPayment'],
 'app/Http/Controllers/Web/AdminPurchasePageController.php':[
   'storeOsl','edit','update','show'],
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':[
   'editOsl','updateOsl'],
 'app/Http/Controllers/Web/AdminVendorPageController.php':[
   'ledger','savePayment'],
}
tokens=['workshop_purchases','workshop_purchase_batches','workshop_payment_allocations',
 'workshop_payment_vouchers','workshop_vendor_payments','vendor_id','supplier_name',
 'supplier_invoice','source_type','source_reference','purchase_ref','OSL_JOB',
 'OSL_PURCHASE','OSL','PURCHASE','DB::transaction','lockForUpdate','request_key']
for path,methods in feature_map.items():
    file=root/path
    if not file.is_file():
        print('MISSING_FEATURE_SOURCE',path)
        continue
    source=file.read_text('utf8','replace')
    chunks=re.split(r'(?m)^\s*(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(',source)
    available={}
    for i in range(1,len(chunks),2):
        available[chunks[i]]=chunks[i+1]
    for method in methods:
        block=available.get(method)
        if block is None:
            print('METHOD_NOT_FOUND',file.name,method)
            continue
        found=[name for name in tokens if name in block]
        literals=sorted(set(v for v in re.findall(r"""['"]([A-Za-z][A-Za-z0-9_-]{1,36})['"]""",block)
                            if any(k in v.lower() for k in ['osl','vendor','purchase','expense','job'])))
        print('METHOD',file.name,method,'FEATURES',','.join(found),
              'SOURCE_LITERAL_HINTS',','.join(literals[:28]))
print('AM217_FINANCE_METHOD_FEATURE_MAP_END')

php=r'''<?php
$root=$argv[1];
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
function cols($t) { return Schema::hasTable($t) ? Schema::getColumnListing($t) : []; }
function has($c,$k) { return in_array($k,$c,true); }
$tables=['workshop_vendors','workshop_purchases','workshop_purchase_batches',
 'workshop_purchase_lines','workshop_purchase_items','workshop_payment_vouchers',
 'workshop_vendor_payments','workshop_vendor_ledger','vendor_ledger','vendor_ledgers',
 'payment_voucher_allocations','workshop_payment_allocations','workshop_expenses',
 'cash_bank_ledger','account_ledger','ledger_entries','accounts'];
echo "AM217_SCHEMA_BEGIN\n";
foreach($tables as $t){
  $c=cols($t);
  echo "TABLE $t ".($c?implode(',',$c):'NOT_FOUND')."\n";
}
echo "AM217_SCHEMA_END\n";
echo "AM217_ROUTE_BEGIN\n";
foreach(app('router')->getRoutes() as $r){
  $name=(string)$r->getName(); $uri=$r->uri();
  if(!preg_match('/(payment|voucher|purchase|vendor|osl)/i',$name.' '.$uri))continue;
  if(!str_starts_with($uri,'erp/'))continue;
  echo implode(',',array_diff($r->methods(),['HEAD'])).' '.$uri.' NAME '.$name.' ACTION '.$r->getActionName()."\n";
}
echo "AM217_ROUTE_END\n";
$ref=trim((string)($argv[2]??''));
$expectedVendor=trim((string)($argv[3]??''));
if($ref===''){echo "AM217_TARGET_LOOKUP_SKIPPED_NO_REFERENCE\n";exit(0);}
$t='workshop_purchases'; $c=cols($t);
if(!$c){echo "AM217_TARGET_PURCHASE_TABLE_NOT_FOUND\n";exit(0);}
$refCols=array_values(array_intersect(['purchase_no','purchase_batch_no','reference_no','voucher_no'],$c));
if(!$refCols){echo "AM217_TARGET_NO_REFERENCE_COLUMN\n";exit(0);}
$q=DB::table($t);
$q->where(function($q)use($refCols,$ref){
 foreach($refCols as $k)$q->orWhere($k,'=',$ref);
});
$matches=$q->limit(2)->get();
echo 'AM217_TARGET_MATCH_COUNT_CAPPED '.count($matches)."\n";
if(count($matches)!==1){
 if(cols('workshop_purchase_batches'))echo "AM217_INSPECT_PURCHASE_BATCH_LOOKUP_REQUIRED\n";
 exit(0);
}
$row=$matches[0];
$vendorId=(int)($row->vendor_id??0);
echo 'AM217_TARGET_VENDOR_ID_PRESENT '.($vendorId>0?'YES':'NO')."\n";
echo 'AM217_TARGET_RECORD_STATUS_PRESENT '.(property_exists($row,'record_status')?'YES':'NO')."\n";
echo 'AM217_TARGET_TOTAL_AMOUNT_PRESENT '.(property_exists($row,'total_amount')?'YES':'NO')."\n";
if(property_exists($row,'total_amount')){
 $amount=(float)$row->total_amount;
 echo 'AM217_TARGET_TOTAL_EQUALS_400 '.(abs($amount-400.0)<0.005?'YES':'NO')."\n";
}
$vc=cols('workshop_vendors');
$vendorRow=($vendorId && $vc && has($vc,'id'))
  ? DB::table('workshop_vendors')->where('id',$vendorId)->first():null;
echo 'AM217_VENDOR_MASTER_ID_MATCH '.($vendorRow?'YES':'NO')."\n";
if($vendorRow&&$expectedVendor!==''){
 $name=(string)($vendorRow->vendor_name??$vendorRow->name??'');
 echo 'AM217_VENDOR_NAME_MATCH '.(mb_strtolower(trim($name))===mb_strtolower($expectedVendor)?'YES':'NO')."\n";
}
if($expectedVendor!==''){
 $supplier=trim((string)($row->supplier_name??''));
 echo 'AM217_PURCHASE_SUPPLIER_NAME_MATCH '.(mb_strtolower($supplier)===mb_strtolower($expectedVendor)?'YES':'NO')."\\n";
 $candidate=DB::table('workshop_vendors')->whereRaw('LOWER(TRIM(vendor_name)) = ?', [mb_strtolower($expectedVendor)])->limit(3)->get();
 echo 'AM217_EXPECTED_VENDOR_MASTER_MATCH_COUNT_CAPPED '.count($candidate)."\\n";
 if(count($candidate)===1){
  echo 'AM217_EXPECTED_VENDOR_ACTIVE '.((int)($candidate[0]->active??0)===1?'YES':'NO')."\\n";
  echo 'AM217_EXPECTED_VENDOR_EQUALS_CURRENT_LINK '.((int)$candidate[0]->id===$vendorId?'YES':'NO')."\\n";
 }
}
echo 'AM217_CURRENT_LINKED_VENDOR_ACTIVE '.((int)($vendorRow->active??0)===1?'YES':'NO')."\\n";
$batchNo=trim((string)($row->purchase_batch_no??''));
$batchCols=cols('workshop_purchase_batches');
if($batchNo!=='' && has($batchCols,'batch_no')){
 $batch=DB::table('workshop_purchase_batches')->where('batch_no',$batchNo)->limit(2)->get();
 echo 'AM217_BATCH_MATCH_COUNT_CAPPED '.count($batch)."\\n";
 if(count($batch)===1)echo 'AM217_BATCH_VENDOR_ID_EQUALS_PURCHASE '.((int)($batch[0]->vendor_id??0)===$vendorId?'YES':'NO')."\\n";
 $linked=DB::table('workshop_purchases')->where('purchase_batch_no',$batchNo)->limit(101)->get();
 echo 'AM217_BATCH_PURCHASE_LINES_CAPPED '.count($linked)."\\n";
 echo 'AM217_BATCH_LINES_SAME_VENDOR '.($linked->every(fn($x)=>(int)($x->vendor_id??0)===$vendorId)?'YES':'NO')."\\n";
}
$allAudit=DB::select("SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%audit%' ORDER BY TABLE_NAME");
foreach($allAudit as $at){
 $t=(string)$at->TABLE_NAME;
 if(preg_match('/(purchase|payment|vendor|account)/i',$t))
   echo 'AM217_RELATED_AUDIT_TABLE '.$t.' COLUMNS '.implode(',',cols($t))."\\n";
}
foreach(['workshop_payment_vouchers','workshop_vendor_payments',
         'workshop_vendor_ledger','vendor_ledger','vendor_ledgers'] as $table){
 $cc=cols($table);
 if(!$cc){echo 'AM217_TABLE_NOT_FOUND '.$table."\n";continue;}
 $fk=has($cc,'vendor_id')?'vendor_id':(has($cc,'payee_vendor_id')?'payee_vendor_id':null);
 if($fk&&$vendorId){
  $n=DB::table($table)->where($fk,$vendorId)->limit(101)->count();
  echo 'AM217_VENDOR_MATCHING_ROWS_CAPPED '.$table.' '.min(101,$n)."\n";
 }else{echo 'AM217_VENDOR_JOIN_REQUIRES_REVIEW '.$table."\n";}
}
echo "AM217_NO_FINANCIAL_ROW_MUTATIONS\n";
'''
with tempfile.TemporaryDirectory(prefix='am217_readonly_') as tmp:
    script=Path(tmp)/'audit.php'
    script.write_text(php,encoding='utf8')
    command=['php',str(script),str(root),purchase,vendor]
    run=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=55)
    if run.stdout:
        print(run.stdout[:25000])
    if run.returncode:
        print('AM217_AUDIT_ERROR_EXIT',run.returncode)
        print('AM217_AUDIT_ERROR_BRIEF',run.stderr[-550:])
        sys.exit(run.returncode)
print('AM217_READ_ONLY_COMPLETED')
