#!/usr/bin/env python3
"""AM260 guarded STAGING deployment: PO -> GRN stock in once -> bill match.

check   preflight WITHOUT writes
apply   create new am260 tables, backup and install authenticated pages/routes,
        block legacy stock mutators against GRN-matched purchase bills
rollback restore previous code only, preserving NEW tables and business data
        (never removes posted stock/receipt records)

No live stock, vendor purchase or JC rows changed during installation itself.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, subprocess, sys, tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM260 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM260_STAGING_ROOT_GUARD')
pkg=Path(__file__).resolve().parent
existing=[
    'routes/web.php',
    'resources/views/layouts/erp.blade.php',
    'resources/views/purchases/index.blade.php',
    'app/Services/PurchaseService.php',
    'app/Http/Controllers/Web/AdminPurchaseReturnController.php',
    'app/Http/Controllers/Web/AdminPurchaseReturnItemwiseController.php',
]
new=[
    'app/Http/Controllers/Web/AdminProcurementGrnController.php',
    'resources/views/procurement/index.blade.php',
    'resources/views/procurement/po-create.blade.php',
    'resources/views/procurement/receive.blade.php',
    'resources/views/procurement/match-bill.blade.php',
]
base=root/'storage/app/am-backups'
pointer=base/'AM260_GRN_PROCUREMENT_LAST_CODE_BACKUP.txt'
marker='AM260_PO_GRN_ROUTES'
def enc(t):return t.encode('utf-8','surrogateescape')
def txt(b):return b.decode('utf-8','surrogateescape')
def sha(b):return hashlib.sha256(b).hexdigest()
def once(s,old,ins,label):
    n=s.count(old)
    if n!=1:raise SystemExit('STOP_AM260_ANCHOR_'+label+'_COUNT_'+str(n))
    return s.replace(old,ins,1)
def run(cmd,timeout=130):
    return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am260_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def phplint(data,name):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(data);p=Path(f.name)
    try:
        out=run(['php','-l',str(p)],60)
        if out.returncode:raise SystemExit('STOP_AM260_PHP_LINT_'+name+': '+(out.stdout+out.stderr)[-900:])
    finally:p.unlink(missing_ok=True)
def blade(data,name):
    with tempfile.TemporaryDirectory(prefix='am260blade_') as td:
        p=Path(td)/'input.blade.php';q=Path(td)/'compiled.php';p.write_bytes(data)
        snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        out=run(['php','-r',snippet,str(p),str(q)],65)
        if out.returncode or not q.exists():
            raise SystemExit('STOP_AM260_BLADE_'+name+': '+(out.stdout+out.stderr)[-1000:])
        phplint(q.read_bytes(),'COMPILED_'+name)

def security_routes():
    snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$base=$routes->getByName('erp.purchases.index');
if(!$base)exit(80);
foreach([
 'erp.procurement.index'=>'GET',
 'erp.procurement.po.create'=>'GET',
 'erp.procurement.po.store'=>'POST',
 'erp.procurement.receive'=>'GET',
 'erp.procurement.receive.store'=>'POST',
 'erp.procurement.bill.create'=>'GET',
 'erp.procurement.bill.store'=>'POST',
] as $name=>$method){
 $route=$routes->getByName($name);
 if(!$route||!in_array($method,$route->methods(),true)||
    $route->gatherMiddleware()!==$base->gatherMiddleware() ||
    !str_contains(implode('|',$route->gatherMiddleware()),'EnsureErpAdmin'))exit(81);
}
echo 'AM260_SEVEN_AUTHENTICATED_ADMIN_ROUTES_PASS';"""
    out=run(['php','-r',snippet],75)
    if out.returncode or 'AM260_SEVEN_AUTHENTICATED_ADMIN_ROUTES_PASS' not in out.stdout:
        raise RuntimeError('AM260_ROUTES_AUTH_GUARD_FAILED '+(out.stdout+out.stderr)[-800:])
    print('AM260_SEVEN_AUTHENTICATED_ADMIN_ROUTES_PASS')
