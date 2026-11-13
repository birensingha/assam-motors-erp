#!/usr/bin/env python3
"""AM253 guarded STAGING Purchase Return integration.
Usage: python3 install.py check|prepare|apply|rollback STAGING_ROOT
No historical bill/payment is changed by install. One mandatory verified SQL
backup must precede migration, which must precede apply. Existing return rows
are preserved even on code rollback.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','prepare','apply','rollback'):
    raise SystemExit('USAGE_AM253 install.py check|prepare|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM253_NOT_STAGING')
pkg=Path(__file__).resolve().parent/'files'
migration='database/migrations/2026_11_13_140000_create_am_purchase_returns.php'
routes='routes/web.php'
purchaseController='app/Http/Controllers/Web/AdminPurchasePageController.php'
purchaseService='app/Services/PurchaseService.php'
expensePaymentService='app/Services/ExpensePaymentService.php'
multiPaymentService='app/Services/MultiPurchasePaymentService.php'
vendorService='app/Services/VendorService.php'
oslController='app/Http/Controllers/Web/AdminOslPurchaseBatchController.php'
purchaseIndex='resources/views/purchases/index.blade.php'
purchaseShow='resources/views/purchases/show.blade.php'
newController='app/Http/Controllers/Web/AdminPurchaseReturnController.php'
newViews=['resources/views/purchase-returns/'+name+'.blade.php' for name in ['choose','create','show','print']]
existing=[routes,purchaseController,purchaseService,expensePaymentService,multiPaymentService,vendorService,oslController,purchaseIndex,purchaseShow]
new=[newController]+newViews
backup_base=root/'storage/app/am-backups'
pointer=backup_base/'AM253_LAST_SUCCESSFUL_CODE_BACKUP.txt'
marker='AM253_PURCHASE_RETURN_INTEGRATION'

def enc(s):return s.encode('utf-8','surrogateescape')
def txt(b):return b.decode('utf-8','surrogateescape')
def sha(blob):return hashlib.sha256(blob).hexdigest()
def run(args,timeout=100):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(src,old,new,tag):
    count=src.count(old)
    if count!=1:raise SystemExit('STOP_AM253_ANCHOR_'+tag+'_MATCHES_'+str(count))
    return src.replace(old,new,1)
def atomic(path,blob):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am253_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(blob)
        os.chmod(tmp,path.stat().st_mode&0o777 if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(blob,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(blob);temp=Path(f.name)
    try:
        r=run(['php','-l',str(temp)],35)
        if r.returncode:raise SystemExit('STOP_AM253_PHP_LINT_'+tag+' '+(r.stdout+r.stderr)[-550:])
    finally:temp.unlink(missing_ok=True)
def blade_lint(blob,tag):
    with tempfile.TemporaryDirectory(prefix='am253blade_') as td:
        a=Path(td)/'view.blade.php';b=Path(td)/'compiled.php';a.write_bytes(blob)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=run(['php','-r',code,str(a),str(b)],50)
        if r.returncode or not b.is_file():raise SystemExit('STOP_AM253_BLADE_COMPILE_'+tag+' '+(r.stdout+r.stderr)[-550:])
        lint(b.read_bytes(),'COMPILED_'+tag)
def schema_check(require_new=False):
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$S=\Illuminate\Support\Facades\Schema::class;
$required=[
'workshop_purchases'=>['id','purchase_no','purchase_batch_no','vendor_id','purchase_type','qty','part_master_id','total_amount','taxable_amount','gst_amount','record_status'],
'workshop_purchase_batches'=>['batch_no','grand_total','record_status'],
'workshop_vendors'=>['id','vendor_name'],
'parts_master'=>['id','stock_qty'],
'inventory_movements'=>['part_master_id','qty_in','qty_out','balance_after','movement_type','reference_no','job_card_id'],
'workshop_payment_vouchers'=>['source_type','source_id','source_reference','amount','record_status'],
'workshop_payment_allocations'=>['payment_voucher_id','purchase_ref','amount'],
'purchase_audit_logs'=>['purchase_id','purchase_no','batch_no','action','before_json','after_json'],
'job_card_parts'=>['job_card_id','part_master_id','issue_status']];
foreach($required as $table=>$columns){
 if(!$S::hasTable($table))exit(51);
 foreach($columns as $col)if(!$S::hasColumn($table,$col))exit(52);
}
$n=(int)$S::hasTable('am_purchase_returns')+(int)$S::hasTable('am_purchase_return_lines');
if($n===1){fwrite(STDERR,'AM253_PARTIAL_RETURN_SCHEMA');exit(53);}
if($n===2){
 $ncols=['am_purchase_returns'=>['id','return_no','request_key','source_purchase_id','source_batch_no','purchase_type','vendor_id','grand_total','record_status','return_date','void_reason'],
 'am_purchase_return_lines'=>['return_id','purchase_id','part_master_id','qty','total_amount','taxable_amount','gst_amount']];
 foreach($ncols as $t=>$cols)foreach($cols as $c)if(!$S::hasColumn($t,$c))exit(54);
 echo 'AM253_RETURN_TABLES_READY';
}else echo 'AM253_RETURN_TABLES_REQUIRE_MIGRATION';
"""
    r=run(['php','-r',code],55)
    if r.returncode:raise SystemExit('STOP_AM253_SCHEMA '+(r.stdout+r.stderr)[-600:])
    output=r.stdout.strip()
    if require_new and output!='AM253_RETURN_TABLES_READY':
        raise SystemExit('STOP_AM253_RUN_MIGRATION_AFTER_VERIFIED_SQL_BACKUP')
    print(output)
