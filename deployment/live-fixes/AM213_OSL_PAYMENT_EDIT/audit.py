#!/usr/bin/env python3
"""AM213 STAGING-only READ-ONLY OSL Purchase -> Vendor -> Payment audit.

No inserts/updates/deletes; no staff/customer data or credentials emitted.
Targets the owner-provided OSL Purchase number, checks vendor linkage and
existing Payment Voucher/OSL editor routes and selected source shapes.
"""
import argparse, json, pathlib, re, subprocess, sys

p=argparse.ArgumentParser()
p.add_argument("root")
p.add_argument("--purchase-no",default="OB-261030184631-517")
args=p.parse_args()
root=pathlib.Path(args.root).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM213_STOP_NOT_STAGING")
purchase_no=args.purchase_no.strip()
if not re.fullmatch(r"[A-Za-z0-9-]{5,65}",purchase_no):
    raise SystemExit("AM213_STOP_BAD_PURCHASE_REFERENCE")

def run(cmd, stdin=None):
    try:
        r=subprocess.run(cmd,cwd=root,input=stdin,text=True,capture_output=True,timeout=55)
    except (OSError,subprocess.TimeoutExpired) as e:
        raise SystemExit("AM213_STOP_AUDIT_COMMAND_FAILED "+type(e).__name__)
    if r.returncode:
        raise SystemExit("AM213_STOP_AUDIT_EXIT_"+str(r.returncode)+" "+r.stderr[-250:])
    return r.stdout

# Only route metadata: no user records.
routes=json.loads(run(["php","artisan","route:list","--json"]))
print("AM213_ROUTES_BEGIN")
for r in routes:
    uri=str(r.get("uri",""))
    name=str(r.get("name") or "")
    if not (uri.startswith("erp/") and
            (re.search(r"purchas|payment|vendors?/",uri,re.I) or
             re.search(r"purchases|payments|vendors|osl-edit",name,re.I))):
        continue
    print(json.dumps({
        "method":r.get("method"),"uri":uri,"name":name,
        "action":r.get("action"),"middleware":r.get("middleware")
    },ensure_ascii=True))
print("AM213_ROUTES_END")

# Which exact template/controller implements the view from user's screenshot?
print("AM213_SOURCE_ANCHORS_BEGIN")
files=[
    "app/Http/Controllers/Web/AdminPurchasePageController.php",
    "app/Http/Controllers/Web/AdminPurchaseController.php",
    "app/Http/Controllers/Web/AdminWorkshopControlPageController.php",
    "app/Http/Controllers/Web/AdminExpensePaymentPageController.php",
    "app/Http/Controllers/Web/AdminNativeModuleController.php",
    "app/Http/Controllers/Web/AdminVendorPageController.php",
    "app/Services/PurchaseService.php",
    "app/Services/ExpensePaymentService.php",
    "app/Services/VendorService.php",
    "resources/views/purchases/show.blade.php",
    "resources/views/purchases/osl-show.blade.php",
    "resources/views/purchases/osl-edit.blade.php",
    "resources/views/purchases/index.blade.php",
    "resources/views/workshop-controls/osl-edit.blade.php",
    "resources/views/payments/create.blade.php",
    "resources/views/payments/index.blade.php",
    "resources/views/payments/show.blade.php",
    "routes/web.php",
]
# Exact source-only snippets, never print requests, tokens, SQL statements,
# vendor personal information, secrets or full source files.
pat=re.compile(r"(?i)(purchase.?view|supplier.invoice|osl.?purchas|vendor(_id|.?master|.?ledger|.?name)|payment.?voucher|against.?reference|reference.kind|source.?kind|edit.?purchas|purchase.?edit|payment.?create|payable|outstanding)")
deny=re.compile(r"(?i)(token|bearer|password|secret|smtp|api.?key|authorization|private.?key|credential)")
for rel in files:
    path=root/rel
    if not path.is_file():
        print("MISSING",rel);continue
    lines=path.read_text(encoding="utf8",errors="replace").splitlines()
    print("FILE",rel,"LINES",len(lines))
    found=0
    for i,line in enumerate(lines):
        if found>=35:break
        if not pat.search(line) or deny.search(line):continue
        compact=line.strip()[:220]
        if rel=="routes/web.php" and
           not any(key in compact for key in
                   ("purchases","payments","vendors","osl","purchase")):
            continue
        print("LINE",i+1,compact.encode("ascii","backslashreplace").decode("ascii"))
        found+=1
print("AM213_SOURCE_ANCHORS_END")

