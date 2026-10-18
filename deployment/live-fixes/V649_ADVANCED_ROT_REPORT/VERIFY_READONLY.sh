#!/usr/bin/env bash
# V649 read-only smoke tests. No user data update, no live ROT clock mutation.
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\ErpAuthUser;
use App\Services\AdvancedRotPerformanceReportService;
use App\Http\Controllers\Web\AdminDashboardPageController;
$service=app(AdvancedRotPerformanceReportService::class);
$before=[
 'rot'=>DB::table('rot_sessions')->count(),
 'events'=>DB::table('rot_session_events')->count(),
 'attendance'=>DB::table('attendance')->count(),
 'corrections'=>DB::table('rot_admin_corrections')->count(),
];
$base=DB::table('rot_sessions as rs')->join('job_cards as jc','rs.job_card_id','=','jc.id')
 ->where('jc.job_no','JC-20261018-00037')->select('rs.id','rs.rot_code','rs.staff_id','jc.vehicle_reg_no')->first();
if(!$base)throw new RuntimeException('Known JC37 sample missing');
$checks=[
 'JC_FILTER'=>['job_card'=>'JC-20261018-00037'],
 'ROT_FILTER'=>['rot_code'=>$base->rot_code,'job_card'=>'JC-20261018-00037'],
 'VEHICLE_FILTER'=>['vehicle'=>$base->vehicle_reg_no,'job_card'=>'JC-20261018-00037'],
 'DATE_FROM_TO'=>['from'=>'2026-10-16','to'=>'2026-10-18','job_card'=>'JC-20261018-00037'],
 'STAFF_FILTER'=>['staff_id'=>$base->staff_id,'job_card'=>'JC-20261018-00037'],
];
foreach($checks as $name=>$criteria){
 $request=Request::create('/erp/rot-performance','GET',$criteria);
 $data=$service->report($request);
 if($data['count']<1)throw new RuntimeException('Report returned no JC37 records for '.$name);
 foreach($data['groups'] as $group){
  if($group['job_no']!=='JC-20261018-00037')throw new RuntimeException('Wrong JC returned for '.$name);
  if($group['vehicle_out']!=='Not Recorded')throw new RuntimeException('Invented vehicle Out time!');
 }
 foreach($data['groups'] as $group)foreach($group['rots'] as $rot){
   foreach(['actual','elapsed','nonproductive','over_standard','pause_count','resume_count','pause_records','staff_segments','history','staff_activity','days_since_start'] as $k)
    if(!array_key_exists($k,$rot))throw new RuntimeException('Missing ROT detail: '.$k);
   if($rot['actual_seconds']<0 || $rot['nonproductive_seconds']<0)
    throw new RuntimeException('Negative time incorrect');
 }
 echo 'V649_FILTER_'.$name.'=PASS|rot='.$data['count'].PHP_EOL;
}
try{
 $service->report(Request::create('/erp/rot-performance','GET',['from'=>'2026-10-19','to'=>'2026-10-16']));
 throw new RuntimeException('Inverted date range accepted');
}catch(ValidationException $e){
 if(!isset($e->errors()['from']))throw $e;
}
echo 'V649_INVALID_DATE_RANGE_REJECTED=PASS'.PHP_EOL;
$admin=new ErpAuthUser;$admin->role='admin';$admin->active=true;
Auth::shouldUse('web');Auth::guard('web')->setUser($admin);
app('view')->share('errors',new \Illuminate\Support\ViewErrorBag());
$req=Request::create('/erp/rot-performance','GET',['job_card'=>'JC-20261018-00037']);
$req->setUserResolver(fn()=>$admin);
$view=app(AdminDashboardPageController::class)->rotPerformance($req);
$html=$view->render();
foreach(['Advanced Staff / ROT Performance Register','Job Card-wise ROT Register',
 'Staff-wise Activity Counts & Pause Reasons',
 'Pause Reasons / Durations','Check-In / Check-Out Raw Attendance Records','name="job_card"',
 'name="vehicle"','name="rot_code"','name="from"','name="to"'] as $needle){
 if(!str_contains($html,$needle))throw new RuntimeException('Rendered HTML missing '.$needle);
}
if(substr_count($html,'Advanced Staff / ROT Performance Register')!==1)
 throw new RuntimeException('Duplicated report panel');
echo 'V649_AUTHENTICATED_ADMIN_REPORT_RENDER=PASS|bytes='.strlen($html).PHP_EOL;
$after=[
 'rot'=>DB::table('rot_sessions')->count(),
 'events'=>DB::table('rot_session_events')->count(),
 'attendance'=>DB::table('attendance')->count(),
 'corrections'=>DB::table('rot_admin_corrections')->count(),
];
if($before!==$after)throw new RuntimeException('Read only smoke caused data changes');
echo "V649_NO_DATABASE_WRITES=PASS\n";
echo "V649_ADVANCED_READONLY_QA=PASS\n";
PHP
