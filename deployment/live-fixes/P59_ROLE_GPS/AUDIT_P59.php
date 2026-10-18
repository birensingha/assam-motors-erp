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
echo "P59_READONLY_AUDIT_DONE\n";