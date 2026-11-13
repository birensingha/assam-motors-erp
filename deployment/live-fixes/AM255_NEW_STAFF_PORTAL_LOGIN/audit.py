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
print('AM255_NO_AUTH_ATTEMPTS_NO_PASSWORDS_NO_MUTATIONS')
