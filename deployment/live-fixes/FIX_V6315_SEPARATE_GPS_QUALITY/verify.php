<?php
/** Staging-only non-mutating quality and geofence acceptance tests. */
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$st=$db::table('staff_location_settings')->where('id',1)->first();
if (!$st || (int)$st->max_accuracy_m!==100 ||
    (int)$st->checkin_max_accuracy_m!==30 || (int)$st->radius_m!==100 ||
    !(bool)$st->geofence_checkin_enabled) {
    throw new RuntimeException('GPS policy mismatch: reporting=100, Check-In=30 and geofence=100 expected.');
}
echo 'REPORTING_MAX_ACCURACY_METRES='.$st->max_accuracy_m.PHP_EOL;
echo 'CHECKIN_MAX_ACCURACY_METRES='.$st->checkin_max_accuracy_m.PHP_EOL;
echo 'GEOFENCE_RADIUS_METRES='.$st->radius_m.PHP_EOL;
$service=app(\App\Services\GeoFenceService::class);
$lat=(float)$st->latitude; $lon=(float)$st->longitude;
$reporting=$service->verify($lat,$lon,100.0,false);
if (!($reporting['verified']??false)) throw new RuntimeException('Login/reporting 100m regression.');
echo "REPORTING_100M_ALLOWED=PASS\n";
$checkin=$service->verify($lat,$lon,20.0,false,true);
if (!($checkin['verified']??false)) throw new RuntimeException('Precise Check-In 20m must pass.');
echo "CHECKIN_PRECISE_20M_ALLOWED=PASS\n";
$deny=function(float $x,float $y,float $accuracy,bool $mocked,string $name)use($service):void{
    try{$service->verify($x,$y,$accuracy,$mocked,true);}
    catch(\Illuminate\Validation\ValidationException $e){echo $name."=BLOCKED_AS_REQUIRED\n";return;}
    throw new RuntimeException("Unsafe Check-In accepted: ".$name);
};
$deny($lat,$lon,100.0,false,'CHECKIN_WEAK_NETWORK_100M');
$deny($lat,$lon,20.0,true,'CHECKIN_MOCK_GPS');
$deny($lat+0.00075,$lon,20.0,false,'CHECKIN_BOUNDARY_WITH_LOCATION_UNCERTAINTY');
echo "SEPARATE_GPS_REPORTING_AND_GEOFENCE_VERIFY=PASS\n";
