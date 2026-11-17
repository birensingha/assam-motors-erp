<?php
declare(strict_types=1);

error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$DB=\Illuminate\Support\Facades\DB::class;
$user=\App\Models\ErpAuthUser::where('active',1)->whereIn('role',['owner','admin'])->firstOrFail();
$controller=app(\App\Http\Controllers\Web\AdminWorkshopProfitControlController::class);
$invoice=$DB::table('invoices')->whereNotNull('invoice_date')
  ->whereRaw("LOWER(COALESCE(status,'')) NOT IN ('reversed','void','cancelled')")
  ->orderByDesc('invoice_date')->first(['invoice_date']);
if(!$invoice)throw new RuntimeException('AM258_MISSING_VALID_INVOICE_DATE');
$date=substr((string)$invoice->invoice_date,0,10);
$before=[
 'invoices'=>$DB::table('invoices')->count(),
 'expenses'=>$DB::table('workshop_expenses')->count(),
 'credits'=>$DB::table('invoice_credit_notes')->count(),
 'parts'=>$DB::table('job_card_parts')->count(),
 'purchases'=>$DB::table('workshop_purchases')->count(),
 'plan'=>(string)($DB::table('admin_settings')->where('setting_key','am257_profit_control_plan_v1')->value('setting_value')??''),
];
$get=function(array $params)use($controller,$user){
    $request=\Illuminate\Http\Request::create('/erp/profit-control','GET',$params);
    $request->setUserResolver(static fn()=>$user);
    $response=$controller->index($request);
    if(!$response instanceof \Illuminate\View\View)
        throw new RuntimeException('AM258_EXPECTED_VIEW');
    $html=$response->render();
    foreach(['Report Period','Last 10 Days','Last 30 Days','Custom From–To','Discount Control Register'] as $label)
        if(!str_contains($html,$label))throw new RuntimeException('AM258_EXPECTED_FIELD_'.str_replace(' ','_',$label));
    return $response->getData();
};
$day=$get(['preset'=>'day','date'=>$date]);
if($day['period']['days']!==1 || $day['period']['from']!==$date ||
   $day['data']['invoice_count']<1)throw new RuntimeException('AM258_DAY_WRONG');
echo "AM258_SINGLE_DAY_COMPATIBILITY_PASS\n";
$ten=$get(['preset'=>'last10','date'=>$date]);
$thirty=$get(['preset'=>'last30','date'=>$date]);
$seven=$get(['preset'=>'last7','date'=>$date]);
$mtd=$get(['preset'=>'month','date'=>$date]);
if($ten['period']['days']!==10 || $thirty['period']['days']!==30 ||
   $seven['period']['days']!==7 || $mtd['period']['days']<1 ||
   $thirty['data']['invoice_count']<$ten['data']['invoice_count'] ||
   $ten['data']['invoice_count']<$day['data']['invoice_count'])
    throw new RuntimeException('AM258_PRESETS_WRONG');
echo "AM258_7_10_30_MTD_PERIOD_TOTALS_PASS\n";
$start=\Illuminate\Support\Carbon::parse($date)->subDays(9)->format('Y-m-d');
$custom=$get(['preset'=>'custom','from'=>$start,'to'=>$date]);
if($custom['period']['days']!==10)
    throw new RuntimeException('AM258_CUSTOM_RANGE_DAYS_WRONG');
foreach(['net_sales','known_discounts','expenses_job','expenses_other','invoice_count','credit_adjustment'] as $field)
    if(abs($custom['data'][$field]-$ten['data'][$field])>.01)
        throw new RuntimeException('AM258_CUSTOM_PRESET_MISMATCH_'.$field);
echo "AM258_CUSTOM_RANGE_AGGREGATION_MATCHES_PRESET_PASS\n";
if(($day['forecast']['configured']??false) && ($ten['forecast']['configured']??false)){
    if(abs($day['forecast']['estimated_working_days']-1)>.0001 ||
       $ten['forecast']['period_billing_target'] < $day['forecast']['daily_billing_target'] ||
       abs($day['forecast']['daily_billing_target'] - $ten['forecast']['daily_billing_target'])>.01)
        throw new RuntimeException('AM258_TARGET_SCALING_WRONG');
}
echo "AM258_TARGET_SCALES_WITH_WORKDAY_ESTIMATE_PASS\n";
$export=\Illuminate\Http\Request::create('/erp/profit-control','GET',['preset'=>'last10','date'=>$date,'export'=>'csv']);
$export->setUserResolver(static fn()=>$user);
$csv=$controller->index($export);
if(!$csv instanceof \Symfony\Component\HttpFoundation\StreamedResponse ||
   $csv->getStatusCode()!==200 ||
   !str_contains((string)$csv->headers->get('content-disposition'),'_to_'))
    throw new RuntimeException('AM258_CSV_WRONG');
echo "AM258_CSV_RANGE_RESPONSE_PASS\n";
foreach([
    ['preset'=>'custom','from'=>$date,'to'=>$start],
    ['preset'=>'custom','from'=>'2024-01-01','to'=>$date],
    ['preset'=>'custom','from'=>$date],
] as $params){
    $request=\Illuminate\Http\Request::create('/erp/profit-control','GET',$params);
    $request->setUserResolver(static fn()=>$user);
    try{
        $controller->index($request);
        throw new RuntimeException('AM258_UNSAFE_RANGE_ACCEPTED');
    }catch(\Illuminate\Validation\ValidationException $e){
        // expected validation error
    }
}
echo "AM258_BAD_RANGE_REJECTED_PASS\n";
$guest=\Illuminate\Http\Request::create('/erp/profit-control','GET',['preset'=>'last30','date'=>$date]);
$guest->setUserResolver(static fn()=>null);
try{
    $controller->index($guest);
    throw new RuntimeException('AM258_GUEST_ALLOWED');
}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
    if($e->getStatusCode()!==403)throw $e;
}
echo "AM258_GUEST_DENIED_PASS\n";
$after=[
 'invoices'=>$DB::table('invoices')->count(),
 'expenses'=>$DB::table('workshop_expenses')->count(),
 'credits'=>$DB::table('invoice_credit_notes')->count(),
 'parts'=>$DB::table('job_card_parts')->count(),
 'purchases'=>$DB::table('workshop_purchases')->count(),
 'plan'=>(string)($DB::table('admin_settings')->where('setting_key','am257_profit_control_plan_v1')->value('setting_value')??''),
];
if($before!==$after)throw new RuntimeException('AM258_DATA_MUTATION_DETECTED');
echo "AM258_INVOICE_EXPENSE_SETTINGS_UNCHANGED_PASS\n";
echo "AM258_ALL_RUNTIME_QA_PASS\n";
