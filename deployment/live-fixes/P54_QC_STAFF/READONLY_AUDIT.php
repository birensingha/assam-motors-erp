<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file($root.'/artisan')){echo "P54_INVALID_ROOT\n";exit(2);}
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;use Illuminate\Support\Facades\Route;
function showAudit(string $topic,mixed $val):void{echo 'P54_'.strtoupper($topic).'='.json_encode($val,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}
showAudit('mode','READ_ONLY_NO_PERSONAL_RECORDS_NO_MUTATIONS');
foreach(['workshop_qc_checks','job_cards','job_card_parts','job_card_labour','erp_auth_users','staff'] as $table){
 if(Schema::hasTable($table))showAudit('table_columns',[$table,Schema::getColumnListing($table)]);
}
foreach(Route::getRoutes() as $r){
 $uri=$r->uri();$name=(string)$r->getName();
 if(preg_match('~(staff|qc|job-cards.index|job-cards.show|lifecycle)~i',$uri.' '.$name)
   && preg_match('~^(erp|staff|api)/~',$uri)){
   showAudit('route',['uri'=>$uri,'name'=>$name,'methods'=>$r->methods(),'middleware'=>$r->gatherMiddleware(),'action'=>$r->getActionName()]);
 }
}
foreach([
 'app/Models/ErpAuthUser.php'=>['function isStaff','function isAdmin','getAuthIdentifier','function staff'],
 'resources/views/job-cards/index.blade.php'=>['JC Summary','job-cards.show','job-cards.edit','Inspection','Estimate','Purchase','actions'],
 'resources/views/job-cards/show.blade.php'=>['Next Step','Go to Step-wise Action Buttons','Edit OSL Purchases','workflow','qc'],
 'resources/views/job-cards/lifecycle-panel.blade.php'=>['QC_PENDING','Start Quality Check','QC Staff','Confirm QC PASS','Send to Rework'],
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php'=>['public function qcPending','public function qcResult','public function showInvoice','private function activeStaff'],
 'routes/web.php'=>['EnsureErpAdmin','prefix(\'staff','prefix(\'erp','QC'],
] as $p=>$patterns){
 $file="$root/$p";
 if(!is_file($file)){showAudit('missing_source',$p);continue;}
 $lines=file($file);
 $hits=[];
 foreach($lines as $i=>$line){
  foreach($patterns as $term)if(stripos($line,$term)!==false){
   $lo=max(0,$i-6);$hi=min(count($lines)-1,$i+11);
   $hits[]=[$i+1,implode("",array_slice($lines,$lo,$hi-$lo+1))];
   break;
  }
  if(count($hits)>=14)break;
 }
 showAudit('source',[$p,'lines'=>count($lines),'hits'=>$hits]);
}
$dirs=['resources/views/staff','resources/views/staff-portal','app/Http/Controllers/Web'];
foreach($dirs as $dir){
 $path="$root/$dir";if(!is_dir($path))continue;
 $names=[];
 foreach(new DirectoryIterator($path) as $item)if(!$item->isDot()&&preg_match('~(?:staff|rot|job|inspection|qc|dashboard|portal)~i',$item->getFilename())){
  $names[]=$item->getFilename();
 }
 showAudit('dir',[$dir,$names]);
}
echo "P54_AUDIT_FINISHED\n";