def source_route_preflight():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['erp.purchases.index','erp.purchases.create','erp.purchases.show',
'erp.purchases.edit','erp.purchases.osl-batch.edit'] as $name)
 if(!\Illuminate\Support\Facades\Route::has($name))exit(41);
echo 'AM253_BASE_ROUTES_PRESENT';"""
    r=run(['php','-r',code],40)
    if r.returncode:raise SystemExit('STOP_AM253_BASE_ROUTES '+(r.stdout+r.stderr)[-250:])
def route_verification():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$all=\Illuminate\Support\Facades\Route::getRoutes();
$old=$all->getByName('erp.purchases.store');
if(!$old)exit(40);
foreach(['erp.purchase-returns.create'=>'GET','erp.purchases.return.form'=>'GET',
'erp.purchases.return.store'=>'POST','erp.purchase-returns.show'=>'GET',
'erp.purchase-returns.print'=>'GET','erp.purchase-returns.void'=>'POST'] as $name=>$method){
 $r=$all->getByName($name);
 if(!$r||!in_array($method,$r->methods(),true)||
    $r->gatherMiddleware()!==$old->gatherMiddleware())exit(42);
 if(!str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(43);
}
echo 'AM253_SIX_ADMIN_PROTECTED_RETURN_ROUTES_PASS';"""
    r=run(['php','-r',code],50)
    if r.returncode or 'AM253_SIX_ADMIN_PROTECTED_RETURN_ROUTES_PASS' not in r.stdout:
        raise SystemExit('STOP_AM253_ADMIN_ROUTE_VERIFY '+(r.stdout+r.stderr)[-450:])
    print('AM253_SIX_ADMIN_PROTECTED_RETURN_ROUTES_PASS')
