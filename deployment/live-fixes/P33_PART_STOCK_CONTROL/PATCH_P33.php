<?php
declare(strict_types=1);
/* P33_JOB_PART_REQUISITION_ISSUE_FITTED: guarded UI/routes/controller. No business POST in CI. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P33_USAGE_ERROR\n");exit(2);}
$edit=['routes/web.php',
 'resources/views/job-cards/show.blade.php',
 'resources/views/job-cards/lifecycle-panel.blade.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php'
];
$payload=[
 'app/Http/Controllers/Web/AdminJobCardPartIssueController.php'=>__DIR__.'/payload/AdminJobCardPartIssueController.php',
 'resources/views/job-cards/part-stock-control.blade.php'=>__DIR__.'/payload/part-stock-control.blade.php',
];
$old=[];
foreach($edit as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P33_NO_GO_MISSING ".$p."\n");exit(3);}
 $old[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($old[$p],'P33_JOB_PART_REQUISITION_ISSUE_FITTED')){fwrite(STDERR,"P33_ALREADY_INSTALLED_NOOP\n");exit(0);}
}
foreach($payload as $p=>$source){if(!is_file($source)||file_exists($root.'/'.$p)){fwrite(STDERR,"P33_NO_GO_PAYLOAD_OR_DEST ".$p."\n");exit(4);}}
function once33(string $txt,string $needle,string $value,string $name):string {
 $n=substr_count($txt,$needle);
 if($n!==1)throw new RuntimeException('P33_NO_GO_ANCHOR_'.$name.'_'.$n);
 return str_replace($needle,$value,$txt);
}
try{
 $route=$old[$edit[0]];
 $anchor="Route::post('/job-cards/{job}/parts/direct', [AdminJobCardPageController::class,'addPartDirect'])->whereNumber('job')->name('erp.job-cards.parts.direct');";
 $routes=<<<'ROUTES'

    // P33_JOB_PART_REQUISITION_ISSUE_FITTED: audited Store issue, independent of labour ROT.
    Route::post('/job-cards/{job}/parts/{part}/requisition', [\App\Http\Controllers\Web\AdminJobCardPartIssueController::class,'requisition'])
        ->whereNumber('job')->whereNumber('part')->name('erp.job-cards.parts.requisition');
    Route::post('/job-cards/{job}/parts/{part}/issue', [\App\Http\Controllers\Web\AdminJobCardPartIssueController::class,'issue'])
        ->whereNumber('job')->whereNumber('part')->name('erp.job-cards.parts.issue');
    Route::post('/job-cards/{job}/parts/{part}/fitted', [\App\Http\Controllers\Web\AdminJobCardPartIssueController::class,'fitted'])
        ->whereNumber('job')->whereNumber('part')->name('erp.job-cards.parts.fitted');
ROUTES;
 $new[$edit[0]]=once33($route,$anchor,$anchor.$routes,'route');

 $view=$old[$edit[1]];
 $view=once33($view,
 "@php(\$fix37Issued=!in_array(strtolower(trim((string)(\$p->issue_status ?? 'planned'))),['','planned','pending','not issued','not_issued'],true))",
 "@php(\$fix37Issued=!in_array(strtolower(trim((string)(\$p->issue_status ?? 'planned'))),['','planned','pending','not issued','not_issued','requisitioned'],true))",
 'delete_button_requisition_not_issued');
 $start=strpos($view,'@php($fix37Issued=');
 if($start===false)throw new RuntimeException('Parts table anchor absent');
 $actionStart=strpos($view,'@if(!in_array(strtolower((string)$job->status)', $start);
 if($actionStart===false||$actionStart>$start+2600)throw new RuntimeException('Parts action segment absent');
 $detailStart=strpos($view,'<details class="action-pop">',$actionStart);
 if($detailStart===false||$detailStart>$actionStart+380)throw new RuntimeException('Parts action details unexpected');
 $view=substr_replace($view,"{{-- P33_JOB_PART_REQUISITION_ISSUE_FITTED --}}\n          @include('job-cards.part-stock-control')\n          ",$detailStart,0);
 $new[$edit[1]]=$view;

 $life=$old[$edit[2]];
 $life=once33($life,
 "IN ('','planned','pending','not issued','not_issued','issued')",
 "IN ('','planned','pending','not issued','not_issued','requisitioned','issued')",
 'pending_count_requisition');
 $new[$edit[2]]=$life;

 $page=$old[$edit[3]];
 $page=once33($page,
 "\$planned = in_array(\$issueStatus,['','planned','pending','not issued','not_issued'],true);",
 "\$planned = in_array(\$issueStatus,['','planned','pending','not issued','not_issued','requisitioned'],true); // P33_JOB_PART_REQUISITION_ISSUE_FITTED",
 'requisition_delete_no_false_stock_return');
 $new[$edit[3]]=$page;

 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($new as $p=>$code){
   if(str_ends_with($p,'.blade.php'))$code=app('blade.compiler')->compileString($code);
   token_get_all($code,TOKEN_PARSE);
 }
 foreach($payload as $p=>$src){
   $code=(string)file_get_contents($src);
   if(str_ends_with($p,'.blade.php'))$code=app('blade.compiler')->compileString($code);
   token_get_all($code,TOKEN_PARSE);
 }
 foreach(['job_cards','job_card_parts','parts_master','inventory_movements','job_card_change_audits'] as $table){
    if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('Missing stock/audit table '.$table);
 }
 echo "P33_PART_REQUISITION_ISSUE_FITTED_BLADE_PHP_PASS\n";
 echo "P33_ADMIN_ROUTE_AND_STOCK_AUDIT_SCHEMA_PASS\n";
 echo "P33_REQ_AND_ISSUED_STILL_PENDING_WORK_COMPLETE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,'P33_NO_GO_PRECHECK '.$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P33_DRY_RUN_NO_STOCK_AUDIT_OR_JOB_CARD_WRITES\n";exit(0);}
$bak=$root.'/storage/app/p33_parts_issue_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($old as $p=>$contents){
 $target=$bak.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$target)||hash_file('sha256',$target)!==hash('sha256',$contents)){
  fwrite(STDERR,"P33_NO_GO_BACKUP_VERIFY\n");exit(6);
 }
}
$written=[];
try{
 foreach($new as $p=>$code){
  $dst=$root.'/'.$p;
  if(hash_file('sha256',$dst)!==hash('sha256',$old[$p]))throw new RuntimeException('Concurrent modification '.$p);
  $tmp=tempnam(dirname($dst),'.p33_');
  if(!$tmp||file_put_contents($tmp,$code,LOCK_EX)!==strlen($code))throw new RuntimeException('Write failed '.$p);
  chmod($tmp,fileperms($dst)&0777);
  if(!rename($tmp,$dst))throw new RuntimeException('Atomic rename failed '.$p);
  $written[]=$p;
 }
 foreach($payload as $p=>$src){
  if(!copy($src,$root.'/'.$p))throw new RuntimeException('Copy payload '.$p);
  $written[]=$p;
 }
 echo 'P33_APPLIED_BACKUP='.$bak."\n";
 echo "P33_ONLY_SOURCE_FILES_CHANGED_ZERO_PARTS_ISSUED\n";
}catch(Throwable $e){
 foreach(array_reverse($written) as $p){
   if(isset($payload[$p]))@unlink($root.'/'.$p);
   else copy($bak.'/'.str_replace('/','__',$p),$root.'/'.$p);
 }
 fwrite(STDERR,'P33_AUTO_SOURCE_ROLLBACK '.$e->getMessage()."\n");exit(7);
}
