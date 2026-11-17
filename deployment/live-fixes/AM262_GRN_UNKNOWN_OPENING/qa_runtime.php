<?php
/**
 * AM262 authenticated STAGING QA in one outer DB rollback.
 * Reproduces Brake Shoe without opening balance and checks real stock-in,
 * audit exceptions, GRN verification, Item Ledger and duplicate protection.
 * Synthetic challans and any temporary stock changes are rolled back.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$D=\Illuminate\Support\Facades\DB::class;
$assert=static function(bool $yes,string $label):void{
    if(!$yes)throw new RuntimeException($label);
};
$user=\App\Models\ErpAuthUser::query()->where('active',1)
    ->whereIn('role',['owner','admin'])->firstOrFail();
$controller=app(\App\Http\Controllers\Web\AdminProcurementGrnController::class);
$ledger=app(\App\Http\Controllers\Web\AdminMasterItemLedgerController::class);
$job=$D::table('job_cards')->where('job_no','JC-20261116-00060')->first();
$assert((bool)$job,'AM262_QA_JOB60_MISSING');
$part=$D::table('job_card_parts as j')
    ->join('parts_master as p','p.id','=','j.part_master_id')
    ->where('j.job_card_id',$job->id)->where('j.part_name','like','%Brake Shoe%')
    ->whereNull('p.stock_qty')->first(['p.id','p.part_name','p.part_code','p.stock_qty','p.active']);
if(!$part){
    $part=$D::table('parts_master')->where('part_name','like','%Brake Shoe%')
        ->whereNull('stock_qty')->first(['id','part_name','part_code','stock_qty','active']);
}
$assert((bool)$part,'AM262_QA_BRAKE_SHOE_UNRECORDED_FIXTURE_MISSING');
$assert(in_array(strtolower(trim((string)$part->active)),['yes','1','active','true'],true),
   'AM262_QA_SELECTED_PART_INACTIVE');
$vendor=$D::table('workshop_vendors')->where('active',1)
   ->where('vendor_name','like','%Jupiter%')->first();
$assert((bool)$vendor,'AM262_QA_JUPITER_ACTIVE_VENDOR_MISSING');
$partId=(int)$part->id;
$before=[
  'grn'=>$D::table('am260_grns')->count(),
  'grn_lines'=>$D::table('am260_grn_lines')->count(),
  'exceptions'=>$D::table('am262_grn_opening_exceptions')->count(),
  'movements'=>$D::table('inventory_movements')->count(),
  'purchases'=>$D::table('workshop_purchases')->count(),
  'bills'=>$D::table('am260_grn_bills')->count(),
];
$assert($D::table('parts_master')->where('id',$partId)->value('stock_qty')===null,
    'AM262_EXPECTED_NULL_OPENING');
$request=static function(string $path,string $method,array $data)use($user){
    $r=\Illuminate\Http\Request::create($path,$method,$data);
    $r->setUserResolver(static fn()=>$user);
    return $r;
};
$view=$controller->newReceipt($request('/erp/procurement/receive','GET',[]));
$assert($view instanceof \Illuminate\View\View,'AM262_RECEIPT_PAGE_NOT_VIEW');
$html=$view->render();
$assert(str_contains($html,'Opening Not Recorded — Allow Provisional Receipt') &&
    str_contains($html,'opening_acknowledged'),'AM262_OPERATOR_ACKNOWLEDGEMENT_UI_MISSING');
echo "AM262_RECEIPT_FORM_ALLOWS_OPENING_UNRECORDED_PASS\n";
$D::beginTransaction();
try{
    $challan='AM262-QA-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(9));
    $today=now('Asia/Kolkata')->format('Y-m-d');
    $payload=[
      'vendor_id'=>(int)$vendor->id,
      'job_card_id'=>(int)$job->id,
      'challan_no'=>$challan,
      'challan_date'=>$today,'receipt_date'=>$today,
      'notes'=>'AM262 rollback QA only - not real challan',
      'lines'=>[['part_master_id'=>$partId,'qty'=>1,'unit_cost'=>90]],
    ];
    try{
        $controller->postReceipt($request('/erp/procurement/receive','POST',$payload));
        throw new RuntimeException('AM262_MISSING_OPENING_ACK_WAS_ACCEPTED');
    }catch(\Illuminate\Validation\ValidationException $e){
        $assert(isset($e->errors()['opening_acknowledged']),
                'AM262_MISSING_ACK_REJECTED_FOR_WRONG_REASON');
    }
    $assert($D::table('am260_grns')->count()===$before['grn'],
       'AM262_FAILED_NO_ACK_CREATED_GRN');
    $assert($D::table('parts_master')->where('id',$partId)->value('stock_qty')===null,
       'AM262_FAILED_NO_ACK_CHANGED_STOCK');
    echo "AM262_UNVERIFIED_OPENING_REQUIRES_EXPLICIT_ACK_PASS\n";

    $payload['opening_acknowledged']='1';
    $result=$controller->postReceipt($request('/erp/procurement/receive','POST',$payload));
    $assert($result instanceof \Illuminate\Http\RedirectResponse,'AM262_POSTED_RECEIPT_NO_REDIRECT');
    $grn=$D::table('am260_grns')->where('challan_no',$challan)->first();
    $assert((bool)$grn,'AM262_GRN_WAS_NOT_SAVED');
    $assert(str_contains($result->getTargetUrl(),'/procurement/grn/'.$grn->id),
       'AM262_GRN_CONFIRMATION_REDIRECT_MISSING');
    $movement=$D::table('inventory_movements')->where('reference_no',$grn->grn_no)
       ->where('part_master_id',$partId)->first();
    $assert($movement && (string)$movement->movement_type==='GRN_RECEIPT_IN' &&
        abs((float)$movement->qty_in-1.0)<0.0001 &&
        abs((float)$movement->balance_after-1.0)<0.0001,
        'AM262_GRN_MOVEMENT_MISSING_OR_DOUBLE_RECEIVED');
    $assert(abs((float)$D::table('parts_master')->where('id',$partId)->value('stock_qty')-1)<.0001,
        'AM262_PROVISIONAL_STOCK_NOT_AVAILABLE');
    $assert(str_contains((string)$movement->note,'AM262_OPENING_UNRECORDED') &&
      str_contains((string)$movement->note,'RECONCILIATION_PENDING'),
      'AM262_MOVEMENT_AUDIT_WARNING_MISSING');
    $exception=$D::table('am262_grn_opening_exceptions')->where('grn_id',$grn->id)
      ->where('part_master_id',$partId)->first();
    $assert($exception &&
      $exception->prior_stock_qty===null &&
      abs((float)$exception->provisional_base_qty)<.0001 &&
      abs((float)$exception->received_qty-1)<.0001 &&
      $exception->status==='PENDING_RECONCILIATION',
      'AM262_OPENING_UNKNOWN_STATUS_NOT_AUDITED');
    echo "AM262_BRAKE_SHOE_UNRECORDED_OPENING_GRN_STOCK_IN_WITH_AUDIT_PASS\n";

    $verify=$controller->showReceipt(
       $request('/erp/procurement/grn/'.$grn->id,'GET',[]),(int)$grn->id);
    $data=$verify->getData();
    $assert($data['allVerified'] && $data['hasUnverifiedOpenings'],
        'AM262_GRN_PROOF_DOES_NOT_SHOW_DIFFERENT_STOCK_AND_OPENING_STATUSES');
    $verifyHtml=$verify->render();
    $assert(str_contains($verifyHtml,'Opening Stock Unverified') &&
      str_contains($verifyHtml,'Reconciliation Pending') &&
      str_contains($verifyHtml,'View Item Ledger'),
      'AM262_GRN_PROOF_MISSING_OPENING_EXCEPTION_WARNING');
    echo "AM262_GRN_PROOF_SHOWS_MOVEMENT_VERIFIED_OPENING_UNVERIFIED_PASS\n";

    $itemLedger=$ledger->index(
       $request('/erp/masters/item-ledger','GET',['type'=>'PART','id'=>$partId]));
    $ledgerData=$itemLedger->getData();
    $events=$ledgerData['report']['events']??[];
    $match=array_values(array_filter($events,static fn($entry)=>
         ($entry['reference']??'')===$grn->grn_no &&
         ($entry['type']??'')==='STOCK · GRN_RECEIPT_IN'));
    $assert(count($match)===1 && abs((float)$match[0]['in']-1)<.0001,
        'AM262_ITEM_LEDGER_GRN_MISSING_OR_DUPLICATED');
    echo "AM262_ITEM_TRANSACTION_LEDGER_SHOWS_ONE_GRN_RECEIPT_PASS\n";
    try{
      $controller->postReceipt($request('/erp/procurement/receive','POST',$payload));
      throw new RuntimeException('AM262_DUPLICATE_CHALLAN_ACCEPTED');
    }catch(\Illuminate\Validation\ValidationException $e){
      $assert(isset($e->errors()['challan_no']),'AM262_DUPLICATE_WRONG_VALIDATION');
    }
    $assert($D::table('am262_grn_opening_exceptions')->count()===$before['exceptions']+1 &&
      $D::table('inventory_movements')->count()===$before['movements']+1 &&
      abs((float)$D::table('parts_master')->where('id',$partId)->value('stock_qty')-1)<.0001,
      'AM262_DUPLICATE_GRN_CHANGED_STOCK_OR_EXCEPTION');
    $assert($D::table('workshop_purchases')->count()===$before['purchases'],
      'AM262_GRN_WRONGLY_BOOKED_VENDOR_PURCHASE_BILL');
    echo "AM262_DUPLICATE_CHALLAN_BLOCKED_VENDOR_BILL_STILL_PENDING_PASS\n";
}finally{
    $D::rollBack();
}
$after=[
  'grn'=>$D::table('am260_grns')->count(),
  'grn_lines'=>$D::table('am260_grn_lines')->count(),
  'exceptions'=>$D::table('am262_grn_opening_exceptions')->count(),
  'movements'=>$D::table('inventory_movements')->count(),
  'purchases'=>$D::table('workshop_purchases')->count(),
  'bills'=>$D::table('am260_grn_bills')->count(),
];
$assert($before===$after,'AM262_QA_LEFT_TRANSACTIONS_IN_DB');
$assert($D::table('parts_master')->where('id',$partId)->value('stock_qty')===null,
    'AM262_QA_DID_NOT_RESTORE_ACTUAL_NULL_OPENING');
echo "AM262_TEST_GRN_STOCK_MOVEMENTS_AND_EXCEPTIONS_FULLY_ROLLED_BACK_PASS\n";
echo "AM262_AUTHENTICATED_RUNTIME_QA_ALL_PASS\n";
