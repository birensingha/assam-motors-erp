<?php
/** AM267 read-only authenticated STAGING runtime QA — Front Brake Pad AM-0234.
 * Validate distinct financial/physical/ERP date fields, real vendor source on
 * stock IN, event counts, balances and unchanged database fingerprints.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$D=\Illuminate\Support\Facades\DB::class;
$u=\App\Models\ErpAuthUser::where('active',1)->whereIn('role',['owner','admin'])->firstOrFail();
$r=\Illuminate\Http\Request::create('/erp/masters/item-ledger','GET',[
 'type'=>'PART','id'=>234
]);
$r->setUserResolver(static fn()=>$u);
$counts=function()use($D):array{
 return [
    'move'=>$D::table('inventory_movements')->count(),
    'purchases'=>$D::table('workshop_purchases')->count(),
    'grn'=>$D::table('am260_grns')->count(),
    'grn_lines'=>$D::table('am260_grn_lines')->count(),
    'jc_parts'=>$D::table('job_card_parts')->count(),
    'stock'=>$D::table('parts_master')->where('id',234)->value('stock_qty')
 ];
};
$before=$counts();
$view=app(\App\Http\Controllers\Web\AdminMasterItemLedgerController::class)->index($r);
if(!$view instanceof \Illuminate\View\View)
    throw new RuntimeException('AM267_QA_NOT_VIEW');
$report=$view->getData()['report']??[];
$events=$report['events']??[];
if(count($events)<5)
    throw new RuntimeException('AM267_QA_MISSING_LEDGER_EVENTS');
$match=static function($type,$reference)use($events){
   foreach($events as $event)if(($event['type']??'')===$type && ($event['reference']??'')===$reference)
      return $event;
   throw new RuntimeException('AM267_QA_MISSING_EVENT_'.$type.'_'.$reference);
};
$grn=$match('STOCK · GRN_RECEIPT_IN','GRN-261117145056-NCWOTCC');
if($grn['document_date']!=='2026-11-15' ||
   $grn['erp_posted_date']!=='2026-11-17' ||
   $grn['party']!=='JUPITER AUTOMOBILE' ||
   abs((float)$grn['in']-1)>0.0001 ||
   !str_contains((string)$grn['url'],'/erp/procurement/grn/5'))
   throw new RuntimeException('AM267_GRN_PHYSICAL_RECEIPT_VS_POSTED_DATE_VENDOR_MISMATCH');
echo "AM267_GRN_PHYSICAL_15NOV_ERP_POSTED_17NOV_VENDOR_JUPITER_PASS\n";
$bill=$match('PURCHASE · BILL','PB-261117155031-835');
if($bill['document_date']!=='2026-10-03' ||
   $bill['erp_posted_date']!=='2026-11-17' ||
   $bill['party']!=='SHYAM MOTORS' ||
   abs((float)$bill['amount']-3397.44)>.01 ||
   $bill['in']!==null)
    throw new RuntimeException('AM267_BILL_INVOICE_DATE_VS_POSTED_DATE_WRONG');
echo "AM267_SHYAM_BILL_DATE_03OCT_ERP_SAVED_17NOV_NO_DOUBLE_STOCK_PASS\n";
$posted=$match('STOCK · PURCHASE_IN','SM/GST2627/4829');
if($posted['document_date']!=='2026-10-03' ||
   $posted['erp_posted_date']!=='2026-11-17' ||
   $posted['party']!=='SHYAM MOTORS' ||
   abs((float)$posted['in']-1)>0.0001)
    throw new RuntimeException('AM267_SHYAM_PURCHASE_IN_DATE_VENDOR_WRONG');
echo "AM267_SHYAM_NORMAL_PURCHASE_STOCK_IN_17NOV_SOURCE_BILL_03OCT_PASS\n";
$issue=$match('STOCK · JOB_ISSUE','JC-2026-0023');
if($issue['date']!=='2026-10-03' ||
   abs((float)$issue['out']-1)>0.0001 ||
   abs((float)$issue['balance']+1)>0.0001)
    throw new RuntimeException('AM267_03OCT_JOB_ISSUE_HISTORY_CHANGED');
echo "AM267_03OCT_NEGATIVE_JOB_ISSUE_HISTORY_UNCHANGED_PASS\n";
if(abs((float)$report['current_stock']-1)>.0001 ||
   abs((float)$report['reconstructed_current']-1)>.0001 ||
   abs((float)$report['variance'])>.0001)
    throw new RuntimeException('AM267_STOCK_RECONCILIATION_CHANGED');
echo "AM267_MASTER_STOCK_1_RECONCILES_NO_DOUBLE_COUNT_PASS\n";
$html=$view->render();
foreach(['Ledger Date','Invoice / Receipt Date','ERP Posted',
 'am267-ledger-event-filter','Stock IN / OUT Only','Shyam',
 'JUPITER AUTOMOBILE'] as $marker){
   if(!str_contains(strtolower($html),strtolower($marker)))
        throw new RuntimeException('AM267_MISSING_LEDGER_UI_'.str_replace(' ','_',$marker));
}
echo "AM267_CATEGORY_FILTER_AND_DUAL_DATE_UI_RENDER_PASS\n";
$guest=\Illuminate\Http\Request::create('/erp/masters/item-ledger','GET',['type'=>'PART','id'=>234]);
$guest->setUserResolver(static fn()=>null);
try{app(\App\Http\Controllers\Web\AdminMasterItemLedgerController::class)->index($guest);
 throw new RuntimeException('AM267_GUEST_LEDGER_ALLOWED');
}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 if($e->getStatusCode()!==403)throw $e;
}
echo "AM267_LEDGER_OWNER_ADMIN_AUTH_UNCHANGED_PASS\n";
if($counts()!==$before)
  throw new RuntimeException('AM267_READONLY_QA_MODIFIED_DATABASE');
echo "AM267_NO_JC_GRN_PURCHASE_INVOICE_OR_MOVEMENTS_MUTATED_PASS\n";
echo "AM267_FRONT_BRAKE_PAD_LEDGER_RUNTIME_ALL_PASS\n";