def initial_database():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$S=\Illuminate\Support\Facades\Schema::class;
$required=[
'workshop_vendors'=>['id','vendor_name','active'],
'job_cards'=>['id','job_no','status','vehicle_reg_no'],
'parts_master'=>['id','part_name','stock_qty','purchase_price','active'],
'inventory_movements'=>['part_master_id','movement_type','qty_in','balance_after','reference_no'],
'workshop_purchases'=>['id','purchase_no','purchase_batch_no','vendor_id','supplier_invoice','record_status','part_master_id'],
'workshop_purchase_batches'=>['batch_no','vendor_id','purchase_type','grand_total'],
'purchase_audit_logs'=>['purchase_id','action','after_json']
];
foreach($required as $table=>$cols){
 if(!$S::hasTable($table))exit(70);
 foreach($cols as $col)if(!$S::hasColumn($table,$col))exit(71);
}
echo 'AM260_EXISTING_FINANCIAL_STOCK_SCHEMA_PASS';"""
    out=run(['php','-r',code],65)
    if out.returncode or 'AM260_EXISTING_FINANCIAL_STOCK_SCHEMA_PASS' not in out.stdout:
        raise SystemExit('STOP_AM260_EXISTING_SCHEMA_CONFLICT '+(out.stdout+out.stderr)[-600:])
    print('AM260_EXISTING_FINANCIAL_STOCK_SCHEMA_PASS')
def method_patch(service,start,end,patch,label):
    a=service.find(start);b=service.find(end,a+len(start))
    if a<0 or b<0:raise SystemExit('STOP_AM260_METHOD_BOUNDARY_'+label)
    part=service[a:b]
    updated=patch(part)
    return service[:a]+updated+service[b:]

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM260_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in backup.parents or not backup.name.startswith('AM260_GRN_CODE_'):
        raise SystemExit('STOP_AM260_BACKUP_LOCATION_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,digest in manifest['installed'].items():
        if not (root/path).is_file() or sha((root/path).read_bytes())!=digest:
            raise SystemExit('STOP_AM260_CANNOT_ROLLBACK_NEWER_SOURCE_'+path)
    for path in existing:atomic(root/path,(backup/path).read_bytes())
    for path in new:(root/path).unlink(missing_ok=True)
    out=run(['php','artisan','optimize:clear'],110)
    if out.returncode:raise SystemExit('AM260_CODE_RESTORED_BUT_OPTIMIZE_CLEAR_FAILED')
    print('AM260_ROLLBACK_RESTORED_SOURCE_NEW_GRN_TABLES_AND_POSTED_DATA_PRESERVED')
    sys.exit(0)

for path in existing:
    if not (root/path).is_file():raise SystemExit('STOP_AM260_BASE_FILE_NOT_FOUND_'+path)
for path in new:
    if not (pkg/'files'/path).is_file():raise SystemExit('STOP_AM260_PACKAGE_FILE_NOT_FOUND_'+path)
original={path:(root/path).read_bytes() for path in existing}
route_source=txt(original['routes/web.php'])
if marker in route_source and all((root/path).is_file() for path in new):
    print('AM260_ALREADY_INSTALLED')
    security_routes()
    sys.exit(0)
if marker in route_source or any((root/path).exists() for path in new):
    raise SystemExit('STOP_AM260_PARTIAL_INSTALL_OR_FILE_CONFLICT')

routes_anchor="    Route::get('/purchases', [AdminPurchasePageController::class,'index'])->name('erp.purchases.index');"
routes_insert=(
"    // AM260_PO_GRN_ROUTES: physical stock receipt now, vendor invoice matching later. Owner/Admin only.\n"
"    Route::get('/procurement', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'index'])->name('erp.procurement.index');\n"
"    Route::get('/procurement/po/create', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'newPo'])->name('erp.procurement.po.create');\n"
"    Route::post('/procurement/po', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'savePo'])->name('erp.procurement.po.store');\n"
"    Route::get('/procurement/receive', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'newReceipt'])->name('erp.procurement.receive');\n"
"    Route::post('/procurement/receive', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'postReceipt'])->name('erp.procurement.receive.store');\n"
"    Route::get('/procurement/bills/new', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'newBill'])->name('erp.procurement.bill.create');\n"
"    Route::post('/procurement/bills', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'postBill'])->name('erp.procurement.bill.store');\n\n"
)
patched={'routes/web.php':enc(once(route_source,routes_anchor,routes_insert+routes_anchor,'PURCHASE_ROUTE'))}

nav_anchor="        <a href=\"{{ route('erp.purchases.index') }}\" class=\"{{ request()->routeIs('erp.purchases.*')?'active':'' }}\"><span>📦</span> Purchase</a>"
nav_insert="\n        {{-- AM260_PO_GRN_SIDEBAR: Challan Procurement before Purchase Bill --}}\n        <a href=\"{{ route('erp.procurement.index') }}\" class=\"{{ request()->routeIs('erp.procurement.*')?'active':'' }}\"><span>🚚</span> Vendor Challan / GRN</a>"
nav=txt(original['resources/views/layouts/erp.blade.php'])
patched['resources/views/layouts/erp.blade.php']=enc(once(nav,nav_anchor,nav_anchor+nav_insert,'PURCHASE_SIDEBAR'))

purchase_view=txt(original['resources/views/purchases/index.blade.php'])
after_section=(
"@section('content')\n"
"{{-- AM260_PO_GRN_PURCHASE_REGISTER_SHORTCUT: Vendor Challan / Bill Pending workflow --}}\n"
"<div style=\"display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0 14px;padding:12px;background:#f0f9f5;border:1px solid #cee6da;border-radius:10px;font-size:12px\">\n"
"  <strong style=\"color:#0e6756\">Vendor Challan / GRN:</strong>\n"
"  <a href=\"{{ route('erp.procurement.po.create') }}\" style=\"color:#134e75;font-weight:800\">New PO</a>\n"
"  <a href=\"{{ route('erp.procurement.receive') }}\" style=\"color:#134e75;font-weight:800\">Receive Stock Against Challan</a>\n"
"  <a href=\"{{ route('erp.procurement.bill.create') }}\" style=\"color:#134e75;font-weight:800\">Purchase Against PO + JC + GRN (Bill Pending)</a>\n"
"  <a href=\"{{ route('erp.procurement.index') }}\" style=\"color:#0d7969;font-weight:900\">Open Procurement Register</a>\n"
"</div>"
)
patched['resources/views/purchases/index.blade.php']=enc(once(purchase_view,"@section('content')",after_section,'PURCHASE_REGISTER_CONTENT'))

service=txt(original['app/Services/PurchaseService.php'])
def patch_history(part):
    anchor="            $row['against_summary'] = $allocations->count() === 1 ? $allocations->first() : $allocations->count().' allocations';"
    addon=(
"\n            // AM260_GRN_PURCHASE_AGAINST: append actual PO/GRN references to the Purchase Register.\n"
"            if (\\Illuminate\\Support\\Facades\\Schema::hasTable('am260_grn_bills') && !empty($row['purchase_batch_no'])) {\n"
"                $linked=DB::table('am260_grn_bills')->where('batch_no',(string)$row['purchase_batch_no'])->first(['id']);\n"
"                if ($linked) {\n"
"                    $refs=DB::table('am260_grn_bill_allocations as x')\n"
"                       ->join('am260_grn_lines as gl','gl.id','=','x.grn_line_id')\n"
"                       ->join('am260_grns as gr','gr.id','=','gl.grn_id')\n"
"                       ->leftJoin('am260_purchase_orders as po','po.id','=','gr.po_id')\n"
"                       ->leftJoin('job_cards as jc2','jc2.id','=','gl.job_card_id')\n"
"                       ->where('x.bill_id',$linked->id)\n"
"                       ->get(['gr.grn_no','po.po_no','jc2.job_no']);\n"
"                    $summaries=$refs->map(static function($ref){\n"
"                       return ($ref->job_no?:'STOCK').' + '.($ref->po_no?:'NO PO').' + '.$ref->grn_no;\n"
"                    })->unique()->values()->take(4)->all();\n"
"                    if ($summaries)$row['against_summary']=implode(' ; ',$summaries);\n"
"                }\n"
"            }"
    )
    return once(part,anchor,anchor+addon,'HISTORY_AGAINST')
service=method_patch(service,"    public function historyBills(","    public function searchJobs",patch_history,'HISTORY')
def patch_save(part):
    anchor="            $invoice = $this->clean($data['supplier_invoice'] ?? null, 100);"
    addon=(
"\n            // AM260_ALREADY_GRN_MATCHED: prevent rebooking an already matched supplier invoice as normal Purchase stock-in.\n"
"            if ($invoice && \\Illuminate\\Support\\Facades\\Schema::hasTable('am260_grn_bills') &&\n"
"                DB::table('am260_grn_bills')->where('vendor_id',$vendorId)->where('supplier_invoice',$invoice)->exists())\n"
"                throw ValidationException::withMessages(['supplier_invoice'=>'This supplier invoice is already GRN-matched. Do not book it again with stock-in.']);"
    )
    return once(part,anchor,anchor+addon,'CREATE_DUPLICATE_INVOICE_GUARD')
service=method_patch(service,"    public function savePartBatch(","    public function updatePartBatch",patch_save,'SAVE_PART_BATCH')
def patch_update(part):
    anchor="            $batchNo = trim((string)$anchor->purchase_batch_no);"
    addon=(
"\n            // AM260_MATCHED_BILL_LOCK: normal edit would alter stock that was already received by GRN.\n"
"            if (\\Illuminate\\Support\\Facades\\Schema::hasTable('am260_grn_bills') &&\n"
"                DB::table('am260_grn_bills')->where('batch_no',$batchNo)->exists())\n"
"                throw ValidationException::withMessages(['purchase'=>'GRN-linked Purchase Bill cannot use normal stock-changing Edit. Use controlled GRN bill correction.']);"
    )
    return once(part,anchor,anchor+addon,'EDIT_GRN_BILL_LOCK')
service=method_patch(service,"    public function updatePartBatch(","    public function delete(",patch_update,'UPDATE_PART_BATCH')
def patch_delete(part):
    anchor="            $before = $purchase->toArray();"
    addon=(
"\n            // AM260_GRN_BILL_DELETE_LOCK: no duplicate physical stock reversal for GRN-matched Purchase Bills.\n"
"            if (\\Illuminate\\Support\\Facades\\Schema::hasTable('am260_grn_bill_allocations') &&\n"
"                DB::table('am260_grn_bill_allocations')->where('purchase_id',$purchase->id)->exists())\n"
"                throw ValidationException::withMessages(['purchase'=>'GRN-matched bill cannot use normal stock-reversing delete. Reconcile/credit separately.']);"
    )
    return once(part,anchor,anchor+addon,'DELETE_GRN_BILL_LOCK')
service=method_patch(service,"    public function delete(","    public function negativeStock",patch_delete,'DELETE')
patched['app/Services/PurchaseService.php']=enc(service)

original_returns=txt(original['app/Http/Controllers/Web/AdminPurchaseReturnController.php'])
anchor="    public function store(Request $request,int $purchase): RedirectResponse\n    {"
addon=(
"\n        // AM260_GRN_RETURN_LOCK: supplier returns must not silently reverse GRN already-issued stock.\n"
"        if (\\Illuminate\\Support\\Facades\\Schema::hasTable('am260_grn_bills')) {\n"
"            $batch=DB::table('workshop_purchases')->where('id',$purchase)->value('purchase_batch_no');\n"
"            if ($batch && DB::table('am260_grn_bills')->where('batch_no',$batch)->exists())\n"
"                throw ValidationException::withMessages(['purchase'=>'GRN-matched supplier bill needs a physically verified return/reconciliation workflow. Generic stock return blocked.']);\n"
"        }"
)
patched['app/Http/Controllers/Web/AdminPurchaseReturnController.php']=enc(once(original_returns,anchor,anchor+addon,'LEGACY_PURCHASE_RETURN_LOCK'))
itemwise=txt(original['app/Http/Controllers/Web/AdminPurchaseReturnItemwiseController.php'])
anchor2="        $ids=array_map(static fn($l)=>(int)$l['purchase_id'],$lineEntries);"
addon2=(
"\n        // AM260_GRN_ITEMWISE_RETURN_LOCK: do not silently decrement stock already received/issued by GRN.\n"
"        if (\\Illuminate\\Support\\Facades\\Schema::hasTable('am260_grn_bill_allocations') &&\n"
"            DB::table('am260_grn_bill_allocations')->whereIn('purchase_id',$ids)->exists())\n"
"            $this->reject('lines','GRN-matched purchase requires a separate physically verified return/reconciliation. Standard Purchase Return is blocked.');"
)
patched['app/Http/Controllers/Web/AdminPurchaseReturnItemwiseController.php']=enc(once(itemwise,anchor2,anchor2+addon2,'ITEMWISE_RETURN_LOCK'))

for path in new:patched[path]=(pkg/'files'/path).read_bytes()
for path in [
 'routes/web.php','app/Services/PurchaseService.php',
 'app/Http/Controllers/Web/AdminPurchaseReturnController.php',
 'app/Http/Controllers/Web/AdminPurchaseReturnItemwiseController.php',
 new[0]]:
    phplint(patched[path],Path(path).stem)
for path in ['resources/views/layouts/erp.blade.php','resources/views/purchases/index.blade.php',*new[1:]]:
    blade(patched[path],Path(path).stem)
phplint((pkg/'migrate.php').read_bytes(),'AM260_MIGRATE')
initial_database()
print('AM260_CODE_BLADE_MUTATION_GUARD_PREFLIGHT_PASS')
if mode=='check':
    print('AM260_CHECK_PASSED_NO_SOURCE_OR_STOCK_CHANGED')
    sys.exit(0)

backup=base/('AM260_GRN_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in original.items():
    p=backup/path;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
# Migration is additive and contains no changes to pre-existing tables.
migrate=run(['php',str(pkg/'migrate.php')],120)
if migrate.returncode or 'AM260_SCHEMA_READY_NO_EXISTING_FINANCIAL_DATA_CHANGED' not in migrate.stdout:
    raise SystemExit('STOP_AM260_NEW_TABLE_MIGRATION_FAILED '+(migrate.stdout+migrate.stderr)[-1000:])
print(migrate.stdout[-1200:])
written=[]
try:
    for path,data in patched.items():
        if path in original and (root/path).read_bytes()!=original[path]:
            raise RuntimeError('AM260_CONCURRENT_EDIT_CONFLICT_'+path)
        atomic(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        out=run(cmd,160)
        if out.returncode:raise RuntimeError('AM260_CACHE_BUILD_FAILED '+(out.stdout+out.stderr)[-850:])
    security_routes()
except BaseException as e:
    for path in written:
        if path in original:atomic(root/path,original[path])
        else:(root/path).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],110)
    raise SystemExit('STOP_AM260_DEPLOY_ROLLED_BACK_CODE_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{path:sha(b) for path,b in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM260_STAGING_PO_GRN_AND_BILL_MATCHING_CODE_INSTALLED')
print('AM260_STOCK_POSTING_ONLY_ON_EXPLICIT_AUTHENTICATED_GRN_SUBMIT')
print('AM260_PRODUCTION_NOT_TOUCHED')
print('AM260_NO_JOB_OR_PURCHASE_OR_STOCK_RECORDS_MODIFIED_BY_DEPLOY')
print('AM260_BACKUP='+str(backup))
