#!/usr/bin/env python3
"""AM171 phase 2: targeted READ-ONLY missing evidence for feature implementation.
Only code/route excerpts, DB record counts and field metadata. NO customer personal
details, NO receipts/voucher amounts, NO INSERT/UPDATE/DELETE.
Usage: python3 audit_routes_methods.py LARAVEL_ROOT
"""
from pathlib import Path
import re,sys,subprocess,tempfile
if len(sys.argv)!=2:raise SystemExit("Usage: python3 audit_routes_methods.py ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("NOT_LARAVEL_ROOT")
private=re.compile(r"(?i)password|secret|private.?key|bearer|csrf|api.?key|authorization|\\.env")
paths=[
"app/Http/Controllers/Web/AdminNativeModuleController.php",
"app/Http/Controllers/Web/AdminMoneyReceiptController.php",
"app/Http/Controllers/Web/AdminExpensePaymentPageController.php",
"app/Services/JobCardService.php",
"app/Services/ExpensePaymentService.php",
"resources/views/admin/native/customers.blade.php",
"resources/views/money-receipts/index.blade.php",
"resources/views/payments/index.blade.php",
"resources/views/customers/ledger.blade.php",
"routes/web.php",
]
route_rx=re.compile(r"customer|receipt|ledger|payments|voucher|job.cards",re.I)
method_rx=re.compile(r"^\\s*(?:public|protected|private)\\s+(?:static\\s+)?function\\s+(?:update|save|store|edit|void|reverse|correct|index|show|create|customer|receipt|payment|voucher|detail|post|submit)\\w*\\s*\\(",re.I)
field_rx=re.compile(r"(?:customer_name|customer_id|customer_master|am_erp_customers|job_cards|customer_payments|workshop_payment_vouchers|payment_allocations|customer_ledger|route\\()",re.I)
def out(line):
    if private.search(line):return "[security-sensitive line omitted]"
    return line.rstrip()[:250]
for name in paths:
    p=root/name
    if not p.is_file():
        print("MISSING",name);continue
    lines=p.read_text(encoding="utf8",errors="replace").splitlines()
    print("\nFILE",name,"LINES",len(lines))
    if name=="routes/web.php":
        matches=[(i+1,l) for i,l in enumerate(lines) if route_rx.search(l)]
        for i,l in matches[:85]:print(f" R{i}: {out(l)}")
        if len(matches)>85:print(" ...",len(matches)-85,"more matching routes")
    else:
        functions=[i for i,l in enumerate(lines) if method_rx.search(l)]
        print("METHOD_CANDIDATES",len(functions))
        for i in functions[:13]:
            print(f" M{i+1}: {out(lines[i])}")
            # code excerpt, bounded to 16 lines per method
            for j in range(i+1,min(i+17,len(lines))):
                print(f" {j+1}: {out(lines[j])}")
        relevant=[(i+1,l) for i,l in enumerate(lines) if field_rx.search(l)]
        print("MATCHING_FIELDS",len(relevant))
        for i,l in relevant[:22]:print(f" E{i}: {out(l)}")
# No actual personal or financial data; only joined counters by foreign key
with tempfile.TemporaryDirectory(prefix="am171_") as folder:
    path=Path(folder)/"counts.php"
    path.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$sets=['am_erp_customers'=>'customer_id','customer_master'=>'customer_id'];
foreach($sets as $table=>$field){
 if(!Schema::hasTable($table)){echo "TABLE_MISSING $table\n";continue;}
 try{
  $r=DB::table('job_cards as j')
  ->leftJoin("$table as c",'c.id','=','j.customer_id')
  ->selectRaw("COUNT(*) as total, SUM(CASE WHEN j.customer_id IS NOT NULL THEN 1 ELSE 0 END) as with_customer_id, SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) as linked, SUM(CASE WHEN c.id IS NOT NULL AND TRIM(COALESCE(j.customer_name,'')) <> TRIM(COALESCE(c.name,'')) THEN 1 ELSE 0 END) as name_mismatch")
  ->first();
  echo "LINK_COUNTS {$table} total={$r->total} with_customer_id={$r->with_customer_id} linked={$r->linked} name_mismatch={$r->name_mismatch}\n";
 }catch(\Throwable $e){echo "LINK_CHECK_NOT_AVAILABLE $table ".get_class($e)."\n";}
}
""",encoding="utf8")
    try:
        r=subprocess.run(["php",str(path),str(root)],capture_output=True,text=True,timeout=20)
        if r.returncode:print("COUNTS_CHECK_FAILED",out(r.stderr[-250:]))
        else:print(r.stdout.rstrip())
    except Exception as ex:print("COUNTS_CHECK_UNAVAILABLE",type(ex).__name__)
print("AM171_PHASE2_READONLY_DONE")
