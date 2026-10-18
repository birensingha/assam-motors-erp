<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
function out(string $k,mixed $v):void{echo 'P61_'.$k.'='.json_encode($v,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
out('MODE','READONLY_NO_PRECISE_GPS_VALUES_NO_WRITES');
$cols=DB::select("SELECT COLUMN_NAME,IS_NULLABLE,DATA_TYPE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='staff_location_logs' AND COLUMN_NAME IN ('attendance_id','staff_id','source','recorded_at')");
out('GPS_COLUMN_NULLABILITY',$cols);
foreach(['app/Http/Controllers/Api/V1/StaffLocationController.php','app/Support/HrAccess.php','app/Http/Controllers/Api/V1/AdminMobileController.php'] as $f){
 $fn="$root/$f";if(!is_file($fn)){out('MISSING',$f);continue;}
 $rows=file($fn);$match=[];
 foreach($rows as $i=>$line)if(preg_match('/function permitted|function locations|isAdmin\(|attendance->id|No active duty attendance|STALE|latest|gps|location|quality|consent/i',$line))$match[]=($i+1).':'.trim(substr($line,0,170));
 out('SOURCE_SUMMARY',[$f,count($rows),array_slice($match,0,100)]);
}
$today=now('Asia/Kolkata')->toDateString();
$accounts=DB::table('erp_auth_users as u')
 ->join('staff as s','s.id','=','u.staff_id')
 ->whereIn('u.role',['admin','supervisor'])
 ->select('s.staff_code','u.role','u.active','s.status','s.attendance_enabled')->get();
out('ADMIN_LINKED_STATUS',$accounts);
$roles=DB::table('staff')->select('role')->selectRaw('count(*) cnt')->groupBy('role')->get();
out('ROLES',$roles);
out('GPS_ROW_COUNTS_BY_DATE',DB::table('staff_location_logs')->selectRaw('DATE(received_at) as day,COUNT(*) as samples')->groupByRaw('DATE(received_at)')->orderByDesc('day')->limit(8)->get());
out('TODAY',$today);
echo "P61_READONLY_COMPLETE\n";