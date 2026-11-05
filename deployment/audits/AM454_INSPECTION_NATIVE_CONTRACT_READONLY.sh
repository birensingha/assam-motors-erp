#!/usr/bin/env bash
# AM454 native Staff Inspection controller contract metadata; no POST or DB rows.
set -Eeuo pipefail
cd "${1:?Existing staging Laravel root required}"
test -f artisan && test -f vendor/autoload.php
php <<'PHP'
<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

$paths=[
 'api/v1/staff/inspections', 'api/v1/staff/inspections/{job}',
 'api/v1/admin/mobile/manager-inspections',
 'api/v1/admin/mobile/manager-inspections/{job}',
 'api/v1/admin/mobile/manager-inspections/{job}/assign',
 'api/v1/admin/mobile/manager-inspections/{job}/complete',
 'api/v1/admin/mobile/job-cards/{job}/inspection/assign'
];
foreach(Route::getRoutes() as $route){
 $uri=(string)$route->uri();
 if(!in_array($uri,$paths,true))continue;
 $action=(string)$route->getActionName();
 $safeKeys=[];
 try{
  $parts=explode('@',$action,2);
  if(count($parts)===2){
   $ref=new ReflectionMethod($parts[0],$parts[1]);
   $file=realpath((string)$ref->getFileName());
   $root=realpath(getcwd().'/app/Http/Controllers');
   if($file && $root && str_starts_with($file,$root.'/')){
    $src=implode('',array_slice(file($file),$ref->getStartLine()-1,
      $ref->getEndLine()-$ref->getStartLine()+1));
    preg_match_all('/[\\x27\\x22]([A-Za-z_][A-Za-z0-9_]{0,39})[\\x27\\x22]\\s*=>/', $src, $matches);
    foreach($matches[1] as $key){
     if(preg_match('/^(ok|success|message|job|items|inspections|assignments|rows|data|inspection_status|staff_id|inspector_id|status|checks|errors|points|checklist|inspection_points|defect_status|defects|id|assigned_to|job_card_id)$/i',$key))
       $safeKeys[]=$key;
    }
   }
  }
 }catch(Throwable $e){$safeKeys=[];}
 $action=str_replace(chr(92),'/', $action);
 foreach($route->methods() as $verb){
  if($verb==='HEAD')continue;
  echo 'AM454_METHOD='.$verb.' '.$uri.';CONTROLLER='.substr($action,0,130).
     ';SAFE_RESPONSE_KEYS='.implode(',',array_slice(array_values(array_unique($safeKeys)),0,30))."\n";
 }
}
foreach(['vehicle_inspections','job_card_inspections','inspection_reports',
         'inspection_items','workshop_inspections','job_card_inspection_points',
         'job_cards','staff'] as $table){
 try{
  if(!Schema::hasTable($table))continue;
  $cols=array_values(array_filter(Schema::getColumnListing($table),
    static fn($field)=>preg_match('/^(id|.*inspection.*|.*inspector.*|.*staff.*|.*status.*|.*job_card.*|.*point.*|.*defect.*|.*comment.*|.*remark.*|.*condition.*|.*note.*)$/i',$field)));
  echo 'AM454_INSPECTION_TABLE='.$table.';FIELDS='.implode(',',array_slice($cols,0,65))."\n";
 }catch(Throwable $e){echo 'AM454_SCHEMA_UNAVAILABLE_'.$table."\n";}
}
echo "AM454_INSPECTION_METADATA_NO_POST_NO_ROWS\n";
PHP
