<?php
/**
 * AM265 authenticated STAGING tests. Every simulated Vendor/JC/Challan/date
 * edit occurs inside one DB transaction and is rolled back before exit.
 * No actual inventory, GRN, vendor, JC, or Purchase data is edited by QA.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());

$DB=\Illuminate\Support\Facades\DB::class;
$S=\Illuminate\Support\Facades\Schema::class;
$assert=static function(bool $okay,string $message):void{
    if(!$okay)throw new RuntimeException($message);
};
$user=\App\Models\ErpAuthUser::where('active',1)
   ->whereIn('role',['owner','admin'])->firstOrFail();
$controller=app(\App\Http\Controllers\Web\AdminProcurementGrnController::class);
$req=static function(string $path,string $method,array $input=[])use($user){
    $r=\Illuminate\Http\Request::create($path,$method,$input);
    $r->setUserResolver(static fn()=>$user);
    return $r;
};
$grn=$DB::table('am260_grns')->where('grn_no','GRN-261117145056-NCWOTCC')->first();
$assert((bool)$grn,'AM265_REFERENCE_GRN_NOT_FOUND');
$assert($grn->status==='RECEIVED','AM265_REFERENCE_GRN_NOT_RECEIVED');
$lines=$DB::table('am260_grn_lines')->where('grn_id',$grn->id)->get();
$assert($lines->count()>0,'AM265_GRN_LINES_EMPTY');
$originalHeader=(array)$grn;
$qtyBefore=$lines->pluck('qty_received','id')->all();
$partIds=$lines->pluck('part_master_id')->unique()->values()->all();
$stockBefore=$DB::table('parts_master')->whereIn('id',$partIds)
   ->get(['id','stock_qty'])->keyBy('id')->map(static fn($p)=>$p->stock_qty)->all();
$before=[
  'grn'=>$DB::table('am260_grns')->count(),
  'grn_lines'=>$DB::table('am260_grn_lines')->count(),
  'grn_edit'=>$DB::table('am264_grn_line_edits')->count(),
  'header_edit'=>$DB::table('am265_grn_header_audits')->count(),
  'movements'=>$DB::table('inventory_movements')->count(),
  'purchase'=>$DB::table('workshop_purchases')->count(),
  'purchase_bill'=>$DB::table('am260_grn_bills')->count()
];
$path='/erp/procurement/grn/'.$grn->id.'/edit';
$view=$controller->editReceipt($req($path,'GET'),(int)$grn->id);
$assert($view instanceof \Illuminate\View\View,'AM265_HEADER_EDIT_NOT_VIEW');
$data=$view->getData();
$assert(isset($data['headerToken'],$data['vendors'],$data['jobs']),
  'AM265_HEADER_FORM_LOOKUPS_OR_VERSION_MISSING');
$html=$view->render();
foreach(['Edit Complete GRN','vendor_id','job_card_id','challan_no',
  'challan_date','receipt_date','notes','header_token','Save Complete GRN'] as $token)
   $assert(str_contains($html,$token),'AM265_RENDER_MISSING_'.$token);
echo "AM265_VENDOR_JOB_CHALLAN_DATES_NOTES_FORM_RENDER_PASS\n";

$guest=\Illuminate\Http\Request::create($path,'GET');
$guest->setUserResolver(static fn()=>null);
try{
  $controller->editReceipt($guest,(int)$grn->id);
  throw new RuntimeException('AM265_GUEST_ALLOWED');
}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
  $assert($e->getStatusCode()===403,'AM265_WRONG_GUEST_STATUS');
}
echo "AM265_GRN_EDIT_OWNER_ADMIN_ONLY_PASS\n";

$payload=[
 'vendor_id'=>(int)$grn->vendor_id,
 'job_card_id'=>$grn->job_card_id,
 'challan_no'=>(string)$grn->challan_no,
 'challan_date'=>substr((string)$grn->challan_date,0,10),
 'receipt_date'=>substr((string)$grn->receipt_date,0,10),
 'notes'=>(string)($grn->notes??''),
 'header_token'=>$data['headerToken'],
 'physical_verified'=>'1',
 'reason'=>'AM265 QA: metadata correction only; no stock movements, all rolled back.',
 'lines'=>[],
 'new_lines'=>[]
];
foreach($lines as $l)$payload['lines'][$l->id]=[
    'qty'=>$l->qty_received,
    'unit_cost'=>$l->provisional_unit_cost,
    'original_qty'=>$l->qty_received,
    'original_cost'=>$l->provisional_unit_cost
];

$DB::beginTransaction();
try{
  // Change only Received Date and Remarks.
  $payload['receipt_date']='2026-11-16';
  if($payload['receipt_date']===$originalHeader['receipt_date'])
      $payload['receipt_date']='2026-11-15';
  $payload['notes']='AM265 temporary full header edit with exact original GRN preserved';
  $res=$controller->updateReceipt($req($path,'POST',$payload),(int)$grn->id);
  $assert($res instanceof \Illuminate\Http\RedirectResponse,'AM265_SAVE_NOT_REDIRECT');
  $updated=$DB::table('am260_grns')->where('id',$grn->id)->first();
  $assert(substr((string)$updated->receipt_date,0,10)===$payload['receipt_date'],
     'AM265_RECEIPT_DATE_NOT_SAVED');
  $assert($updated->notes===$payload['notes'],'AM265_REMARKS_NOT_SAVED');
  $assert($updated->vendor_id===$originalHeader['vendor_id'] &&
      $updated->job_card_id===$originalHeader['job_card_id'] &&
      $updated->challan_no===$originalHeader['challan_no'],
      'AM265_UNCHANGED_HEADER_FIELDS_MODIFIED');
  $assert($DB::table('inventory_movements')->count()===$before['movements'],
      'AM265_HEADER_ONLY_CREATED_STOCK_MOVEMENT');
  $assert($DB::table('am264_grn_line_edits')->count()===$before['grn_edit'],
      'AM265_HEADER_ONLY_CREATED_ITEM_CORRECTION');
  $assert($DB::table('am265_grn_header_audits')->count()===$before['header_edit']+1,
      'AM265_HEADER_AUDIT_MISSING');
  $audit=$DB::table('am265_grn_header_audits')->where('grn_id',$grn->id)
      ->orderByDesc('id')->first();
  $assert($audit && str_contains($audit->changed_fields,'receipt_date') &&
      str_contains($audit->changed_fields,'notes'),'AM265_AUDIT_FIELDS_MISSING');
  echo "AM265_HEADER_ONLY_DATE_REMARKS_EDIT_AUDIT_ZERO_STOCK_CHANGE_PASS\n";

  // Deliberately stale original header token must fail.
  try{
    $controller->updateReceipt($req($path,'POST',$payload),(int)$grn->id);
    throw new RuntimeException('AM265_STALE_TAB_WAS_ACCEPTED');
  }catch(\Illuminate\Validation\ValidationException $e){
    $assert(isset($e->errors()['header']),'AM265_STALE_FORM_WRONG_BLOCK');
  }
  echo "AM265_STALE_SECOND_TAB_PROTECTION_PASS\n";

  $fresh=$controller->editReceipt($req($path,'GET'),(int)$grn->id)->getData();
  $payload['header_token']=$fresh['headerToken'];
  $otherVendor=$DB::table('workshop_vendors')->where('active',1)
     ->where('id','<>',$grn->vendor_id)->first();
  if($otherVendor && !$fresh['hasBilled'] && !$grn->po_id){
    $payload['vendor_id']=$otherVendor->id;
    $payload['reason']='AM265 QA: correcting wrong Vendor Master on unbilled urgent GRN.';
    $controller->updateReceipt($req($path,'POST',$payload),(int)$grn->id);
    $afterVendor=$DB::table('am260_grns')->where('id',$grn->id)->first();
    $assert((int)$afterVendor->vendor_id===(int)$otherVendor->id,
       'AM265_UNBILLED_VENDOR_EDIT_FAILED');
    $assert($DB::table('inventory_movements')->count()===$before['movements'],
       'AM265_VENDOR_ONLY_EDIT_MODIFIED_STOCK');
    echo "AM265_UNBILLED_URGENT_GRN_VENDOR_CORRECTION_PASS\n";
    $fresh=$controller->editReceipt($req($path,'GET'),(int)$grn->id)->getData();
    $payload['header_token']=$fresh['headerToken'];

    // Mark one line billed only inside this roll-back transaction.
    $first=$lines->first();
    $DB::table('am260_grn_lines')->where('id',$first->id)->update(['qty_billed'=>1]);
    $payload['vendor_id']=(int)$grn->vendor_id;
    try{
      $controller->updateReceipt($req($path,'POST',$payload),(int)$grn->id);
      throw new RuntimeException('AM265_BILLED_VENDOR_RELINK_ALLOWED');
    }catch(\Illuminate\Validation\ValidationException $e){
      $assert(isset($e->errors()['header']),'AM265_BILLED_VENDOR_GUARD_MISSING');
    }
    echo "AM265_BILLED_GRN_VENDOR_JOB_CHALLAN_RELINK_PROTECTED_PASS\n";
    $DB::table('am260_grn_lines')->where('id',$first->id)
       ->update(['qty_billed'=>$first->qty_billed]);
  }

  $detail=$controller->showReceipt(
      $req('/erp/procurement/grn/'.$grn->id,'GET'),(int)$grn->id);
  $assert($detail instanceof \Illuminate\View\View,'AM265_DETAIL_NOT_VIEW');
  $detailHtml=$detail->render();
  $assert(str_contains($detailHtml,'Complete GRN Header Correction History') &&
      str_contains($detailHtml,'Edit Complete GRN'),
      'AM265_BEFORE_AFTER_CORRECTION_HISTORY_NOT_DISPLAYED');
  echo "AM265_HEADER_HISTORY_VISIBLE_IN_GRN_DETAIL_PASS\n";

}finally{
  $DB::rollBack();
}

$after=[
  'grn'=>$DB::table('am260_grns')->count(),
  'grn_lines'=>$DB::table('am260_grn_lines')->count(),
  'grn_edit'=>$DB::table('am264_grn_line_edits')->count(),
  'header_edit'=>$DB::table('am265_grn_header_audits')->count(),
  'movements'=>$DB::table('inventory_movements')->count(),
  'purchase'=>$DB::table('workshop_purchases')->count(),
  'purchase_bill'=>$DB::table('am260_grn_bills')->count()
];
$assert($after===$before,'AM265_TEST_TRANSACTIONS_NOT_ROLLED_BACK');
$afterHeader=(array)$DB::table('am260_grns')->where('id',$grn->id)->first();
$assert($afterHeader==$originalHeader,'AM265_REAL_GRN_HEADER_CHANGED_BY_TEST');
$stockAfter=$DB::table('parts_master')->whereIn('id',$partIds)
   ->get(['id','stock_qty'])->keyBy('id')->map(static fn($p)=>$p->stock_qty)->all();
$assert($stockAfter==$stockBefore,'AM265_REAL_STOCK_CHANGED_BY_TEST');
$qtyAfter=$DB::table('am260_grn_lines')->where('grn_id',$grn->id)
  ->get()->pluck('qty_received','id')->all();
$assert($qtyAfter==$qtyBefore,'AM265_REAL_GRN_QTY_CHANGED_BY_TEST');
echo "AM265_REAL_STOCK_GRN_PURCHASE_AND_TEST_AUDIT_FULLY_UNCHANGED_PASS\n";
echo "AM265_FULL_GRN_HEADER_EDIT_STAGING_QA_PASS\n";
