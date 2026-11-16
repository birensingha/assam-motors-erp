#!/usr/bin/env python3
"""AM258 read-only source/route inspection for Estimate, Purchase Create/Edit and Counter Sale.
No DB reads, credentials, financial data or mutations.
"""
from pathlib import Path
import sys,re,subprocess
if len(sys.argv)!=2:raise SystemExit('AM258_USAGE audit.py STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_AM258_NOT_STAGING')
segments={
'public/js/estimate-entry.js':[(1,96),(155,305)],
'public/js/purchase-entry.js':[(1,126),(190,315),(466,622)],
'public/js/purchase-edit.js':[(1,130),(215,458)],
'resources/views/counter-sales/create.blade.php':[(74,160)],
'resources/views/estimates/create.blade.php':[(70,115),(325,400)],
'resources/views/purchases/create.blade.php':[(400,580)],
'resources/views/purchases/edit.blade.php':[(470,528)]
}
for rel,lspec in segments.items():
 p=root/rel
 if not p.is_file():print('AM258_FILE_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM258_SOURCE_FILE',rel,'LINES',len(lines))
 for start,end in lspec:
  for idx in range(start-1,min(len(lines),end)):
   val=lines[idx].rstrip()
   if re.search(r'(password|secret|DB_PASSWORD|API_KEY|csrf_token\(|bearer\s|access_token)',val,re.I):val='[REDACTED]'
   print('AM258_SOURCE',rel,idx+1,val[:530])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(\Illuminate\Support\Facades\Route::getRoutes() as $r){
 $n=(string)$r->getName();$u=(string)$r->uri();
 if((str_contains($u,'estimate')||str_contains($u,'purchase')||str_contains($u,'counter-sale'))
    && in_array('POST',$r->methods(),true))
   echo 'AM258_WRITE_ROUTE='.json_encode(['uri'=>$u,'name'=>$n,'action'=>$r->getActionName()])."\n";
}
echo 'AM258_ROUTES_READ_ONLY_DONE'."\n";"""
p=subprocess.run(['php','-r',php],cwd=root,text=True,capture_output=True,timeout=45)
if p.returncode:raise SystemExit('STOP_AM258_ROUTE_AUDIT '+(p.stdout+p.stderr)[-650:])
print(p.stdout)
print('AM258_READONLY_AUDIT_COMPLETE')
