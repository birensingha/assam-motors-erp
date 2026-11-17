<?php
declare(strict_types=1);

error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$DB=\Illuminate\Support\Facades\DB::class;
$user=\App\Models\ErpAuthUser::where('active',1)
    ->whereIn('role',['owner','admin'])->firstOrFail();
$controller=app(\App\Http\Controllers\Web\AdminServiceSalesRegisterController::class);
$before=[
  'invoices'=>$DB::table('invoices')->count(),
  'job_card_labour'=>$DB::table('job_card_labour')->count(),
  'credit_notes'=>$DB::table('invoice_credit_notes')->count(),
  'purchases'=>$DB::table('workshop_purchases')->count(),
  'expenses'=>$DB::table('workshop_expenses')->count(),
  'plan'=>(string)($DB::table('admin_settings')->where('setting_key','am257_profit_control_plan_v1')->value('setting_value')??''),
];
$fetch=function(string $type,array $params=[])use($controller,$user){
    $req=\Illuminate\Http\Request::create('/erp/sales-register/'.$type,'GET',$params);
    $req->setUserResolver(static fn()=>$user);
    $res=$controller->$type($req);
    if(!$res instanceof \Illuminate\View\View)
        throw new RuntimeException('AM259_NOT_A_BLADE_VIEW_'.$type);
    $html=$res->render();
    foreach(['Sales Register','Total Customer Discount','Total','From Date','To Date'] as $token)
       if(!str_contains($html,$token))
          throw new RuntimeException('AM259_VIEW_MARKUP_MISSING_'.$type.'_'.$token);
    if(!str_contains($html,$type==='osl'?'OSL Sales Register':'Labour / ROT Sales Register'))
       throw new RuntimeException('AM259_WRONG_CATEGORY_TITLE_'.$type);
    return $res->getData();
};
$l=$fetch('labour');
$o=$fetch('osl');
if($l['category']!=='labour' || $o['category']!=='osl')
    throw new RuntimeException('AM259_CATEGORY_CONDITION_NOT_SEPARATED');
if((int)$l['summary']->item_count<1 || (int)$o['summary']->item_count<1)
    throw new RuntimeException('AM259_STAGING_SERVICE_FIXTURES_MISSING');
echo "AM259_LABOUR_AND_OSL_VIEW_RENDER_PASS\n";
$idsL=array_map('intval',$l['rows']->getCollection()->pluck('line_id')->all());
$idsO=array_map('intval',$o['rows']->getCollection()->pluck('line_id')->all());
if(array_intersect($idsL,$idsO))
    throw new RuntimeException('AM259_LABOUR_OSL_DUPLICATE_LINE_IDS');
echo "AM259_DISTINCT_LABOUR_OSL_SERVICE_LINES_PASS\n";
if(abs(
    ((float)$l['summary']->net_sales+(float)$l['summary']->discounts)
    -(float)$l['summary']->gross_before_discount
)>0.02 || abs(
    ((float)$o['summary']->net_sales+(float)$o['summary']->discounts)
    -(float)$o['summary']->gross_before_discount
)>0.02)throw new RuntimeException('AM259_DISCOUNT_GROSS_MATH_ERROR');
echo "AM259_GROSS_LESS_DISCOUNT_EQUALS_NET_PASS\n";
$latestInvoice=$DB::table('invoices')
    ->selectRaw('MAX(id) as id')->whereNotNull('job_card_id')
    ->whereRaw("LOWER(COALESCE(status,'')) NOT IN ('reversed','void','cancelled')")
    ->groupBy('job_card_id');
$expected=$DB::table('job_card_labour as l')
    ->join('invoices as i','i.job_card_id','=','l.job_card_id')
    ->joinSub($latestInvoice,'latest','latest.id','=','i.id')
    ->where(static function($q){$q->whereNull('l.billing_included')->orWhere('l.billing_included',1);})
    ->count();
if($expected!==(int)$l['summary']->item_count+(int)$o['summary']->item_count)
    throw new RuntimeException('AM259_REGISTER_SPLIT_DID_NOT_RECONCILE');
echo "AM259_ALL_INVOICED_SERVICE_LINES_RECONCILE_PASS\n";
$oslFirst=$o['rows']->first();
if($oslFirst){
    $match=$fetch('osl',['q'=>(string)$oslFirst->invoice_no]);
    if((int)$match['summary']->item_count<1 ||
       (int)$match['summary']->item_count>(int)$o['summary']->item_count)
        throw new RuntimeException('AM259_OSL_SEARCH_FAILED');
}
$labourFirst=$l['rows']->first();
if($labourFirst){
    $match=$fetch('labour',['from'=>substr((string)$labourFirst->invoice_date,0,10),
        'to'=>substr((string)$labourFirst->invoice_date,0,10)]);
    if((int)$match['summary']->item_count<1)
        throw new RuntimeException('AM259_LABOUR_SINGLE_DATE_FILTER_FAILED');
}
echo "AM259_INVOICE_SEARCH_AND_DATE_FILTERS_PASS\n";
foreach(['labour','osl'] as $type){
   $guest=\Illuminate\Http\Request::create('/erp/sales-register/'.$type,'GET');
   $guest->setUserResolver(static fn()=>null);
   try{
       $controller->$type($guest);
       throw new RuntimeException('AM259_GUEST_ALLOWED_'.$type);
   }catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
       if($e->getStatusCode()!==403)throw $e;
   }
}
echo "AM259_GUEST_AUTH_403_FOR_BOTH_REGISTERS_PASS\n";
$after=[
  'invoices'=>$DB::table('invoices')->count(),
  'job_card_labour'=>$DB::table('job_card_labour')->count(),
  'credit_notes'=>$DB::table('invoice_credit_notes')->count(),
  'purchases'=>$DB::table('workshop_purchases')->count(),
  'expenses'=>$DB::table('workshop_expenses')->count(),
  'plan'=>(string)($DB::table('admin_settings')->where('setting_key','am257_profit_control_plan_v1')->value('setting_value')??''),
];
if($after!==$before)throw new RuntimeException('AM259_FINANCIAL_ROWS_OR_SETTINGS_CHANGED');
echo "AM259_FINANCIAL_RECORDS_READONLY_PASS\n";
echo "AM259_ALL_AUTHENTICATED_RUNTIME_QA_PASS\n";
