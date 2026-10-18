<?php
/**
 * Native Live Duty Location read-only smoke test.
 * No tokens, names, coordinates, or user-identifying data printed.
 * Safe: only reads admin account, staff/attendance/location rows.
 */
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$controller=app(\App\Http\Controllers\Web\StaffLiveLocationController::class);
$admin=\App\Models\ErpAuthUser::query()->where('active',true)->get()
    ->first(static fn($u)=>$u->isAdmin());
if(!$admin)throw new RuntimeException('No active admin for safe read-only test.');
\Illuminate\Support\Facades\Auth::guard('web')->setUser($admin);
$make=static function(array $args=[])use($admin):\Illuminate\Http\Request{
    $req=\Illuminate\Http\Request::create('/erp/staff-live-location/feed','GET',$args);
    $req->setUserResolver(static fn()=> $admin);
    return $req;
};
$meta=$controller->feed($make(['after'=>0,'meta'=>1]));
if($meta->getStatusCode()!==200)throw new RuntimeException('Initial feed HTTP not 200.');
$data=$meta->getData(true);
if(!($data['ok']??false) || !isset($data['points'],$data['cursor']) ||
    !is_array($data['points']) || !is_array($data['staff'])) {
    throw new RuntimeException('Initial native GPS feed shape invalid.');
}
foreach($data['points'] as $point) {
    foreach(['staff_id','recorded_at','received_at','accuracy_m','lat','lng'] as $key)
        if(!array_key_exists($key,$point))throw new RuntimeException('GPS point shape missing '.$key);
    foreach(['device_id','email','phone','password','token','edit_token'] as $key)
        if(array_key_exists($key,$point))throw new RuntimeException('Sensitive field leaked: '.$key);
}
echo "STAFF_LIVE_INITIAL_FEED_HTTP=200\n";
echo "STAFF_LIVE_INITIAL_STAFF_TOTAL=".count($data['staff'])."\n";
echo "STAFF_LIVE_INITIAL_LATEST_FIX_TOTAL=".count($data['points'])."\n";
$delta=$controller->feed($make(['after'=>max(1,(int)$data['cursor']),'meta'=>0]));
if($delta->getStatusCode()!==200 || ($delta->getData(true)['ok']??false)!==true)
    throw new RuntimeException('Incremental feed failed.');
echo "STAFF_LIVE_DELTA_READONLY=PASS\n";
$view=$controller->index($make());
if($view->name()!=='admin.native.staff-live-location')
    throw new RuntimeException('Incorrect protected view.');
$html=$view->render();
if(strpos($html,'id="slMap"')===false || strpos($html,'staff-live-location/feed')===false)
    throw new RuntimeException('Native page render/route failed.');
echo "STAFF_LIVE_PAGE_BLADE_RENDER=PASS\n";
if(count($data['staff'])>0) {
    $id=(int)$data['staff'][0]['id'];
    $history=$controller->history($make([
        'staff_id'=>$id,'date'=>\Carbon\CarbonImmutable::now('Asia/Kolkata')->toDateString(),
    ]));
    if($history->getStatusCode()!==200 || ($history->getData(true)['ok']??false)!==true)
        throw new RuntimeException('Authenticated history request failed.');
    echo "STAFF_LIVE_HISTORY_AUTH_READONLY=PASS\n";
}
$unauth=\Illuminate\Http\Request::create('/erp/staff-live-location/feed','GET');
$unauth->setUserResolver(static fn()=>null);
try{$controller->feed($unauth);throw new RuntimeException('SECURITY FAILED: guest accessed Staff GPS');}
catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
    if($e->getStatusCode()!==403)throw $e;
    echo "STAFF_LIVE_GUEST_ACCESS=DENIED_403\n";
}
echo "STAFF_LIVE_FULL_READONLY_SMOKE=PASS\n";
