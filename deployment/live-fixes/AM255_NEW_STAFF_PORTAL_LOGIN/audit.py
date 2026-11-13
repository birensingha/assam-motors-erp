#!/usr/bin/env python3
"""AM255 read-only Staff Portal login lifecycle and recent staff/account linking audit.
Never reads or prints passwords, password hashes, tokens, cookies, phone or email.
"""
from pathlib import Path
import sys,re,subprocess
if len(sys.argv)!=2:raise SystemExit('AM255_USAGE audit.py STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_AM255_NOT_STAGING')
files=[
 ('app/Http/Controllers/Web/StaffWebAuthController.php',[(1,300)]),
 ('app/Http/Middleware/EnsureStaffUser.php',[(1,180)]),
 ('app/Models/ErpAuthUser.php',[(1,200)]),
 ('config/auth.php',[(15,145)]),
 ('resources/views/staff/login.blade.php',[(1,100)]),
 ('app/Http/Controllers/Web/AdminStaffController.php',[(1,270)]),
 ('app/Http/Controllers/Web/AdminStaffPageController.php',[(1,250)]),
 ('app/Http/Controllers/Api/V1/StaffAuthController.php',[(1,160)]),
]
redact=re.compile(r'(?i)password\s*[=:]|secret\s*[=:]|api[_ -]?key|authorization|cookie|bearer |DB_PASS|APP_KEY|csrf_token\(|getPasswordHash|passwd')
print('AM255_SOURCE_AUDIT_BEGIN')
for rel,ranges in files:
 p=root/rel
 if not p.is_file():print('AM255_FILE_NOT_FOUND',rel);continue
 ls=p.read_text('utf8','replace').splitlines()
 print('AM255_SOURCE_FILE',rel,'LINES',len(ls))
 for start,stop in ranges:
  for i in range(start-1,min(len(ls),stop)):
   s=ls[i].rstrip()
   if redact.search(s):s='[REDACTED_SENSITIVE]'
   print('AM255_SOURCE',rel,i+1,s[:540])
# Identify actual staff creation handler locations without dumping all source.
for p in (root/'app/Http/Controllers').rglob('*.php'):
 if p.stat().st_size>260000:continue
 s=p.read_text('utf8','replace')
 if 'staff' not in p.name.lower() and 'employee' not in p.name.lower():continue
 if any(x in s for x in ['DB::table(\'staff\')->insert','Staff::create(','staff_web','ErpAuthUser::create']):
  ls=s.splitlines()
  matched=[i for i,line in enumerate(ls) if any(x in line for x in
   ['DB::table(\'staff\')->insert','Staff::create(','staff_web','ErpAuthUser::create','function store','function create','function login'])]
  print('AM255_STAFF_CREATE_CANDIDATE',p.relative_to(root),'LINES',len(ls))
  for i in matched[:17]:
   for j in range(max(0,i-3),min(len(ls),i+10)):
    v=ls[j].rstrip()
    if redact.search(v):v='[REDACTED_SENSITIVE]'
    print('AM255_CREATE_SOURCE',p.relative_to(root),j+1,v[:490])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
