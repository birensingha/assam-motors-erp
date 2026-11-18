<?php
/**
 * AM268 live STAGING read-only QA. Transactional data is never mutated:
 * compares raw Item Ledger to server-side consolidated grouping; validates
 * all original entries accessible in one expandable row per voucher or JC.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());

$D=\Illuminate\Support\Facades\DB::class;
$assert=static function(bool $okay,string $msg):void{if(!$okay)throw new RuntimeException($msg);};
$user=\App\Models\ErpAuthUser::query()->where('active',1)
 ->whereIn('role',['owner','admin'])->firstOrFail();
$controller=app(\App\Http\Controllers\Web\AdminMasterItemLedgerController::class);
$request=static function(string $mode)use($user){
 $q=\Illuminate\Http\Request::create('/erp/masters/item-ledger','GET',[
   'type'=>'PART','id'=>234,'ledger_view'=>$mode
 ]);
 $q->setUserResolver(static fn()=>$user);
 return $q;
};
$before=[
 'inventory_moves'=>$D::table('inventory_movements')->count(),
 'parts_master_stock'=>$D::table('parts_master')->where('id',234)->value('stock_qty'),
 'purchase_lines'=>$D::table('workshop_purchases')->count(),
 'grns'=>$D::table('am260_grns')->count(),
 'grn_lines'=>$D::table('am260_grn_lines')->count(),
 'jc_lines'=>$D::table('job_card_parts')->count(),
 'invoices'=>$D::table('invoices')->count(),
];
$rawView=$controller->index($request('raw'));
$compactView=$controller->index($request('consolidated'));
$assert($rawView instanceof \Illuminate\View\View &&
  $compactView instanceof \Illuminate\View\View,'AM268_LEDGER_VIEW_NOT_RETURNED');
$raw=$rawView->getData()['report'];
$group=$compactView->getData()['report'];
$src=$raw['events'];$main=$group['events'];
$assert(count($src)>=4,'AM268_RAW_LEDGER_HAS_NO_SOURCE_EVENTS');
$assert(count($main)<count($src),'AM268_CONSOLIDATION_DID_NOT_REDUCE_ROWS');
$assert((int)($group['source_event_count']??0)===count($src),
 'AM268_ORIGINAL_SOURCE_COUNT_NOT_PRESERVED');
$flatten=[];
foreach($main as $entry){
  $source=$entry['source_events']??[$entry];
  $assert((int)($entry['source_count']??1)===count($source),
     'AM268_ENTRY_AUDIT_COUNT_WRONG');
  foreach($source as $event)$flatten[]=$event;
}
$assert(count($flatten)===count($src),
    'AM268_ORIGINAL_SOURCE_EVENTS_DROPPED_OR_DUPLICATED');
foreach(['in','out'] as $key){
    $actual=array_sum(array_map(static fn($e)=>(float)($e[$key]??0),$src));
    $summed=array_sum(array_map(static fn($e)=>(float)($e[$key]??0),$main));
    $assert(abs($actual-$summed)<0.0001,'AM268_STOCK_TOTAL_DOUBLE_COUNT_'.$key);
}
foreach(['current_stock','reconstructed_current','opening','closing','variance'] as $key)
   $assert($raw[$key]===$group[$key],'AM268_REAL_STOCK_KPI_CHANGED_'.$key);
echo "AM268_ALL_ORIGINAL_MOVEMENTS_PRESERVED_NO_STOCK_DOUBLE_COUNT_PASS\n";
if(count($src)===7){
  $assert(count($main)===4,'AM268_FRONT_BRAKE_PAD_7_RAW_EXPECT_4_CONSOLIDATED');
  $refs=array_column($main,'reference');
  foreach(['JC-2026-0023','JC-20261116-00060',
       'PB-261117155031-835','GRN-261117145056-NCWOTCC'] as $reference)
    $assert(in_array($reference,$refs,true),'AM268_MISSING_COMBINED_REF_'.$reference);
  echo "AM268_FRONT_BRAKE_PAD_REAL_7_EVENTS_TO_4_CONSOLIDATED_ROWS_PASS\n";
}else echo "AM268_LIVE_CHANGED_SINCE_PDF_SOURCE_EVENTS=".count($src)." MAIN_ROWS=".count($main)."\n";
$byRef=[];foreach($main as $entry)$byRef[$entry['reference']]=$entry;
if(isset($byRef['GRN-261117145056-NCWOTCC'],$byRef['PB-261117155031-835'])){
   $assert($byRef['GRN-261117145056-NCWOTCC']['party']==='JUPITER AUTOMOBILE' &&
    $byRef['PB-261117155031-835']['party']==='SHYAM MOTORS',
    'AM268_UNRELATED_VENDOR_RECEIPTS_MERGED');
   echo "AM268_DISTINCT_JUPITER_AND_SHYAM_PURCHASE_VOUCHERS_NOT_MERGED_PASS\n";
}
$compactHtml=$compactView->render();
$rawHtml=$rawView->render();
foreach(['Consolidated — One Row / Voucher or JC',
 'Full Audit — Every Source Event','View Original Entries','source events',
 'am268-source-details','JC-20261116-00060'] as $marker)
   $assert(str_contains($compactHtml,$marker),'AM268_COMPACT_HTML_MISSING_'.$marker);
$assert(!str_contains($rawHtml,'View Original Entries (2)'),
 'AM268_RAW_VIEW_WRONGLY_SHOWS_COMPACT_AUDIT');
echo "AM268_EXPAND_ORIGINAL_EVENTS_AND_RAW_TOGGLE_RENDER_PASS\n";
$guest=\Illuminate\Http\Request::create('/erp/masters/item-ledger','GET',[
 'type'=>'PART','id'=>234,'ledger_view'=>'consolidated'
]);
$guest->setUserResolver(static fn()=>null);
try{$controller->index($guest);throw new RuntimeException('AM268_UNAUTHENTICATED_LEDGER_ALLOWED');}
catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 $assert($e->getStatusCode()===403,'AM268_GUEST_STATUS_WRONG');
}
echo "AM268_OWNER_ADMIN_ACCESS_UNCHANGED_PASS\n";
$after=[
 'inventory_moves'=>$D::table('inventory_movements')->count(),
 'parts_master_stock'=>$D::table('parts_master')->where('id',234)->value('stock_qty'),
 'purchase_lines'=>$D::table('workshop_purchases')->count(),
 'grns'=>$D::table('am260_grns')->count(),
 'grn_lines'=>$D::table('am260_grn_lines')->count(),
 'jc_lines'=>$D::table('job_card_parts')->count(),
 'invoices'=>$D::table('invoices')->count(),
];
$assert($before==$after,'AM268_READONLY_LEDGER_MUTATED_BUSINESS_ROWS');
echo "AM268_NO_STOCK_JC_GRN_PURCHASE_OR_INVOICE_DATA_MODIFIED_PASS\n";
echo "AM268_FULL_AUTHENTICATED_CONSOLIDATED_LEDGER_QA_PASS\n";