def read_query_smoke():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$DB=\Illuminate\Support\Facades\DB::class;
$DB::table('am_purchase_returns')->where('record_status','POSTED')->limit(1)->get();
$DB::table('am_purchase_return_lines as l')->join('am_purchase_returns as r','r.id','=','l.return_id')
->where('r.record_status','POSTED')->selectRaw('l.purchase_id,SUM(l.total_amount) AS credited')->groupBy('l.purchase_id')->limit(1)->get();
echo 'AM253_PURCHASE_RETURN_SQL_READONLY_PASS';"""
    r=run(['php','-r',code],50)
    if r.returncode or 'AM253_PURCHASE_RETURN_SQL_READONLY_PASS' not in r.stdout:
        raise SystemExit('STOP_AM253_SQL_QUERY '+(r.stdout+r.stderr)[:1500])
    print('AM253_PURCHASE_RETURN_SQL_READONLY_PASS')

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM253_NO_CODE_BACKUP_POINTER')
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith('AM253_PURCHASE_RETURN_CODE_'):
        raise SystemExit('STOP_AM253_INVALID_CODE_BACKUP')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,digest in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=digest:
            raise SystemExit('STOP_AM253_NEWER_SOURCE_CONFLICT_'+rel)
    for rel in existing:atomic(root/rel,(backup/rel).read_bytes())
    for rel in new:(root/rel).unlink(missing_ok=True)
    r=run(['php','artisan','optimize:clear'],90)
    if r.returncode:raise SystemExit('AM253_CODE_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM253_PURCHASE_RETURN_CODE_ROLLED_BACK')
    print('AM253_PURCHASE_RETURN_TABLES_AND_DOCUMENT_HISTORY_PRESERVED')
    sys.exit(0)

if not (pkg/migration).is_file():raise SystemExit('STOP_AM253_MIGRATION_NOT_PACKAGED')
for rel in existing:
    if not (root/rel).is_file():raise SystemExit('STOP_AM253_MISSING_BASE_'+rel)
for rel in new:
    if not (pkg/rel).is_file():raise SystemExit('STOP_AM253_MISSING_PACKAGE_'+rel)

raw={rel:(root/rel).read_bytes() for rel in existing}
src={rel:txt(raw[rel]) for rel in existing}
if marker in src[routes] and all((root/rel).is_file() for rel in new):
    schema_check(True)
    print('AM253_ALREADY_INSTALLED')
    sys.exit(0)
if marker in src[routes] or any((root/rel).exists() for rel in new):
    raise SystemExit('STOP_AM253_PARTIAL_INSTALL_OR_UNEXPECTED_NEW_FILE')

# 1. Route definitions inherit existing secure ERP admin group.
routeAdd=(
"    // AM253_PURCHASE_RETURN_INTEGRATION: supplier debit notes, original bills remain untouched.\n"
"    Route::get('/purchase-returns/create', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'create'])->name('erp.purchase-returns.create');\n"
"    Route::get('/purchase-returns/{return}/print', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'print'])->whereNumber('return')->name('erp.purchase-returns.print');\n"
"    Route::post('/purchase-returns/{return}/void', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'void'])->whereNumber('return')->name('erp.purchase-returns.void');\n"
"    Route::get('/purchase-returns/{return}', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'show'])->whereNumber('return')->name('erp.purchase-returns.show');\n"
"    Route::get('/purchases/{purchase}/return', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'form'])->whereNumber('purchase')->name('erp.purchases.return.form');\n"
"    Route::post('/purchases/{purchase}/return', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'store'])->whereNumber('purchase')->name('erp.purchases.return.store');\n")
patched={}
patched[routes]=enc(once(src[routes],
    "    Route::get('/purchases', [AdminPurchasePageController::class,'index'])",
    routeAdd+"    Route::get('/purchases', [AdminPurchasePageController::class,'index'])","NEW_ROUTES"))

# 2. One Purchase Bill / one Purchase Return per register row, negative amounts.
pc=src[purchaseController]
pc=once(pc,
    "'rows' => $service->historyBills($filters),",
    "'rows' => $this->am253Rows($service->historyBills($filters), $filters),",
    'REGISTER_SOURCE')
addMethod=r'''
    /** AM253_PURCHASE_RETURN_INTEGRATION: read-only merged bill and return rows. */
    private function am253Rows(array $bills, array $filters): array
    {
        $q=trim((string)($filters['q']??''));
        $returns=DB::table('am_purchase_returns');
        if($q!==''){
            $like='%'.$q.'%';
            $returns->where(function($w)use($like){
                $w->where('return_no','like',$like)->orWhere('source_batch_no','like',$like)
                  ->orWhere('vendor_name','like',$like)->orWhere('supplier_credit_note','like',$like)
                  ->orWhere('reason','like',$like);
            });
        }
        foreach($returns->orderByDesc('id')->limit(250)->get() as $r){
            $void=(string)$r->record_status!=='POSTED';
            $bills[]=[
                'id'=>(int)$r->source_purchase_id,'return_id'=>(int)$r->id,
                'is_return'=>true,'return_status'=>(string)$r->record_status,
                'purchase_no'=>(string)$r->return_no,'purchase_batch_no'=>(string)$r->return_no,
                'purchase_type'=>(string)$r->purchase_type,'purchase_date'=>(string)$r->return_date,
                'vendor_name'=>(string)$r->vendor_name,'supplier_name'=>(string)$r->vendor_name,
                'supplier_invoice'=>(string)($r->supplier_credit_note??''),
                'item_count'=>(int)$r->item_count,'qty'=>$void?0:-(float)$r->total_qty,
                'total_amount'=>$void?0:-(float)$r->grand_total,
                'against_summary'=>'Return against '.(string)$r->source_batch_no,
                'vehicle_summary'=>'',
            ];
        }
        usort($bills,static function($a,$b){
            $c=strcmp((string)$b['purchase_date'],(string)$a['purchase_date']);
            if($c!==0)return $c;
            return (int)($b['return_id']??$b['id'])<=>(int)($a['return_id']??$a['id']);
        });
        return array_slice($bills,0,250);
    }

'''
pc=once(pc,
    '    public function create(Request $request, PurchaseService $service): View',
    addMethod+'    public function create(Request $request, PurchaseService $service): View',
    'REGISTER_MERGE_METHOD')
patched[purchaseController]=enc(pc)

# 3. Guard original Parts bill edit/delete after a posted return.
ps=src[purchaseService]
batchGuard=r'''
            // AM253_PURCHASE_RETURN_INTEGRATION: original bill is immutable after supplier return.
            if (\Illuminate\Support\Facades\Schema::hasTable('am_purchase_returns')
                && DB::table('am_purchase_returns')->where('source_batch_no',$batchNo)
                    ->where('purchase_type','PART')->where('record_status','POSTED')->exists())
                throw ValidationException::withMessages([
                    'purchase'=>'Purchase has a posted Purchase Return. Void the return before editing the original bill.'
                ]);
'''
ps=once(ps,'            $batchNo = trim((string)$anchor->purchase_batch_no);\n',
    '            $batchNo = trim((string)$anchor->purchase_batch_no);\n'+batchGuard,
    'PART_BATCH_EDIT_GUARD')
deleteGuard=r'''
            // AM253_PURCHASE_RETURN_INTEGRATION: never delete an original bill whose return is posted.
            $am253Ref=trim((string)($purchase->purchase_batch_no?:$purchase->purchase_no));
            if(\Illuminate\Support\Facades\Schema::hasTable('am_purchase_returns') &&
                DB::table('am_purchase_returns')->where('source_batch_no',$am253Ref)
                  ->where('record_status','POSTED')->exists())
                throw ValidationException::withMessages(['purchase'=>'Posted Purchase Return exists. Void it before deleting the original purchase.']);
'''
ps=once(ps,
    "            $before = $purchase->toArray();\n            if (strtoupper((string)$purchase->purchase_type) === 'PART') {",
    "            $before = $purchase->toArray();\n"+deleteGuard+"            if (strtoupper((string)$purchase->purchase_type) === 'PART') {",
    'PURCHASE_DELETE_GUARD')
patched[purchaseService]=enc(ps)

# 4. OSL purchase editor/void must also refuse edits after a supplier return.
osl=src[oslController]
oslGuard=r'''
        // AM253_PURCHASE_RETURN_INTEGRATION: keep original OSL source immutable after posted return.
        if(Schema::hasTable('am_purchase_returns')){
            $ref=$batchNo!==''?$batchNo:(string)($lines[0]->purchase_no??'');
            if($ref!=='' && DB::table('am_purchase_returns')
                ->where('source_batch_no',$ref)->where('purchase_type','OSL')
                ->where('record_status','POSTED')->exists())
                throw ValidationException::withMessages(['purchase'=>'OSL Purchase has posted supplier return. Void the return before editing or voiding the original OSL bill.']);
        }
'''
osl=once(osl,
    '        $ids=array_map(static fn($p)=>(int)$p->id,$lines);',
    oslGuard+'        $ids=array_map(static fn($p)=>(int)$p->id,$lines);',
    'OSL_PURCHASE_EDIT_VOID_GUARD')
patched[oslController]=enc(osl)

# 5. Vendor payables: subtract credits from posted return documents, and stop overpayment.
ep=src[expensePaymentService]
ep=once(ep,
    "        foreach($purchaseGroups as $p){ $billTotal=(float)($batchTotals[$p->src_ref] ?? $p->total); $out=round($billTotal-",
    """        // AM253_PURCHASE_RETURN_INTEGRATION: returns lower supplier payable, not cash paid.
        $am253PartCredits=DB::table('am_purchase_returns')->where('purchase_type','PART')
            ->where('record_status','POSTED')->selectRaw('source_batch_no,SUM(grand_total) as credit')
            ->groupBy('source_batch_no')->pluck('credit','source_batch_no');
        foreach($purchaseGroups as $p){ $billTotal=(float)($batchTotals[$p->src_ref] ?? $p->total); $out=round($billTotal-(float)($am253PartCredits[$p->src_ref]??0)-""",
    'PAYABLE_PART_NET_CREDIT')
ep=once(ep,
    "        foreach($osls as $o){ $out=round((float)$o->total_amount-",
    """        // AM253_PURCHASE_RETURN_INTEGRATION: individual OSL lines are payment sources.
        $am253OslCredits=DB::table('am_purchase_return_lines as l')
            ->join('am_purchase_returns as r','r.id','=','l.return_id')
            ->where('r.record_status','POSTED')->where('r.purchase_type','OSL')
            ->selectRaw('l.purchase_id,SUM(l.total_amount) as credit')
            ->groupBy('l.purchase_id')->pluck('credit','purchase_id');
        foreach($osls as $o){ $out=round((float)$o->total_amount-(float)($am253OslCredits[$o->id]??0)-""",
    'PAYABLE_OSL_NET_CREDIT')
ep=once(ep,
    "            return ['source_id'=>$id,'source_reference'=>($p->purchase_batch_no ?: $p->purchase_no)",
    """            $am253Credit=(float)DB::table('am_purchase_return_lines as l')
                ->join('am_purchase_returns as r','r.id','=','l.return_id')
                ->where('l.purchase_id',$id)->where('r.record_status','POSTED')
                ->where('r.purchase_type','OSL')->sum('l.total_amount');
            return ['source_id'=>$id,'source_reference'=>($p->purchase_batch_no ?: $p->purchase_no)""",
    'PAYMENT_OSL_SOURCE_CREDIT_QUERY')
ep=once(ep,
    "'outstanding'=>max(0,round((float)$p->total_amount-$paid,2))",
    "'outstanding'=>max(0,round((float)$p->total_amount-$am253Credit-$paid,2))",
    'PAYMENT_OSL_SOURCE_NET')
ep=once(ep,
    "            $total=$batchTotal!==null ? (float)$batchTotal : (float)$rows->sum('total_amount'); $first=$rows->first(); $paid=",
    """            $total=$batchTotal!==null ? (float)$batchTotal : (float)$rows->sum('total_amount');
            $am253Credit=(float)DB::table('am_purchase_returns')->where('source_batch_no',$ref)
                ->where('purchase_type','PART')->where('record_status','POSTED')->sum('grand_total');
            $first=$rows->first(); $paid=""",
    'PAYMENT_PART_SOURCE_CREDIT_QUERY')
ep=once(ep,
    "'outstanding'=>max(0,round($total-$paid,2))",
    "'outstanding'=>max(0,round($total-$am253Credit-$paid,2))",
    'PAYMENT_PART_SOURCE_NET')
patched[expensePaymentService]=enc(ep)

# 6. Multibill payment validations must see reduced supplier payable too.
mp=src[multiPaymentService]
mp=once(mp,
    "                    $due=round((float)$osl->total_amount-$direct-$allocated,2);",
    """                    // AM253_PURCHASE_RETURN_INTEGRATION: supplier return credit is NOT a payment.
                    $am253Credit=(float)DB::table('am_purchase_return_lines as l')
                        ->join('am_purchase_returns as r','r.id','=','l.return_id')
                        ->where('l.purchase_id',$id)->where('r.purchase_type','OSL')
                        ->where('r.record_status','POSTED')->sum('l.total_amount');
                    $due=round((float)$osl->total_amount-$am253Credit-$direct-$allocated,2);""",
    'MULTI_OSL_BALANCE')
mp=once(mp,
    "                $available=round($dueTotal-$single-$multi,2);",
    """                $am253Credit=(float)DB::table('am_purchase_returns')->where('source_batch_no',$ref)
                    ->where('purchase_type','PART')->where('record_status','POSTED')->sum('grand_total');
                $available=round($dueTotal-$am253Credit-$single-$multi,2);""",
    'MULTI_PART_BALANCE')
patched[multiPaymentService]=enc(mp)

# 7. Vendor Ledger: one dated supplier debit note in same chronological ledger.
vs=src[vendorService]
vs=once(vs,
    '        $opening = $openingPurchases + $openingExpenses - $openingLegacyPayments - $openingUnifiedPayments;',
    """        $opening = $openingPurchases + $openingExpenses - $openingLegacyPayments - $openingUnifiedPayments;
        // AM253_PURCHASE_RETURN_INTEGRATION: earlier returns reduce opening vendor liability.
        if($from!=='')$opening-=(float)DB::table('am_purchase_returns')
            ->where('vendor_id',$vendorId)->where('record_status','POSTED')
            ->where('return_date','<',$from)->sum('grand_total');""",
    'VENDOR_OPENING_CREDIT')
vs=once(vs,
    "        usort($entries, fn($a,$b)=>[$a['sort_date'],$a['sort_type'],$a['id']] <=> [$b['sort_date'],$b['sort_type'],$b['id']]);",
    """        // AM253_PURCHASE_RETURN_INTEGRATION: separate negative supplier return entry.
        $am253Returns=DB::table('am_purchase_returns')->where('vendor_id',$vendorId)
            ->where('record_status','POSTED');
        if($from!=='')$am253Returns->where('return_date','>=',$from);
        if($to!=='')$am253Returns->where('return_date','<=',$to);
        foreach($am253Returns->orderBy('return_date')->orderBy('id')->get() as $r) {
            $amt=(float)$r->grand_total;
            $periodPurchases-=$amt;
            $entries[]=[
                'sort_date'=>(string)$r->return_date,'sort_type'=>1,'id'=>100000000+(int)$r->id,
                'date'=>(string)$r->return_date,'entry_no'=>(string)$r->return_no,
                'entry_type'=>'PURCHASE_RETURN','payment_kind'=>'',
                'description'=>'Supplier Return against '.(string)$r->source_batch_no.' · '.(string)$r->reason,
                'invoice_no'=>(string)($r->supplier_credit_note??''),
                'job_no'=>'','vehicle_reg_no'=>'','payment_mode'=>'','reference_no'=>'',
                'debit'=>$amt,'credit'=>0.0
            ];
        }
        usort($entries, fn($a,$b)=>[$a['sort_date'],$a['sort_type'],$a['id']] <=> [$b['sort_date'],$b['sort_type'],$b['id']]);""",
    'VENDOR_LEDGER_DEBIT_ENTRY')
patched[vendorService]=enc(vs)

# 8. Purchase Register: show both original bills and returns (signed ±).
ix=src[purchaseIndex]
ix=once(ix,
    '<p>Parts and OSL purchases · supplier bills, workshop allocation and purchase history</p>',
    '<p>Parts / OSL Purchases and Purchase Returns together · supplier bills and signed debit notes</p>',
    'REGISTER_SUBTITLE')
ix=once(ix,
    'grid-template-columns:repeat(3,minmax(0,1fr))!important;',
    'grid-template-columns:repeat(4,minmax(0,1fr))!important;',
    'FOUR_PURCHASE_ACTIONS')
ix=once(ix,
    '</style>',
    '.p18-purchase-register .p18-actions .p18-return{background:linear-gradient(135deg,#b86924,#8f4b13)!important;color:#fff!important}\n'
    '.p18-purchase-register .p18-register-table tr.am253-return-row{background:#fffaf4}\n'
    '.p18-purchase-register .p18-register-table .am253-negative{color:#ac263a!important;font-weight:850}\n'
    '.p18-purchase-register .p18-register-table .am253-void{color:#9d6970}\n</style>',
    'REGISTER_STYLE')
ix=once(ix,
    '<a class="p18-create p18-recon" href="{{ route(\'erp.purchases.reconciliation\') }}">',
    '<a class="p18-create p18-return" href="{{ route(\'erp.purchase-returns.create\') }}"><span class="p18-plus" aria-hidden="true">↩</span>Purchase Return</a>\n'
    '    <a class="p18-create p18-recon" href="{{ route(\'erp.purchases.reconciliation\') }}">',
    'PURCHASE_RETURN_CTA')
ix=once(ix,
    'data-purchase-url="{{ route(\'erp.purchases.show\',$r[\'id\']) }}"',
    'data-purchase-url="{{ !empty($r[\'return_id\']) ? route(\'erp.purchase-returns.show\',$r[\'return_id\']) : route(\'erp.purchases.show\',$r[\'id\']) }}"',
    'REGISTER_CLICK_URL')
ix=once(ix,
    '<tr data-purchase-url=',
    '<tr class="{{ !empty($r[\'return_id\'])?\'am253-return-row\':\'\' }}" data-purchase-url=',
    'REGISTER_RETURN_ROW')
ix=once(ix,
    '<td><span class="badge {{ strtolower($r[\'purchase_type\']) }}">{{ $r[\'purchase_type\'] }}</span><br><a href="{{ route(\'erp.purchases.show\',$r[\'id\']) }}"><strong>{{ $r[\'purchase_batch_no\'] ?: $r[\'purchase_no\'] }}</strong></a></td>',
    '''<td>
      @if(!empty($r['return_id']))
        <span class="badge" style="background:#fff0df;color:#934f17">PURCHASE RETURN</span>
        @if(($r['return_status']??'')==='VOID') <small class="am253-void">VOID</small> @endif
        <br><a href="{{ route('erp.purchase-returns.show',$r['return_id']) }}"><strong>{{ $r['purchase_no'] }}</strong></a>
      @else
        <span class="badge {{ strtolower($r['purchase_type']) }}">{{ $r['purchase_type'] }}</span><br>
        <a href="{{ route('erp.purchases.show',$r['id']) }}"><strong>{{ $r['purchase_batch_no'] ?: $r['purchase_no'] }}</strong></a>
      @endif
    </td>''',
    'REGISTER_DOCUMENT_REF')
ix=once(ix,
    '<td class="num money">₹{{ number_format((float)$r[\'total_amount\'],2) }}</td>',
    '''<td class="num money {{ !empty($r['return_id'])?'am253-negative':'' }}">
      @if(!empty($r['return_id'])){{ ($r['return_status']??'')==='VOID'?'VOID · ':'−' }}₹{{ number_format(abs((float)$r['total_amount']),2) }}
      @else ₹{{ number_format((float)$r['total_amount'],2) }} @endif
    </td>''',
    'REGISTER_SIGNED_AMOUNT')
ix=once(ix,
    '''        <a class="btn small" href="{{ route('erp.purchases.show',$r['id']) }}">👁 View</a>
        @if(strtoupper((string)$r['purchase_type']) === 'PART')''',
    '''        @if(!empty($r['return_id']))
          <a class="btn small" href="{{ route('erp.purchase-returns.show',$r['return_id']) }}">👁 View Return</a>
          <a class="btn small" target="_blank" rel="noopener" href="{{ route('erp.purchase-returns.print',$r['return_id']) }}">Print</a>
        @else
        <a class="btn small" href="{{ route('erp.purchases.show',$r['id']) }}">👁 View</a>
        @if(strtoupper((string)$r['purchase_type']) === 'PART')''',
    'REGISTER_RETURN_ACTION')
ix=once(ix,
    '        @endif\n    </div>\n</td>\n</tr>',
    '        @endif\n        @endif\n    </div>\n</td>\n</tr>',
    'REGISTER_ACTION_ENDIF')
patched[purchaseIndex]=enc(ix)

# 9. Purchase Detail: Add Return, without confusing it with destructive Delete.
show=src[purchaseShow]
show=once(show,
    """    <a class="btn" href="{{ route('erp.purchases.index') }}">← Back</a>""",
    """    <a class="btn" href="{{ route('erp.purchases.index') }}">← Back</a>
    <a class="btn" href="{{ route('erp.purchases.return.form',$purchase['id']) }}" style="background:#9e5a1c;color:#fff">↩ Purchase Return</a>""",
    'PURCHASE_DETAIL_RETURN_BUTTON')
patched[purchaseShow]=enc(show)

for rel in new:patched[rel]=(pkg/rel).read_bytes()

# Local PHP + Blade + existing routes. These are file-only/read-only for check/prepare.
for rel in [routes,purchaseController,purchaseService,expensePaymentService,
            multiPaymentService,vendorService,oslController,newController,migration]:
    lint((pkg/migration).read_bytes() if rel==migration else patched[rel],
         rel.split('/')[-1])
for rel in [purchaseIndex,purchaseShow]+newViews:
    blade_lint(patched[rel],rel.split('/')[-1])
source_route_preflight()
schema_check(False)
print('AM253_CONTROLLER_PHP_BLADE_AND_FINANCE_PRECHECK_PASS')

if mode=='check':
    print('AM253_CHECK_READONLY_PASS')
    sys.exit(0)
if mode=='prepare':
    dest=root/migration
    if dest.is_file():
        if dest.read_bytes()!=(pkg/migration).read_bytes():
            raise SystemExit('STOP_AM253_MIGRATION_FILENAME_CONFLICT')
        print('AM253_MIGRATION_FILE_ALREADY_PREPARED')
    else:
        atomic(dest,(pkg/migration).read_bytes())
        print('AM253_MIGRATION_PREPARED_FILE_ONLY')
    print('AM253_REQUIRE_VERIFIED_FRESH_SQL_BACKUP_BEFORE_MIGRATE')
    sys.exit(0)
schema_check(True)
read_query_smoke()
backup=backup_base/('AM253_PURCHASE_RETURN_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for rel,blob in raw.items():
    out=backup/rel;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(blob)
written=[]
try:
    for rel,blob in patched.items():
        dest=root/rel
        if rel in raw and dest.read_bytes()!=raw[rel]:
            raise RuntimeError('STAGING_SOURCE_CHANGED_'+rel)
        atomic(dest,blob)
        written.append(rel)
    for command in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        result=run(command,115)
        if result.returncode:
            raise RuntimeError('ARTISAN_VIEW_OR_ROUTE_CACHE '+(result.stdout+result.stderr)[-650:])
    route_verification()
    read_query_smoke()
except BaseException as ex:
    for rel in written:
        if rel in raw:atomic(root/rel,raw[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],85)
    raise SystemExit('STOP_AM253_APPLY_RESTORED_SOURCE_'+str(ex))

(backup/'manifest.json').write_text(json.dumps({
    'installed':{rel:sha(blob) for rel,blob in patched.items()}
},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM253_PURCHASE_RETURN_SALES_AND_VENDOR_LEDGER_CONNECTED')
print('AM253_PURCHASE_RETURN_ADMIN_ROUTES_INSTALLED')
print('AM253_ORIGINAL_PURCHASE_BILLS_AND_PAYMENTS_UNCHANGED')
print('AM253_CODE_BACKUP='+str(backup))