foreach(['staff','erp_auth_users','users'] as $table) {
 if(!Schema::hasTable($table)){echo 'AM255_TABLE_MISSING '.$table."\n";continue;}
 $cols=Schema::getColumnListing($table);
 $allowed=['id','name','staff_id','staff_code','role','active','status','is_active','user_type','deleted_at','created_at','updated_at'];
 echo 'AM255_TABLE_IDENTITY_FIELDS '.$table.' '.json_encode(array_values(array_intersect($allowed,$cols)))."\n";
}
if(Schema::hasTable('staff')&&Schema::hasTable('erp_auth_users')){
 $s=Schema::getColumnListing('staff');$u=Schema::getColumnListing('erp_auth_users');
 if(in_array('created_at',$s,true)) {
  $recent=DB::table('staff')->orderByDesc('id')->limit(10)->get(array_values(array_intersect(['id','staff_code','status','active','created_at'],$s)));
  foreach($recent as $staff) {
   $ids=DB::table('erp_auth_users')->where('staff_id',$staff->id)->get(array_values(array_intersect(['id','role','staff_id','active','created_at'],$u)));
   echo 'AM255_RECENT_STAFF_ACCOUNT_LINK='.json_encode(['staff'=>$staff,'linked_users'=>$ids,'count'=>$ids->count()])."\n";
  }
 }
}
foreach(Route::getRoutes() as $route) {
 $name=(string)($route->getName()??'');$url=(string)$route->uri();$a=(string)$route->getActionName();
 if(str_contains($url,'staff/login') || str_contains($name,'staff.login') || str_contains($a,'StaffWebAuthController'))
  echo 'AM255_LOGIN_ROUTE='.json_encode(['uri'=>$url,'name'=>$name,'action'=>$a,'method'=>$route->methods(),'middleware'=>$route->gatherMiddleware()])."\n";
}
echo 'AM255_STAFF_IDENTITY_SCHEMA_READ_ONLY_DONE'."\n";"""
p=subprocess.run(['php','-r',php],cwd=root,text=True,capture_output=True,timeout=50)
if p.returncode:raise SystemExit('STOP_AM255_SCHEMA_AUDIT '+(p.stderr or p.stdout)[-600:])
print(p.stdout[:7500])

# Staff creation, API login and Check-In route/controller source: never print request data.
print('AM255_ATTENDANCE_AND_CREATE_SOURCE_START')
php2=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;use Illuminate\Support\Facades\Route;
foreach(['staff','erp_auth_users','staff_attendances','staff_attendance','attendance','attendances'] as $table) {
 if(!Schema::hasTable($table))continue;
 foreach(DB::select('SELECT COLUMN_NAME AS c,DATA_TYPE AS d,IS_NULLABLE AS nullable,COLUMN_DEFAULT AS def FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table]) as $col){
   if(in_array($table,['staff','erp_auth_users']) && !in_array($col->c,['id','name','email','staff_code','staff_id','role','status','active','phone','mobile','created_at','updated_at','department','designation','password','password_hash'],true)) continue;
   echo 'AM255_COLUMN '.$table.' '.$col->c.' '.$col->d.' NULL='.$col->nullable."\n";
 }
}
foreach(Route::getRoutes() as $r){
 $uri=(string)$r->uri();$name=(string)($r->getName()??'');$action=(string)$r->getActionName();
 if(preg_match('/staff.*(store|create|update|new)|attendance|check.in|check.out|mobile.*(login|auth)/i',$uri.' '.$name.' '.$action)){
  echo 'AM255_FUNCTION_ROUTE='.json_encode(['uri'=>$uri,'name'=>$name,'methods'=>$r->methods(),'action'=>$action,'middleware'=>$r->gatherMiddleware()])."\n";
 }
}
echo "AM255_ATTENDANCE_SCHEMA_ROUTES_READONLY_DONE\n";"""
p2=subprocess.run(['php','-r',php2],cwd=root,text=True,capture_output=True,timeout=45)
if p2.returncode:raise SystemExit('STOP_AM255_ATTENDANCE_ROUTE_AUDIT '+(p2.stderr+p2.stdout)[-800:])
print(p2.stdout[:24500])
for name in ('app/Http/Controllers/Web/StaffMasterController.php',
 'app/Http/Controllers/Web/StaffManagementController.php',
 'app/Http/Controllers/Web/AdminStaffMasterController.php',
 'app/Http/Controllers/Web/AdminConfigurationController.php',
 'app/Http/Controllers/Web/StaffPortalController.php',
 'app/Http/Controllers/Api/V1/StaffAuthController.php',
 'app/Http/Controllers/Api/V1/StaffAttendanceController.php',
 'app/Http/Controllers/Api/V1/StaffApiController.php'):
 p=root/name
 if not p.is_file():continue
 ls=p.read_text('utf8','replace').splitlines()
 print('AM255_FUNCTION_SOURCE_FILE',name,'LINES',len(ls))
 pat=re.compile(r'function\s+(store|create|update|login|checkIn|checkOut|attendance)|erp_auth_users|Staff::create|DB::table\([\'"]staff|attendance|check.in',re.I)
 picks=set()
 for i,line in enumerate(ls):
  if pat.search(line):picks.update(range(max(0,i-5),min(len(ls),i+23)))
 for i in sorted(picks)[:260]:
  line=ls[i].strip()
  if redact.search(line):line='[REDACTED_SENSITIVE]'
  print('AM255_FUNCTION_SOURCE',name,i+1,line[:560])
print('AM255_ATTENDANCE_AND_CREATE_SOURCE_DONE')


