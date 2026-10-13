<?php
declare(strict_types=1);
/** P12 protected JC Reversal Centre staging installer; never changes financial/JC rows on install. */
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: APPLY_P12.php check|apply\n");exit(2);}
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
$payload=__DIR__.'/payload';
$controller=$root.'/app/Http/Controllers/Web/AdminJobCardUnwindController.php';
$view=$root.'/resources/views/job-cards/unwind.blade.php';
if(realpath($root)!==$root||!is_file($root.'/artisan')||!is_file($payload.'/AdminJobCardUnwindController.php')||!is_file($payload.'/unwind.blade.php')){fwrite(STDERR,"NO-GO canonical staging/payload not available\n");exit(2);}
if(is_file($controller)||is_file($view)){fwrite(STDERR,"NO-GO existing files require manual review; no overwrite\n");exit(2);}
function oneP12(string $src,string $old,string $new,string $label):string {
 $n=substr_count($src,$old);if($n!==1)throw new RuntimeException("NO-GO ".$label." occurrence=".$n);return str_replace($old,$new,$src);
}
try {
 $routes=$root.'/routes/web.php';$show=$root.'/resources/views/job-cards/show.blade.php';
 $beforeRoutes=(string)file_get_contents($routes);$beforeShow=(string)file_get_contents($show);
 $routeAnchor="Route::get('/job-cards/{job}', [AdminJobCardPageController::class,'show'])->whereNumber('job')->name('erp.job-cards.show');";
 $block=<<<'ROUTES'

    // JC_UNWIND_P12: Admin-only audited reversal and archive control.
    Route::get('/job-cards/{job}/reversal', [\App\Http\Controllers\Web\AdminJobCardUnwindController::class,'show'])->whereNumber('job')->name('erp.job-cards.reversal');
    Route::delete('/job-cards/{job}/reversal/labour/{labour}', [\App\Http\Controllers\Web\AdminJobCardUnwindController::class,'removeLabour'])->whereNumber('job')->whereNumber('labour')->name('erp.job-cards.reversal.labour');
    Route::post('/job-cards/{job}/reversal/request', [\App\Http\Controllers\Web\AdminJobCardUnwindController::class,'requestReview'])->whereNumber('job')->name('erp.job-cards.reversal.request');
    Route::post('/job-cards/{job}/reversal/cancel', [\App\Http\Controllers\Web\AdminJobCardUnwindController::class,'cancel'])->whereNumber('job')->name('erp.job-cards.reversal.cancel');
ROUTES;
 $newRoutes=oneP12($beforeRoutes,$routeAnchor,$routeAnchor."\n".$block,'routes');
 $showAnchor="@include('job-cards.lifecycle-panel')";
 $showLink=<<<'BLADE'

<div class="card section-gap" style="border-left:5px solid #ef9b0a;padding:15px">
  <div class="actions wrap" style="justify-content:space-between;gap:10px">
    <div><strong>↶ Job Card Reversal / Cancel Centre</strong>
      <p class="muted" style="margin:3px 0 0">Labour remove · Part stock return · Estimate review · Cancel/archive with audit</p>
    </div>
    <a class="btn primary" href="{{ route('erp.job-cards.reversal',$job->id) }}">Open Reversal Centre →</a>
  </div>
</div>
BLADE;
 $newShow=oneP12($beforeShow,$showAnchor,$showAnchor."\n".$showLink,'JC Summary link');
 token_get_all($newRoutes,TOKEN_PARSE);token_get_all((string)file_get_contents($payload.'/AdminJobCardUnwindController.php'),TOKEN_PARSE);
 foreach([$routes=>$beforeRoutes,$show=>$beforeShow] as $path=>$old)if(!is_file($path)||is_link($path))throw new RuntimeException("NO-GO unsafe source ".$path);
 echo "P12_PRECHECK routes old_sha=".hash('sha256',$beforeRoutes)." next_sha=".hash('sha256',$newRoutes)."\n";
 echo "P12_PRECHECK view old_sha=".hash('sha256',$beforeShow)." next_sha=".hash('sha256',$newShow)."\n";
} catch(Throwable $e){fwrite(STDERR,"NO-GO ".$e->getMessage()."\n");exit(2);}
if($mode==='check'){echo "P12_DRY_RUN_PASS: no files or Job Cards changed\n";exit(0);}
$backup=$root.'/storage/app/jc_unwind_p12_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO backup failed\n");exit(2);}
if(!copy($routes,$backup.'/web.php')||!copy($show,$backup.'/show.blade.php')||hash_file('sha256',$backup.'/web.php')!==hash('sha256',$beforeRoutes)||hash_file('sha256',$backup.'/show.blade.php')!==hash('sha256',$beforeShow)){
 fwrite(STDERR,"NO-GO backup sha verification failed\n");exit(2);
}
$changed=[];
try{
 foreach([[$routes,$beforeRoutes,$newRoutes],[$show,$beforeShow,$newShow]] as [$path,$old,$new]){
  if(hash_file('sha256',$path)!==hash('sha256',$old))throw new RuntimeException("Concurrent source edit ".$path);
  $tmp=tempnam(dirname($path),'.unwind_');
  if(!$tmp||file_put_contents($tmp,$new,LOCK_EX)!==strlen($new))throw new RuntimeException('Temp write failed');
  chmod($tmp,fileperms($path)&0777);
  if(!rename($tmp,$path))throw new RuntimeException('Replace failed');
  $changed[]=$path;
  if(hash_file('sha256',$path)!==hash('sha256',$new))throw new RuntimeException('Result hash mismatch');
 }
 if(!copy($payload.'/AdminJobCardUnwindController.php',$controller))throw new RuntimeException('Controller copy failed');
 if(!copy($payload.'/unwind.blade.php',$view))throw new RuntimeException('View copy failed');
 if(hash_file('sha256',$controller)!==hash_file('sha256',$payload.'/AdminJobCardUnwindController.php')||
 hash_file('sha256',$view)!==hash_file('sha256',$payload.'/unwind.blade.php'))throw new RuntimeException('Payload SHA mismatch');
} catch(Throwable $e){
 @unlink($controller);@unlink($view);
 foreach($changed as $path)copy($path===$routes?$backup.'/web.php':$backup.'/show.blade.php',$path);
 fwrite(STDERR,"P12_NO_GO_ROLLED_BACK ".$e->getMessage()."\n");exit(2);
}
echo "P12_JC_UNWIND_APPLIED: BACKUP=".$backup."\n";
echo "NO JOBCARDS / STOCK / ROT / PURCHASE / ESTIMATE / FINANCIAL ROWS MUTATED.\n";
