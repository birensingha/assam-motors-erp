<?php
/**
 * AM260 STAGING authenticated read/write ROLLBACK QA:
 * Tests real controller stock-in and bill posting inside an outer DB transaction.
 * All temporary PO/GRN/Purchase test rows and test stock movements roll back.
 * Never posts actual JC-60 stock or vendor invoices permanently.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$D=\Illuminate\Support\Facades\DB::class;
$user=\App\Models\ErpAuthUser::where('active',1)->whereIn('role',['owner','admin'])->firstOrFail();
$controller=app(\App\Http\Controllers\Web\AdminProcurementGrnController::class);
$today=now('Asia/Kolkata')->format('Y-m-d');
$vendor=$D::table('workshop_vendors')->where('active',1)->where('vendor_name','like','%Jupiter%')->first();
if(!$vendor){
    echo "AM260_JUPITER_NOT_PRESENT_AS_ACTIVE_VENDOR\n";
    $vendor=$D::table('workshop_vendors')->where('active',1)->orderBy('id')->first();
}
if(!$vendor)throw new RuntimeException('AM260_NO_ACTIVE_VENDOR_FOR_TEST');
$job=$D::table('job_cards')->where('job_no','JC-20261116-00060')->first();
$jcPart=$job?$D::table('job_card_parts')->where('job_card_id',$job->id)->where('part_name','like','%Spark Plug%')->first():null;
$part=$jcPart?$D::table('parts_master')->where('id',$jcPart->part_master_id)->first():null;
echo 'AM260_QA_SPARK_MASTER_ID='.($part->id??'MISSING').' ACTIVE='.($part->active??'MISSING')."\n";
if(!$part || !$job)throw new RuntimeException('AM260_REQUIRED_JC60_SPARK_PLUG_FIXTURE_MISSING');
if($part->stock_qty===null)throw new RuntimeException('AM260_SPARK_PLUG_STOCK_UNRECORDED');
$vendorId=(int)$vendor->id;
$partId=(int)$part->id;
$jobId=(int)$job->id;
$stockBefore=round((float)$part->stock_qty,3);
$before=[
   'po'=>$D::table('am260_purchase_orders')->count(),
   'po_lines'=>$D::table('am260_po_lines')->count(),
   'grn'=>$D::table('am260_grns')->count(),
   'grn_lines'=>$D::table('am260_grn_lines')->count(),
   'bill'=>$D::table('am260_grn_bills')->count(),
   'alloc'=>$D::table('am260_grn_bill_allocations')->count(),
   'purchase'=>$D::table('workshop_purchases')->count(),
   'purchase_batches'=>$D::table('workshop_purchase_batches')->count(),
   'movements'=>$D::table('inventory_movements')->count(),
   'audits'=>$D::table('purchase_audit_logs')->count(),
];
$req=function(string $path,string $method,array $params)use($user){
    $r=\Illuminate\Http\Request::create($path,$method,$params);
    $r->setUserResolver(static fn()=>$user);
    return $r;
};
$assert=function(bool $condition,string $message){
    if(!$condition)throw new RuntimeException($message);
};
foreach(['index','newPo','newReceipt','newBill'] as $fn){
    $view=$controller->$fn($req('/erp/procurement','GET',[]));
    $assert($view instanceof \Illuminate\View\View,'AM260_VIEW_NOT_RETURNED_'.$fn);
    $html=$view->render();
    $assert(str_contains($html,'ASSAM MOTORS'),'AM260_VIEW_RENDER_MISSING_COMPANY_'.$fn);
}
echo "AM260_ALL_FOUR_GRN_PAGES_RENDER_PASS\n";
$guest=\Illuminate\Http\Request::create('/erp/procurement','GET');
$guest->setUserResolver(static fn()=>null);
try{
    $controller->index($guest);
    throw new RuntimeException('AM260_GUEST_WAS_ALLOWED');
}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
    $assert($e->getStatusCode()===403,'AM260_GUEST_WRONG_STATUS');
}
echo "AM260_FINANCIAL_GRN_OWNER_ADMIN_ACCESS_PASS\n";
$D::beginTransaction();
try{
    $poRequest=[
        'vendor_id'=>$vendorId,'job_card_id'=>$jobId,'order_date'=>$today,
        'notes'=>'AM260 ROLLBACK QA / NEVER REAL ORDER',
        'lines'=>[
            ['part_master_id'=>$partId,'qty'=>3,'unit_cost'=>80]
        ],
    ];
    $res=$controller->savePo($req('/erp/procurement/po','POST',$poRequest));
    $assert($res instanceof \Illuminate\Http\RedirectResponse,'AM260_PO_NO_REDIRECT');
    $po=$D::table('am260_purchase_orders')->orderByDesc('id')->first();
    $poLine=$D::table('am260_po_lines')->where('po_id',$po->id)->first();
    $assert((int)$po->vendor_id===$vendorId && (int)$po->job_card_id===$jobId,
        'AM260_PO_VENDOR_JOB_RELATION_INVALID');
    $assert(abs((float)$D::table('parts_master')->where('id',$partId)->value('stock_qty')-$stockBefore)<.0001,
        'AM260_PO_WRONGLY_CHANGED_STOCK');
    $assert($D::table('workshop_purchases')->count()===$before['purchase'],
        'AM260_PO_WRONGLY_BOOKED_PURCHASE');
    echo "AM260_PO_STOCK_AND_PAYABLE_UNCHANGED_PASS\n";

    $challan='QA-AM260-CHALLAN-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));
    $receiptRequest=[
        'po_id'=>$po->id,'vendor_id'=>$vendorId,
        'challan_no'=>$challan,'challan_date'=>$today,'receipt_date'=>$today,
        'notes'=>'QA rollback only',
        'lines'=>[
            ['po_line_id'=>$poLine->id,'part_master_id'=>$partId,'qty'=>3,'unit_cost'=>80]
        ],
    ];
    $res=$controller->postReceipt($req('/erp/procurement/receive','POST',$receiptRequest));
    $assert($res instanceof \Illuminate\Http\RedirectResponse,'AM260_RECEIPT_NO_REDIRECT');
    $grn=$D::table('am260_grns')->where('challan_no',$challan)->first();
    $grnLine=$D::table('am260_grn_lines')->where('grn_id',$grn->id)->first();
    $afterGrn=(float)$D::table('parts_master')->where('id',$partId)->value('stock_qty');
    $assert(abs($afterGrn-($stockBefore+3))<.0001,'AM260_GRN_STOCK_NOT_INCREMENTED_EXACTLY_ONCE');
    $assert((float)$grnLine->qty_billed===0.0,'AM260_GRN_WRONGLY_AUTO_BILLED');
    $assert($D::table('workshop_purchases')->count()===$before['purchase'],
        'AM260_GRN_WRONGLY_POSTED_SUPPLIER_BILL');
    $movement=$D::table('inventory_movements')->where('reference_no',$grn->grn_no)->first();
    $assert($movement && abs((float)$movement->qty_in-3)<.0001 &&
       abs((float)$movement->qty_out)<.0001,'AM260_PHYSICAL_STOCK_MOVEMENT_MISSING');
    echo "AM260_GRN_PHYSICAL_STOCK_IN_ONCE_AND_BILL_PENDING_PASS\n";

    try{
        $controller->postReceipt($req('/erp/procurement/receive','POST',$receiptRequest));
        throw new RuntimeException('AM260_DUPLICATE_CHALLAN_WAS_ACCEPTED');
    }catch(\Illuminate\Validation\ValidationException $e){/* expected */}
    $assert(abs((float)$D::table('parts_master')->where('id',$partId)->value('stock_qty')-$afterGrn)<.0001,
        'AM260_DUPLICATE_CHALLAN_CHANGED_STOCK');
    echo "AM260_DUPLICATE_CHALLAN_BLOCKED_NO_DOUBLE_STOCK_PASS\n";

    $billNo='QA-AM260-INVOICE-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));
    $billRequest=[
        'vendor_id'=>$vendorId,'supplier_invoice'=>$billNo,'purchase_date'=>$today,
        'notes'=>'Rollback testing only',
        'lines'=>[$grnLine->id=>[
            'selected'=>'1','qty'=>3,'unit_cost'=>80,'discount_percent'=>5,'gst_percent'=>18
        ]]
    ];
    $res=$controller->postBill($req('/erp/procurement/bills','POST',$billRequest));
    $assert($res instanceof \Illuminate\Http\RedirectResponse,'AM260_BILL_NO_REDIRECT');
    $bill=$D::table('am260_grn_bills')->where('supplier_invoice',$billNo)->first();
    $batch=$D::table('workshop_purchase_batches')->where('batch_no',$bill->batch_no)->first();
    $alloc=$D::table('am260_grn_bill_allocations')->where('bill_id',$bill->id)->first();
    $purchase=$D::table('workshop_purchases')->where('id',$alloc->purchase_id)->first();
    $assert($batch && $purchase && $alloc,'AM260_PURCHASE_REGISTER_BILL_NOT_POSTED');
    $assert((int)$purchase->job_card_id===$jobId &&
        (int)$purchase->part_master_id===$partId &&
        (int)$purchase->vendor_id===$vendorId,
        'AM260_PB_MISLINKED_VENDOR_JOB_PART');
    $assert((float)$D::table('am260_grn_lines')->where('id',$grnLine->id)->value('qty_billed')===3.0,
        'AM260_GRN_PENDING_QTY_NOT_SETTLED');
    $assert(abs((float)$D::table('parts_master')->where('id',$partId)->value('stock_qty')-$afterGrn)<.0001,
        'AM260_GRN_BILL_DOUBLE_ADDED_STOCK');
    $assert($D::table('inventory_movements')->count()===$before['movements']+1,
        'AM260_BILL_CREATED_SECOND_STOCK_MOVEMENT');
    $assert(abs((float)$purchase->taxable_amount-228)<.01 &&
        abs((float)$purchase->gst_amount-41.04)<.01,
        'AM260_GRN_BILL_GST_DISCOUNT_MATH_BAD');
    echo "AM260_MATCH_BILL_PARTIAL_OR_FULL_IN_PURCHASE_REGISTER_NO_DUPLICATE_STOCK_PASS\n";

    try{
        $controller->postBill($req('/erp/procurement/bills','POST',$billRequest));
        throw new RuntimeException('AM260_SUPPLIER_INVOICE_DUPLICATE_BOOKED');
    }catch(\Illuminate\Validation\ValidationException $e){/* expected */}
    echo "AM260_DUPLICATE_SUPPLIER_INVOICE_BLOCKED_PASS\n";

    $purchaseService=app(\App\Services\PurchaseService::class);
    try{
       $purchaseService->delete((int)$purchase->id,'test delete from QA',(string)$user->name);
       throw new RuntimeException('AM260_GRN_LINKED_PURCHASE_STOCK_DELETE_ALLOWED');
    }catch(\Illuminate\Validation\ValidationException $e){/* expected */}
    echo "AM260_LEGACY_PURCHASE_DELETE_PROTECTED_PASS\n";

    try{
       $purchaseService->updatePartBatch((int)$purchase->id,
           ['vendor_id'=>$vendorId,'purchase_date'=>$today,'supplier_invoice'=>$billNo,
             'lines'=>[['purchase_id'=>$purchase->id,'part_master_id'=>$partId,'qty'=>3,
                'unit_cost'=>80,'discount_percent'=>5,'gst_percent'=>18,'allocation_type'=>'JOB','job_card_id'=>$jobId]]],
           (string)$user->name);
       throw new RuntimeException('AM260_GRN_LINKED_PURCHASE_EDIT_ALLOWED');
    }catch(\Illuminate\Validation\ValidationException $e){/* expected */}
    echo "AM260_LEGACY_PURCHASE_EDIT_PROTECTED_PASS\n";

    $normalReturn=app(\App\Http\Controllers\Web\AdminPurchaseReturnController::class);
    try{
       $normalReturn->store($req('/erp/purchases/'.$purchase->id.'/return','POST',[]),(int)$purchase->id);
       throw new RuntimeException('AM260_LEGACY_PURCHASE_RETURN_ALLOWED');
    }catch(\Illuminate\Validation\ValidationException $e){/* expected */}
    echo "AM260_LEGACY_PURCHASE_RETURN_PROTECTED_PASS\n";
} finally {
    $D::rollBack();
}
$after=[
   'po'=>$D::table('am260_purchase_orders')->count(),
   'po_lines'=>$D::table('am260_po_lines')->count(),
   'grn'=>$D::table('am260_grns')->count(),
   'grn_lines'=>$D::table('am260_grn_lines')->count(),
   'bill'=>$D::table('am260_grn_bills')->count(),
   'alloc'=>$D::table('am260_grn_bill_allocations')->count(),
   'purchase'=>$D::table('workshop_purchases')->count(),
   'purchase_batches'=>$D::table('workshop_purchase_batches')->count(),
   'movements'=>$D::table('inventory_movements')->count(),
   'audits'=>$D::table('purchase_audit_logs')->count(),
];
$assert($after===$before,'AM260_ROLLBACK_QA_LEFT_TEST_DOCUMENTS_IN_DB');
$stockAfter=(float)$D::table('parts_master')->where('id',$partId)->value('stock_qty');
$assert(abs($stockAfter-$stockBefore)<.0001,
    'AM260_ROLLBACK_QA_CHANGED_REAL_SPARK_PLUG_STOCK');
echo "AM260_ALL_TEST_PURCHASES_GRNS_MOVEMENTS_AND_PART_STOCK_ROLLED_BACK_PASS\n";
echo "AM260_PRODUCTION_AND_JC60_FINANCIAL_ROWS_UNCHANGED\n";
echo "AM260_FULL_STAGING_AUTHENTICATED_QA_PASS\n";