print('AM255_AUTH_AND_CHECKIN_IMPLEMENTATIONS_START')
wanted=[
('app/Services/AttendanceService.php',range(1,310)),
('app/Services/MandatoryNotificationService.php',range(1,230)),
('app/Http/Controllers/Api/V1/AttendanceController.php',range(1,210)),
('app/Http/Controllers/Api/V1/AuthController.php',range(1,180)),
('app/Http/Controllers/Api/V1/StaffAuthApiController.php',range(1,180)),
('app/Http/Controllers/Web/AdminNativeModuleController.php',range(1,200)),
('app/Models/Staff.php',range(1,150)),
]
for rel,rr in wanted:
 p=root/rel
 if not p.is_file():print('AM255_IMPLEMENTATION_MISSING',rel);continue
 print('AM255_IMPLEMENTATION_FILE',rel,p.stat().st_size)
 lines=p.read_text('utf8','replace').splitlines()
 for i in rr:
  if i>len(lines):break
  l=lines[i-1].rstrip()
  if redact.search(l):l='[REDACTED_SENSITIVE]'
  print('AM255_IMPLEMENTATION_SOURCE',rel,i,l[:520])
print('AM255_AUTH_CHECKIN_READONLY_DONE')


print('AM255_STAFF_CREATION_AND_HASH_METADATA_START')
php3=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Route;use Illuminate\Support\Facades\Hash;
foreach(Route::getRoutes() as $r){
 $u=(string)$r->uri();$n=(string)($r->getName()??'');$ac=(string)$r->getActionName();
 if(str_contains($u,'staff')||str_contains($ac,'Staff')||str_contains($n,'staff'))
  if(in_array('POST',$r->methods(),true) || in_array('PUT',$r->methods(),true))
    echo 'AM255_STAFF_WRITE_ROUTE='.json_encode(['uri'=>$u,'name'=>$n,'action'=>$ac,'methods'=>$r->methods()])."\n";
}
foreach(['staff','erp_auth_users'] as $t){
 foreach(DB::select('SELECT INDEX_NAME AS n, COLUMN_NAME AS c, NON_UNIQUE AS non_unique FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME IN ("email","staff_id")',[$t]) as $x)
  echo 'AM255_LOGIN_IDENTITY_INDEX='.$t.':'.$x->n.':'.$x->c.':'.$x->non_unique."\n";
}
$s=DB::table('staff')->where('id',9)->first();
if($s){
 $h=(string)$s->password;
 $algo=(password_get_info($h)['algoName']??'unknown');
 echo 'AM255_NEW_STAFF_PASSWORD_HASH_TYPE='.$algo.' (no hash/secret printed)'."\n";
 $email=(string)($s->email??'');
 $email_count=DB::table('staff')->whereRaw('LOWER(email)=?',[strtolower($email)])->count();
 $erp_email_count=DB::table('erp_auth_users')->whereRaw('LOWER(email)=?',[strtolower($email)])->count();
 echo 'AM255_NEW_STAFF_EMAIL_UNIQUENESS='.json_encode(['staff_match_count'=>$email_count,'erp_account_match_count'=>$erp_email_count])."\n";
}
"""
p3=subprocess.run(['php','-r',php3],cwd=root,text=True,capture_output=True,timeout=35)
if p3.returncode:raise SystemExit('STOP_AM255_STAFF_CREATE_AUDIT '+(p3.stderr+p3.stdout)[-450:])
print(p3.stdout[:13000])
for rel in ['app/Http/Controllers/Web/AdminNativeModuleController.php','app/Http/Controllers/Web/AdminWorkshopControlPageController.php']:
 p=root/rel
 if not p.is_file():continue
 lines=p.read_text('utf8','replace').splitlines()
 hits=[i for i,l in enumerate(lines) if re.search(r'function\s+(createStaff|storeStaff|updateStaff|staffStore|newStaff)\s*\(|DB::table\(\s*[\'"]staff[\'"]\s*\)|Staff::create|Staff::query\(',l)]
 print('AM255_STAFF_HANDLER_FILE',rel,'hits',len(hits))
 selects=set()
 for i in hits[:20]:selects.update(range(max(0,i-7),min(len(lines),i+45)))
 for i in sorted(selects)[:240]:
  line=lines[i].strip()
  if redact.search(line):line='[REDACTED_SENSITIVE]'
  print('AM255_STAFF_HANDLER_CODE',rel,i+1,line[:480])
print('AM255_STAFF_CREATION_AND_HASH_METADATA_END')

print('AM255_NO_AUTH_ATTEMPTS_NO_PASSWORDS_NO_MUTATIONS')
