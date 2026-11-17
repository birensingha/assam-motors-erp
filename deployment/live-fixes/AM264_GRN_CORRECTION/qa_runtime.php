<?php
/**
 * AM264 Authenticated STAGING transactional QA. Simulates actual JC-60 GRN
 * corrections and Issue All ONLY inside an outer DB rollback. No test stock,
 * challan, invoice, JC, or edits persist.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$D=\Illuminate\Support\Facades\DB::class;
$user=\App\Models\ErpAuthUser::where('active',1)->whereIn('role',['owner','admin'])->firstOrFail();
$grnCtl=app(\App\Http\Controllers\Web\AdminProcurementGrnController::class);
$issueCtl=app(\App\Http\Controllers\Web\AdminJobCardPartsBatchController::class);
$assert=static function(bool $pass,string $label):void{
    if(!$pass)throw new RuntimeException($label);
};
$r=static function(string $path,string $method,array $values)use($user){
    $req=\Illuminate\Http\Request::create($path,$method,$values);
    $req->setUserResolver(static fn()=>$user);
    return $req;
};
$no='GRN-261117145056-NCWOTCC';
$grn=$D::table('am260_grns')->where('grn_no',$no)->first();
$assert((bool)$grn,'AM264_REAL_GRN_FIXTURE_NOT_FOUND');
$job=$D::table('job_cards')->where('job_no','JC-20261116-00060')->first();
$assert((bool)$job && (int)$grn->job_card_id===(int)$job->id,'AM264_GRN_JOB_LINK_MISMATCH');
$oldLines=$D::table('am260_grn_lines')->where('grn_id',$grn->id)->orderBy('id')->get();
$oldStock=$D::table('parts_master')->whereIn('id',[234,250,805,236])
    ->get(['id','stock_qty'])->keyBy('id')->map(static fn($p)=>$p->stock_qty)->all();
$before=[
    'grn'=>$D::table('am260_grns')->count(),
    'grn_lines'=>$D::table('am260_grn_lines')->count(),
    'movements'=>$D::table('inventory_movements')->count(),
    'corrections'=>$D::table('am264_grn_line_edits')->count(),
    'exceptions'=>$D::table('am262_grn_opening_exceptions')->count(),
    'audits'=>$D::table('job_card_change_audits')->count(),
    'purchases'=>$D::table('workshop_purchases')->count(),
];
$edit=$grnCtl->editReceipt($r('/erp/procurement/grn/'.$grn->id.'/edit','GET',[]),(int)$grn->id);
$assert($edit instanceof \Illuminate\View\View,'AM264_GRN_EDIT_VIEW_MISSING');
$editHtml=$edit->render();
foreach(['Edit Received GRN Items','Save GRN Item Correction','physical_verified','new_lines[0][part_master_id]'] as $part){
    $assert(str_contains($editHtml,$part),'AM264_GRN_EDIT_VIEW_INCOMPLETE_'.$part);
}
$guest=\Illuminate\Http\Request::create('/erp/procurement/grn/'.$grn->id.'/edit','GET');
$guest->setUserResolver(static fn()=>null);
try{$grnCtl->editReceipt($guest,(int)$grn->id);throw new RuntimeException('AM264_GUEST_ALLOWED');}
catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
    if($e->getStatusCode()!==403)throw $e;
}
echo "AM264_ADMIN_EDIT_FORM_AND_GUEST_403_PASS\n";
$front=$oldLines->firstWhere('part_master_id',234);
$throttle=$oldLines->firstWhere('part_master_id',805);
$assert($front && $throttle,'AM264_REQUIRED_GRN_LINES_MISSING');
$payload=[
  'reason'=>'AM264 rollback-only QA for physical GRN item correction, not actual vendor goods',
  'physical_verified'=>'1','opening_acknowledged'=>'1','lines'=>[],
  'new_lines'=>[['part_master_id'=>250,'part_search_label'=>'AM-0250 | Brake Wheel Cylinder',
                 'qty'=>1,'unit_cost'=>250]],
];
foreach($oldLines as $line){
   $qty=(float)$line->qty_received;
   if((int)$line->part_master_id===234)$qty+=1;
   if((int)$line->part_master_id===805)$qty+=2;
   $payload['lines'][$line->id]=[
       'original_qty'=>$line->qty_received,
       'original_cost'=>$line->provisional_unit_cost,
       'qty'=>number_format($qty,3,'.',''),
       'unit_cost'=>$line->provisional_unit_cost
   ];
}
$D::beginTransaction();
try{
    // Demonstrate the exact user-visible Issue All guard BEFORE correcting GRN.
    try{
        $issueCtl->issueAll($r('/erp/job-cards/'.$job->id.'/parts/issue-all','POST',[
            'reason'=>'AM264 rollback QA: stock precheck'
        ]),(int)$job->id);
        throw new RuntimeException('AM264_ISSUE_ALL_DID_NOT_BLOCK_SHORTAGE');
    }catch(\Illuminate\Validation\ValidationException $e){
        $message=json_encode($e->errors());
        $assert(str_contains($message,'Front Brake Pad') &&
            str_contains($message,'Throttle Body Cleaner') &&
            str_contains($message,'Brake Wheel Cylinder'),
            'AM264_ISSUE_PRECHECK_WRONG_MISSING_PARTS');
    }
    echo "AM264_EXISTING_ISSUE_ALL_CORRECTLY_BLOCKS_3_SHORTAGES_PASS\n";

    // Simulated verified correction, NOT a real operator receipt.
    $response=$grnCtl->updateReceipt($r('/erp/procurement/grn/'.$grn->id.'/edit','POST',$payload),
        (int)$grn->id);
    $assert($response instanceof \Illuminate\Http\RedirectResponse,
        'AM264_UPDATE_NO_REDIRECT');
    $assert((int)$D::table('am260_grn_lines')->where('grn_id',$grn->id)->count()===$oldLines->count()+1,
        'AM264_NEW_PART_NOT_ADDED');
    $newWheel=$D::table('am260_grn_lines')->where('grn_id',$grn->id)
        ->where('part_master_id',250)->first();
    $assert($newWheel && abs((float)$newWheel->qty_received-1)<.0001,
        'AM264_NEW_WHEEL_CYLINDER_NOT_RECEIVED');
    foreach([234=>1.0,805=>1.0,250=>1.0] as $id=>$wanted){
        $actual=$D::table('parts_master')->where('id',$id)->value('stock_qty');
        $assert(abs((float)$actual-$wanted)<.0001,
            'AM264_STOCK_AFTER_CORRECTION_BAD_PART_'.$id);
    }
    $assert($D::table('am264_grn_line_edits')->where('grn_id',$grn->id)->count()===3,
        'AM264_IMMUTABLE_AUDIT_MISSING');
    $assert($D::table('am262_grn_opening_exceptions')->where('grn_id',$grn->id)
        ->where('part_master_id',250)->where('status','PENDING_RECONCILIATION')->exists(),
        'AM264_UNKNOWN_OPENING_EXCEPTION_MISSING');
    $assert($D::table('inventory_movements')->where('reference_no',$grn->grn_no)
        ->where('movement_type','GRN_EDIT_IN')->count()===2,
        'AM264_CORRECTION_MOVEMENT_COUNT_BAD');
    $assert($D::table('inventory_movements')->where('reference_no',$grn->grn_no)
        ->where('part_master_id',250)->where('movement_type','GRN_RECEIPT_IN')->count()===1,
        'AM264_NEW_PART_RECEIPT_MOVEMENT_MISSING');
    echo "AM264_GRN_ITEM_EDIT_AND_NEW_PART_QTY_STOCK_DELTAS_PASS\n";

    $detail=$grnCtl->showReceipt($r('/erp/procurement/grn/'.$grn->id,'GET',[]),(int)$grn->id);
    $assert($detail instanceof \Illuminate\View\View,'AM264_GRN_DETAIL_NOT_VIEW');
    $details=$detail->getData();
    $assert($details['allVerified'],'AM264_CORRECTED_MOVEMENT_PROOF_NOT_RECONCILED');
    $assert($details['shortages']->isEmpty(),
        'AM264_JC_STOCK_READINESS_STILL_REPORTS_SHORTAGE');
    $assert($details['edits']->count()===3,'AM264_DETAIL_AUDIT_HISTORY_NOT_SHOWN');
    $rendered=$detail->render();
    foreach(['Edit GRN Items','Issue All Parts Stock Readiness','Correction History'] as $token){
        $assert(str_contains($rendered,$token),
            'AM264_DETAIL_SCREEN_MISSING_'.str_replace(' ','_',$token));
    }
    echo "AM264_GRN_CORRECTION_LEDGER_PROOF_AND_JC_STOCK_READINESS_PASS\n";

    // Now test issue all on JC-60 in same outer transaction; it MUST succeed
    // with corrected 3 missing items and have no effect after rollback.
    $res=$issueCtl->issueAll($r('/erp/job-cards/'.$job->id.'/parts/issue-all','POST',[
        'reason'=>'AM264 rollback QA only after correcting all physical received parts'
    ]),(int)$job->id);
    $assert($res instanceof \Illuminate\Http\RedirectResponse,
        'AM264_BULK_ISSUE_NOT_REDIRECT');
    $issued=(int)$D::table('job_card_parts')->where('job_card_id',$job->id)
        ->where('issue_status','ISSUED')->count();
    $assert($issued>=9,'AM264_BULK_ISSUE_NOT_ALL_NINE_PARTS');
    echo "AM264_JC60_BULK_ISSUE_AFTER_CORRECTED_STOCK_PASS\n";

    // Already issued Part cannot be deducted again by reducing its GRN qty.
    $nowLines=$D::table('am260_grn_lines')->where('grn_id',$grn->id)->orderBy('id')->get();
    $attempt=$payload;
    $attempt['lines']=[];
    $attempt['new_lines']=[];
    foreach($nowLines as $line){
        $attempt['lines'][$line->id]=[
            'original_qty'=>$line->qty_received,
            'original_cost'=>$line->provisional_unit_cost,
            'qty'=>$line->qty_received,
            'unit_cost'=>$line->provisional_unit_cost,
        ];
    }
    $attempt['lines'][$front->id]['qty']=1;
    try{
        $grnCtl->updateReceipt($r('/erp/procurement/grn/'.$grn->id.'/edit','POST',$attempt),
            (int)$grn->id);
        throw new RuntimeException('AM264_ISSUED_PART_GRN_REVERSAL_ALLOWED');
    }catch(\Illuminate\Validation\ValidationException $e){/* expected */}
    echo "AM264_CANNOT_REVERSE_GRN_STOCK_ALREADY_ISSUED_PASS\n";
}finally{
    $D::rollBack();
}
$after=[
    'grn'=>$D::table('am260_grns')->count(),
    'grn_lines'=>$D::table('am260_grn_lines')->count(),
    'movements'=>$D::table('inventory_movements')->count(),
    'corrections'=>$D::table('am264_grn_line_edits')->count(),
    'exceptions'=>$D::table('am262_grn_opening_exceptions')->count(),
    'audits'=>$D::table('job_card_change_audits')->count(),
    'purchases'=>$D::table('workshop_purchases')->count(),
];
$assert($before===$after,'AM264_ROLLBACK_LEFT_TRANSACTIONS');
$current=$D::table('parts_master')->whereIn('id',array_keys($oldStock))
    ->get(['id','stock_qty'])->keyBy('id')->map(static fn($p)=>$p->stock_qty)->all();
$assert($current==$oldStock,'AM264_ROLLBACK_CHANGED_REAL_PART_STOCK');
$assert((int)$D::table('job_card_parts')->where('job_card_id',$job->id)
    ->where('issue_status','ISSUED')->count()===0,
    'AM264_ROLLBACK_LEFT_ISSUED_JC_PARTS');
echo "AM264_ALL_TEST_GRN_STOCK_AND_JC_MOVEMENTS_ROLLED_BACK_PASS\n";
echo "AM264_FULL_AUTHENTICATED_STAGING_QA_SUCCESS\n";
