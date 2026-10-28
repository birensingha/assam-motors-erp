#!/usr/bin/env python3
"""AM179: READ-ONLY public Hiring to ERP integration + admin route verification.

No DB writes, form submissions, applicant data, secrets or CV contents.
Run: python3 verify.py LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import re,subprocess,sys,tempfile
if len(sys.argv)!=2:raise SystemExit("Usage: verify.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("AM179_STOP_NOT_STAGING_LARAVEL")
site=root.parent/"public_html"
handler=site/"api/job-apply.php"
erpview=root/"resources/views/admin/native/job-applications.blade.php"
controller=root/"app/Http/Controllers/Web/AdminNativeModuleController.php"
if not all(p.is_file() for p in (handler,erpview,controller)):
    raise SystemExit("AM179_STOP_REQUIRED_FILE_MISSING")
text=handler.read_text(encoding="utf8",errors="replace")
marker="AM176_PUBLIC_HIRING_DIRECT_NEW_ERP_DB"
print("AM179_HIRING_ROUTE_VERIFY_READ_ONLY")
print("LIVE_PUBLIC_API_BRIDGE_ACTIVE",marker in text and "$db = am176ErpHiringPdo();" in text)
print("PUBLIC_OLD_CONFIG_INSERT_ACTIVE","$db = getDB();" in text)
print("NEW_ERP_VIEW_EXISTS",erpview.is_file())
print("NEW_ERP_CONTROLLER_HAS_JOB_METHODS",all(x in controller.read_text(encoding="utf8",errors="replace")
 for x in ("function jobApplications","function updateJobApplication","function deleteJobApplication")))
# Dynamic routes matter: routes can be registered in files beyond routes/web.php.
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
 $routes=$app->make('router')->getRoutes();
 $count=0;$matches=0;
 foreach($routes as $route){
  $uri=$route->uri();
  $name=(string)$route->getName();
  $action=(string)$route->getActionName();
  $m=implode(',', $route->methods());
  if(str_starts_with($uri,'erp/'))$count++;
  if(preg_match('/job.?applications|hiring|recruit/i',$uri.' '.$name.' '.$action)){
   $matches++;
   echo 'JOB_ROUTE '.json_encode(['uri'=>$uri,'name'=>$name,
     'methods'=>$m,'action'=>$action,'middleware'=>$route->gatherMiddleware()],
     JSON_UNESCAPED_SLASHES)."\n";
  }
  if(preg_match('#^erp/(attendance|vendors|customers|staff)(?:/|$)#',$uri)
       && in_array('GET',$route->methods())){
   echo 'EXAMPLE_ERP_ROUTE '.json_encode(['uri'=>$uri,'name'=>$name,
     'methods'=>$m,'action'=>$action,'middleware'=>$route->gatherMiddleware()],
     JSON_UNESCAPED_SLASHES)."\n";
  }
 }
 echo 'ERP_ROUTE_TOTAL '.$count."\n";
 echo 'JOB_ROUTE_TOTAL '.$matches."\n";
 $need=['erp.job-applications.index','erp.job-applications.status',
        'erp.job-applications.delete'];
 foreach($need as $name){
  echo 'REQUIRED_ROUTE_NAME '.$name.' '.($routes->getByName($name) ? 'PRESENT' : 'MISSING')."\n";
 }
}catch(Throwable $e){
 fwrite(STDERR,'ROUTE_PROBE_ERROR '.get_class($e)."\n");exit(1);
}
"""
with tempfile.TemporaryDirectory(prefix="am179_") as d:
    f=Path(d)/"check-routes.php"
    f.write_text(php,encoding="utf8")
    p=subprocess.run(["php",str(f),str(root)],capture_output=True,text=True,timeout=30)
    if p.returncode:
        print("AM179_ROUTE_PROBE_FAILED",p.stderr.strip()[-220:])
        sys.exit(2)
    print(p.stdout.strip())
print("AM179_READ_ONLY_COMPLETE")
