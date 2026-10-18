<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
function p(string $k,mixed $v):void{echo 'P59_'.$k.'='.json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
p('MODE','READONLY_NO_LAT_LONG_NO_IDENTIFYING_PERSONAL_DATA');
foreach(['erp_auth_users','staff','staff_devices','staff_location_logs','staff_location_settings','attendance','hr_audit_logs'] as $t){
 if(Schema::hasTable($t))p('TABLE',[$t,Schema::getColumnListing($t),DB::table($t)->count()]);
}
foreach(Route::getRoutes() as $r){
 $s=$r->uri().' '.$r->getName().' '.$r->getActionName();
 if(preg_match('~(attendance|location|check.in|staff.portal|self.attendance|hr)~i',$s)){
  p('ROUTE',[$r->uri(),$r->getName(),$r->methods(),$r->getActionName(),$r->gatherMiddleware()]);
 }
}
if(Schema::hasTable('erp_auth_users')){
 p('ACCOUNT_ROLES',DB::table('erp_auth_users')->select('role')->selectRaw('COUNT(*) as accounts, SUM(CASE WHEN staff_id IS NOT NULL THEN 1 ELSE 0 END) AS linked')->groupBy('role')->get());
 $ratna=DB::table('erp_auth_users')->whereRaw("LOWER(name) LIKE '%ratna%'")->get(['id','role','staff_id','active']);
 p('RATNA_LOGIN_NO_PERSONAL_CONTACT',$ratna);
}
if(Schema::hasTable('staff')){
 $staff=DB::table('staff')->whereRaw("LOWER(name) LIKE '%ratna%'")->get(['id','staff_code','role','status','attendance_enabled']);
 p('RATNA_STAFF_NO_CONTACT',$staff);
 p('STAFF_ROLE_ATTENDANCE',DB::table('staff')->select('role','status','attendance_enabled')->selectRaw('COUNT(*) as cnt')->groupBy('role','status','attendance_enabled')->get());
}
if(Schema::hasTable('staff_location_logs')){
 p('LOCATION_COVERAGE',DB::table('staff_location_logs as g')->join('staff as s','s.id','=','g.staff_id')
  ->select('s.role')->selectRaw('count(*) as samples, COUNT(DISTINCT g.staff_id) as people, MAX(g.received_at) as last_sample')->groupBy('s.role')->get());
}
$files=[
 'app/Models/ErpAuthUser.php'=>['function isStaff','function isAdmin','function isHr'],
 'app/Services/AttendanceService.php'=>['function checkIn','function checkOut','geofence','function today'],
 'app/Http/Controllers/Api/V1/AttendanceController.php'=>['private function staff','function checkIn','function checkOut'],
 'app/Http/Controllers/Api/V1/StaffLocationController.php'=>['function store','NO_DUTY_ATTENDANCE','private function'],
 'app/Http/Controllers/Api/V1/AuthController.php'=>['function login','admin_exempt','loginGeo'],
 'app/Http/Controllers/Web/StaffPortalController.php'=>['public function index','attendance','geofence','location'],
 'resources/views/staff/portal.blade.php'=>['Check-In','Check In','Geolocation','Location','GPS','attendance'],
 'app/Http/Controllers/Web/AdminSelfAttendanceController.php'=>['function checkIn','no GPS'],
 'app/Http/Controllers/Api/V1/OwnerSelfAttendanceInspectionController.php'=>['function myAttendance','can_check_in','REMOTE_EXEMPT'],
 'app/Http/Controllers/Api/V1/StaffDashboardController.php'=>['public function'],
 'app/Services/StaffDashboardService.php'=>['attendance','is_admin','is_hr'],
 'routes/api.php'=>['attendance','location','role'],
];
foreach($files as $file=>$keywords){
 $abs="$root/$file";if(!is_file($abs)){p('MISSING_FILE',$file);continue;}
 $lines=file($abs);$hits=[];
 foreach($lines as $i=>$line){
  foreach($keywords as $word)if(stripos($line,$word)!==false){
   $lo=max(0,$i-4);$hi=min(count($lines)-1,$i+13);
   $hits[]=[$i+1,implode('',array_slice($lines,$lo,$hi-$lo+1))];break;
  }
  if(count($hits)>=11)break;
 }
 p('SOURCE',[$file,count($lines),$hits]);
}
foreach([
 ['resources/views/staff/portal.blade.php',184,253],
 ['app/Http/Controllers/Web/StaffPortalController.php',1,113],
 ['app/Http/Controllers/Web/StaffWebAuthController.php',1,100],
 ['app/Services/GeoFenceService.php',1,190],
 ['app/Services/AttendanceService.php',16,115],
 ['app/Http/Controllers/Api/V1/StaffDashboardController.php',43,57],
 ['app/Http/Controllers/Api/V1/AuthController.php',16,96]
] as [$name,$lo,$hi]){
 $fn="$root/$name";if(!is_file($fn))continue;
 $lines=file($fn);$selected=[];
 for($i=$lo-1;$i<min($hi,count($lines));$i++)$selected[]=($i+1).':'.rtrim($lines[$i]);
 p('EXCERPT',[$name,$lo,$hi,$selected]);
}
if(Schema::hasTable('staff_devices')){
 $dev=DB::table('staff_devices as d')->join('staff as s','s.id','=','d.staff_id')
    ->select('s.role','d.active','d.location_permission','d.notification_permission','d.app_version')
    ->selectRaw('count(*) as devices,MAX(d.last_seen_at) as last_seen')
    ->groupBy('s.role','d.active','d.location_permission','d.notification_permission','d.app_version')->get();
 p('DEVICE_COVERAGE',$dev);
 $rdev=DB::table('staff_devices')->where('staff_id',6)
    ->get(['app_version','active','location_permission','notification_permission','last_seen_at']);
 p('RATNA_DEVICE_PERMISSIONS',$rdev);
}
if(Schema::hasTable('attendance')){
 $today=now('Asia/Kolkata')->toDateString();
 $rows=DB::table('staff as s')->leftJoin('attendance as a',function($join)use($today){
  $join->on('a.staff_id','=','s.id')->where('a.date','=',$today);
 })->select('s.id','s.role','s.attendance_enabled','a.check_in','a.check_out','a.status')
   ->orderBy('s.id')->get();
 p('TODAY_ATTENDANCE_WITHOUT_NAMES_OR_GPS',[$today,$rows]);
}
if(Schema::hasTable('staff_location_settings')){
 p('GEOFENCE_POLICY_NO_LOCATION_VALUES',DB::table('staff_location_settings')
  ->get(['tracking_enabled','geofence_checkin_enabled','radius_m','max_accuracy_m','sample_minutes']));
}
$summary=DB::table('staff as s')
 ->leftJoin('erp_auth_users as u','u.staff_id','=','s.id')
 ->select('s.id','s.staff_code','s.role','s.attendance_enabled','u.role as login_role')
 ->orderBy('s.id')->get();
foreach($summary as $row){
 $sid=(int)$row->id;
 $sample=DB::table('staff_location_logs')->where('staff_id',$sid)->orderByDesc('id')->first(['received_at','source','quality','app_version']);
 $count=DB::table('staff_location_logs')->where('staff_id',$sid)->count();
 $todayCount=DB::table('staff_location_logs')->where('staff_id',$sid)
   ->whereDate('received_at',now('Asia/Kolkata')->toDateString())->count();
 $device=DB::table('staff_devices')->where('staff_id',$sid)->orderByDesc('last_seen_at')
   ->first(['app_version','location_permission','notification_permission','last_seen_at']);
 p('STAFF_GPS_COVERAGE',[
    'staff_code'=>$row->staff_code,'staff_role'=>$row->role,'login_role'=>$row->login_role,
    'attendance_enabled'=>$row->attendance_enabled,'samples'=>$count,
    'today_samples'=>$todayCount,'latest_gps_at'=>$sample->received_at??null,
    'latest_gps_source'=>$sample->source??null,'latest_gps_quality'=>$sample->quality??null,
    'device_app_version'=>$device->app_version??null,
    'latest_registered_location_permission'=>$device->location_permission??null,
    'last_device_seen'=>$device->last_seen_at??null
 ]);
}
foreach(['app/Http/Controllers/Web/HrManagerController.php','app/Http/Controllers/Api/V1/HrApiController.php','app/Http/Controllers/Api/V1/HrOperationsApiController.php'] as $fn){
 $file="$root/$fn";if(!is_file($file))continue;
 $ls=file($file);$hits=[];
 foreach($ls as $i=>$line)if(preg_match('~check.?in|check.?out|geolocation|location|attendance~i',$line))
  $hits[]=[$i+1,trim(substr($line,0,190))];
 p('HR_APP_ATTENDANCE_REFERENCES',[basename($fn),array_slice($hits,0,35)]);
}
$dirs=[dirname($root).'/public_html',dirname($root).'/staff-app',dirname($root).'/android'];
foreach($dirs as $dir){
 if(!is_dir($dir))continue;
 $found=[];
 foreach(new DirectoryIterator($dir) as $item){
  if($item->isDot()||$item->isLink())continue;
  if(preg_match('~android|staff.?app|gradle|apk|mobile~i',$item->getFilename()))
    $found[]=[$item->getFilename(),$item->isDir()?'dir':'file'];
 }
 p('ANDROID_BUILD_SOURCE_DISCOVERY',[$dir,$found]);
}
echo "P59_READONLY_AUDIT_DONE\n";