# This inline PHP is read-only. It prints selected PO number, linked IDs,
# sums and reference column names. It never prints staff details or whole
# ledgers. Vendor name is already provided by the owner.
php=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
$no=$argv[1];
$names=['workshop_purchases','workshop_purchase_batches','workshop_vendors',
        'workshop_vendor_payments','workshop_vendor_ledger','workshop_payment_vouchers',
        'vendor_ledger','vendor_payments','expense_payments','payment_voucher_audits',
        'workshop_purchase_payments','workshop_payment_allocations'];
foreach($names as $name) {
 if (!Schema::hasTable($name)) { echo "MISSING_TABLE $name\n";continue; }
 echo "TABLE $name COLUMNS ".implode(',',Schema::getColumnListing($name))."\n";
}
if(!Schema::hasTable('workshop_purchases')){echo "AM213_STOP_NO_PURCHASE_TABLE\n";exit;}
$cols=Schema::getColumnListing('workshop_purchases');
$keys=array_values(array_intersect(['purchase_no','purchase_batch_no','supplier_invoice'], $cols));
$q=DB::table('workshop_purchases');
if(count($keys)){
 $q->where(function($w)use($keys,$no){foreach($keys as $k)$w->orWhere($k,'=',$no);});
 $rows=$q->limit(25)->get();
} else {$rows=collect([]);}
echo "TARGET_PURCHASE_MATCH_COUNT ".$rows->count()."\n";
foreach($rows as $r){
 $data=(array)$r;
 $safe=[];
 foreach(['id','purchase_no','purchase_batch_no','purchase_line_no','vendor_id',
  'supplier_name','purchase_type','type','category','record_status','payment_status',
  'total_amount','qty','quantity','rate','gst_mode','supplier_invoice','job_card_id',
  'deleted_at'] as $k)if(array_key_exists($k,$data))$safe[$k]=$data[$k];
 echo "TARGET_PURCHASE_LINE ".json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}
if(Schema::hasTable('workshop_purchase_batches')){
 $cols=Schema::getColumnListing('workshop_purchase_batches');
 $keys=array_values(array_intersect(['batch_no','purchase_batch_no','purchase_no','supplier_invoice'],$cols));
 if(count($keys)){
  $b=DB::table('workshop_purchase_batches')->where(function($w) use($keys,$no){
   foreach($keys as $k)$w->orWhere($k,'=',$no);
  })->limit(8)->get();
  echo "TARGET_BATCH_MATCH_COUNT ".$b->count()."\n";
  foreach($b as $row){
   $out=[];
   foreach(['id','purchase_batch_no','batch_no','purchase_no','vendor_id','supplier_name',
    'total_amount','grand_total','supplier_invoice','record_status'] as $k)if(isset($row->$k))$out[$k]=$row->$k;
   echo "TARGET_BATCH ".json_encode($out,JSON_UNESCAPED_UNICODE)."\n";
  }
 }
}
if(Schema::hasTable('workshop_vendors')) {
 $vc=Schema::getColumnListing('workshop_vendors');
 $nameCol=in_array('vendor_name',$vc,true)?'vendor_name':(in_array('name',$vc,true)?'name':null);
 $ids=[];
 foreach($rows as $r)if(isset($r->vendor_id) && (int)$r->vendor_id>0)$ids[]=(int)$r->vendor_id;
 $ids=array_values(array_unique($ids));
 if(count($ids)){
  $vs=DB::table('workshop_vendors')->whereIn('id',$ids)->get();
  foreach($vs as $v){
   $out=['id'=>$v->id];
   if($nameCol)$out['vendor_name']=$v->$nameCol;
   if(isset($v->status))$out['status']=$v->status;
   echo "LINKED_VENDOR ".json_encode($out,JSON_UNESCAPED_UNICODE)."\n";
  }
  echo "LINKED_VENDOR_MASTER_MATCH_COUNT ".$vs->count()."\n";
 }
 if($nameCol){
  $matches=DB::table('workshop_vendors')
   ->whereRaw('LOWER(TRIM('.$nameCol.')) = ?', [mb_strtolower('SAHERA ENGINEERING WORKSHOP (DAKHINGAON)')])
   ->limit(10)->get();
  echo "EXACT_SCREENSHOT_VENDOR_NAME_MASTER_MATCH_COUNT ".$matches->count()."\n";
  foreach($matches as $v)echo "EXACT_NAME_VENDOR_ID ".$v->id."\n";
 }
}
echo "AM213_DB_TARGET_READONLY_DONE\n";
"""
print("AM213_DB_AUDIT_BEGIN")
print(run(["php","-r",php,purchase_no]))
print("AM213_DB_AUDIT_END")
print("AM213_COMPLETE_READ_ONLY_NO_FINANCIAL_WRITES")
