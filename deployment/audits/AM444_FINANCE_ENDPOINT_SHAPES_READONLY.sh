#!/usr/bin/env bash
# AM444: metadata-only, private source never emitted to GitHub logs.
set -Eeuo pipefail
cd "${1:?Required staging Laravel root}"
test -f artisan && test -f vendor/autoload.php
php <<'PHP'
<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
$wanted=[
 'api/v1/vendors',
 'api/v1/vendors/{vendor}/ledger',
 'api/v1/purchases/history',
 'api/v1/purchases/job-options',
];
foreach(Route::getRoutes() as $route){
 $uri=(string)$route->uri();
 if(!in_array($uri,$wanted,true) || !in_array('GET',$route->methods(),true))continue;
 $method=(string)$route->getActionName();
 $classMethod=explode('@',$method,2);
 $keys=[];
 if(count($classMethod)===2){
  try{
   $ref=new ReflectionMethod($classMethod[0],$classMethod[1]);
   $file=realpath((string)$ref->getFileName());
   $controllerRoot=realpath(getcwd().'/app/Http/Controllers');
   if($file!==false && $controllerRoot!==false &&
      str_starts_with($file,$controllerRoot.'/')){
    $all=file($file);
    $src=implode('',array_slice($all,$ref->getStartLine()-1,
           $ref->getEndLine()-$ref->getStartLine()+1));
    preg_match_all('/[\\x27\\x22]([A-Za-z_][A-Za-z0-9_]{0,39})[\\x27\\x22]\\s*=>/', $src, $match);
    foreach($match[1] as $key){
     if(preg_match('/^(id|name|vendor|vendors|ledger|data|items|rows|transactions|payments|purchases|purchase|history|total|amount|balance|outstanding|opening|closing|debit|credit|success|status|message|reference|date|pagination|next_cursor|.*_name|.*_no|.*_amount|.*_total)$/i',$key)){
       $keys[]=$key;
     }
    }
   }
  }catch(Throwable $e){$keys=[];}
 }
 $method=str_replace(chr(92),'/', $method);
 echo 'AM444_ENDPOINT='.$uri.';CONTROLLER='.substr($method,0,140).
      ';SAFE_RESPONSE_KEYS='.implode(',',array_slice(array_values(array_unique($keys)),0,35))."\n";
}
foreach(['workshop_vendors','workshop_purchases','workshop_expenses','workshop_payment_vouchers','purchases'] as $table){
 try{
  if(!Schema::hasTable($table))continue;
  $fields=array_values(array_filter(Schema::getColumnListing($table),
    static fn($key)=>preg_match('/^(id|.*vendor.*|.*supplier.*|.*name.*|.*code.*|.*type.*|.*balance.*|.*status.*|.*amount.*|.*total.*|.*gst.*|.*purchase.*|.*paid.*|.*reference.*|.*staff.*)$/i',$key)));
  echo 'AM444_TABLE_FIELDS_'.$table.'='.implode(',',array_slice($fields,0,80))."\n";
 }catch(Throwable $e){echo 'AM444_SCHEMA_UNAVAILABLE_'.$table."\n";}
}
echo "AM444_NO_FINANCE_VALUES_OR_MUTATIONS\n";
PHP
