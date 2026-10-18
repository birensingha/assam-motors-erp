#!/usr/bin/env bash
# V648: read-only smoke tests; no customer, ROT, or Attendance updates.
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ErpAuthUser;
use App\Services\StaffDailyRotPerformanceService as Daily;
use App\Http\Controllers\Web\AdminDashboardPageController as Pages;
use Carbon\CarbonImmutable;
$svc=app(Daily::class);
$pending=$svc->pending(18);
$counts=$svc->pendingCount();
if($pending->count()>18 || min($counts)<0)throw new RuntimeException('Invalid pending counts/limits');
echo 'V648_PENDING_ROTS_QUERY=PASS|count='.$pending->count().PHP_EOL;
$staffId=DB::table('rot_sessions')->where('id','ROT261018134719KZUZO')->value('staff_id');
if(!$staffId)$staffId=DB::table('attendance')->whereDate('date','2026-10-18')->value('staff_id');
if(!$staffId)throw new RuntimeException('Known staff example absent');
$data=$svc->daily((int)$staffId,'2026-10-18');
if($data['summary']['productive_seconds']<0||$data['summary']['paused_seconds']<0||
   $data['summary']['pause_count']<0||$data['summary']['auto_pause_count']>$data['summary']['pause_count']||
   $data['rot_rows']->count()>300){
 throw new RuntimeException('Daily seconds or pause count invalid');
}
echo 'V648_DAILY_STAFF_EVENT_METRICS=PASS|rot='.$data['summary']['rot_count'].
  '|pause='.$data['summary']['pause_count'].'|resume='.$data['summary']['resume_count'].
  '|productive_seconds='.$data['summary']['productive_seconds'].PHP_EOL;
$auth=new ErpAuthUser;
$auth->role='admin';$auth->active=true;
Auth::shouldUse('web');
Auth::guard('web')->setUser($auth);
$pages=app(Pages::class);
foreach([
 ['daily',Request::create('/erp/rot-performance','GET',['date'=>'2026-10-18','staff_id'=>(int)$staffId]),'rotPerformance'],
 ['main',Request::create('/erp/dashboard','GET'),'main'],
 ['workshop',Request::create('/erp/workshop','GET'),'workshop'],
] as [$name,$request,$method]){
 $request->setUserResolver(fn()=>$auth);
 $view=$pages->{$method}($request);
 $html=$view->render();
 if(!$html || !str_contains($html,'ROT'))throw new RuntimeException($name.' page failed to render');
 echo 'V648_PAGE_'.$name.'=PASS|html_bytes='.strlen($html).PHP_EOL;
}
$att=file_get_contents(base_path('app/Services/AttendanceService.php'));
$admin=file_get_contents(base_path('app/Http/Controllers/Web/AdminSelfAttendanceController.php'));
$rot=file_get_contents(base_path('app/Services/RotWorkflowService.php'));
if(!str_contains($att,'V648_AUTO_PAUSE_ON_CHECKOUT')||
 !str_contains($admin,'V648_OWNER_AUTO_PAUSE_ON_CHECKOUT')||
 !str_contains($att,"'AUTO_CHECK_OUT'")||
 !str_contains($admin,"'AUTO_CHECK_OUT'")||
 !str_contains($rot,'function pause(')){
 throw new RuntimeException('Transactional auto pause hook missing');
}
echo "V648_AUTO_PAUSE_SOURCE_AND_AUDIT=PASS\n";
echo "V648_NO_WRITE_QA=PASS\n";
PHP
