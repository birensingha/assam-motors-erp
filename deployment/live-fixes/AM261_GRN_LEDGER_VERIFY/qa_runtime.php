<?php
/**
 * AM261 STAGING read/write-in-a-rollback verification.
 * Real Front Brake Pad fixture, synthetic unique QA challan. Every test
 * receipt, inventory movement and stock update is rolled back, with checks.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$DB=\Illuminate\Support\Facades\DB::class;
$user=\App\Models\ErpAuthUser::where('active',1)
    ->whereIn('role',['owner','admin'])->firstOrFail();
$grnController=app(\App\Http\Controllers\Web\AdminProcurementGrnController::class);
$ledgerController=app(\App\Http\Controllers\Web\AdminMasterItemLedgerController::class);
$part=$DB::table('parts_master')->where('part_code','AM-0234')->first();
$job=$DB::table('job_cards')->where('job_no','JC-20261116-00060')->first();
$vendor=$DB::table('workshop_vendors')->where('active',1)
    ->where('vendor_name','like','%Jupiter%')->first();
if(!$vendor)$vendor=$DB::table('workshop_vendors')->where('active',1)->first();
if(!$part||!$job||!$vendor)
    throw new RuntimeException('AM261_TEST_FIXTURE_MISSING');
if($part->stock_qty===null)
    throw new RuntimeException('AM261_FRONT_BRAKE_PAD_OPENING_STOCK_UNRECORDED');
$stock=round((float)$part->stock_qty,3);
$rowsBefore=[
  'grn'=>$DB::table('am260_grns')->count(),
  'lines'=>$DB::table('am260_grn_lines')->count(),
  'movements'=>$DB::table('inventory_movements')->count(),
  'purchases'=>$DB::table('workshop_purchases')->count(),
  'bills'=>$DB::table('am260_grn_bills')->count(),
];
$req=function(string $path,string $method,array $data)use($user){
   $r=\Illuminate\Http\Request::create($path,$method,$data);
   $r->setUserResolver(static fn()=>$user);
   return $r;
};
$indexView=$grnController->index($req('/erp/procurement','GET',[]));
if(!$indexView instanceof \Illuminate\View\View ||
   !str_contains($indexView->render(),'Receive Vendor Challan'))
    throw new RuntimeException('AM261_INDEX_BROKEN');
echo "AM261_PROCUREMENT_REGISTER_RENDER_PASS\n";
$guest=\Illuminate\Http\Request::create('/erp/procurement/grn/1','GET');
$guest->setUserResolver(static fn()=>null);
try{$grnController->showReceipt($guest,1);throw new RuntimeException('AM261_GUEST_WAS_ALLOWED');}
catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 if($e->getStatusCode()!==403)throw $e;
}
echo "AM261_GRN_DETAIL_GUEST_BLOCKED_PASS\n";
$DB::beginTransaction();
try{
  $challan='AM261-QA-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(9));
  $today=now('Asia/Kolkata')->format('Y-m-d');
  $post=[
    'vendor_id'=>(int)$vendor->id,
    'job_card_id'=>(int)$job->id,
    'challan_no'=>$challan,
    'challan_date'=>$today,
    'receipt_date'=>$today,
    'notes'=>'AM261 rollback-only test. Not a real delivery.',
    'lines'=>[['part_master_id'=>(int)$part->id,'qty'=>1,'unit_cost'=>100]]
  ];
  $response=$grnController->postReceipt($req('/erp/procurement/receive','POST',$post));
  if(!$response instanceof \Illuminate\Http\RedirectResponse)
    throw new RuntimeException('AM261_GRN_POST_NO_REDIRECT');
  $grn=$DB::table('am260_grns')->where('challan_no',$challan)->first();
  if(!$grn)throw new RuntimeException('AM261_POSTED_GRN_NOT_FOUND');
  if(!str_contains($response->getTargetUrl(),'/procurement/grn/'.$grn->id))
    throw new RuntimeException('AM261_POST_NOT_REDIRECTED_TO_VERIFICATION');
  $view=$grnController->showReceipt(
     $req('/erp/procurement/grn/'.$grn->id,'GET',[]),(int)$grn->id);
  if(!$view instanceof \Illuminate\View\View)
    throw new RuntimeException('AM261_DETAIL_NOT_VIEW');
  $data=$view->getData();
  if(!$data['allVerified']||$data['lines']->count()!==1 ||
     !$data['lines']->first()->movement_verified ||
     abs((float)$data['lines']->first()->confirmed_stock_in-1)>.0001)
    throw new RuntimeException('AM261_RECEIPT_NOT_VERIFIED_BY_MOVEMENT');
  $html=$view->render();
  foreach(['Stock-In Verified','View Item Ledger',$part->part_code,$grn->grn_no] as $token)
     if(!str_contains($html,$token))
       throw new RuntimeException('AM261_RECEIPT_VERIFY_PAGE_MISSING_'.$token);
  echo "AM261_CONFIRMED_GRN_DETAIL_SHOWS_ACTUAL_MOVEMENT_AND_LEDGER_LINK_PASS\n";
  $ledger=$ledgerController->index(
      $req('/erp/masters/item-ledger','GET',['type'=>'PART','id'=>(int)$part->id]));
  if(!$ledger instanceof \Illuminate\View\View)
      throw new RuntimeException('AM261_ITEM_LEDGER_NOT_VIEW');
  $report=$ledger->getData()['report']??null;
  if(!is_array($report) || !isset($report['events']))
      throw new RuntimeException('AM261_ITEM_LEDGER_REPORT_MISSING');
  $matches=array_values(array_filter($report['events'],
      static fn($event)=>($event['reference']??'')===$grn->grn_no &&
                          ($event['type']??'')==='STOCK · GRN_RECEIPT_IN'));
  if(count($matches)!==1 || abs((float)$matches[0]['in']-1)>.0001)
      throw new RuntimeException('AM261_GRN_NOT_VISIBLE_IN_ITEM_LEDGER');
  echo "AM261_REAL_ITEM_LEDGER_SHOWS_GRN_RECEIPT_IN_ONCE_PASS\n";
  if($DB::table('workshop_purchases')->count()!==$rowsBefore['purchases'])
      throw new RuntimeException('AM261_GRN_UNEXPECTED_PURCHASE_BILL');
  if(abs((float)$DB::table('parts_master')->where('id',$part->id)->value('stock_qty')
       -($stock+1))>.0001)
      throw new RuntimeException('AM261_GRN_STOCK_IN_WRONG');
  echo "AM261_GRN_STOCK_IN_ONCE_BILL_UNCHANGED_PASS\n";
}finally{
  $DB::rollBack();
}
$rowsAfter=[
  'grn'=>$DB::table('am260_grns')->count(),
  'lines'=>$DB::table('am260_grn_lines')->count(),
  'movements'=>$DB::table('inventory_movements')->count(),
  'purchases'=>$DB::table('workshop_purchases')->count(),
  'bills'=>$DB::table('am260_grn_bills')->count(),
];
if($rowsBefore!==$rowsAfter)
   throw new RuntimeException('AM261_QA_TEST_ROWS_NOT_ROLLED_BACK');
if(abs((float)$DB::table('parts_master')->where('id',$part->id)->value('stock_qty')-$stock)>.0001)
   throw new RuntimeException('AM261_QA_CHANGED_REAL_STOCK');
echo "AM261_NO_REAL_GRN_MOVEMENTS_OR_STOCK_CHANGED_BY_QA_PASS\n";
echo "AM261_AUTHENTICATED_LEDGER_RUNTIME_ALL_PASS\n";
