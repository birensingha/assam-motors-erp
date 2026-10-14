<?php
declare(strict_types=1);
/** P34_ROT_BILLING_CONTROL: guarded ROT charge status + invoice inclusion, no automatic financial POSTs. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P34_USAGE_ERROR\n");exit(2);}
$edit=['routes/web.php',
 'resources/views/job-cards/show.blade.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php'
];
$payload=[
 'app/Http/Controllers/Web/AdminRotBillingController.php'=>__DIR__.'/payload/AdminRotBillingController.php',
 'resources/views/job-cards/rot-billing-control.blade.php'=>__DIR__.'/payload/rot-billing-control.blade.php',
 'database/migrations/2026_10_14_181000_add_labour_billing_flag.php'=>__DIR__.'/payload/2026_10_14_181000_add_labour_billing_flag.php',
];
$old=[];
foreach($edit as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P34_NO_GO_MISSING ".$p."\n");exit(3);}
 $old[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($old[$p],'P34_ROT_BILLING_CONTROL')){fwrite(STDERR,"P34_ALREADY_INSTALLED_NOOP\n");exit(0);}
}
foreach($payload as $p=>$source){
 if(!is_file($source)||file_exists($root.'/'.$p)){fwrite(STDERR,"P34_NO_GO_PAYLOAD_OR_DEST ".$p."\n");exit(4);}
}
function once34(string $txt,string $needle,string $replace,string $name):string{
 $n=substr_count($txt,$needle);
 if($n!==1)throw new RuntimeException('P34_NO_GO_ANCHOR_'.$name.'_COUNT_'.$n);
 return str_replace($needle,$replace,$txt);
}
try{
 $routes=$old[$edit[0]];
 $anchor="Route::post('/job-cards/{job}/parts/{part}/fitted', [\App\Http\Controllers\Web\AdminJobCardPartIssueController::class,'fitted'])";
 if(substr_count($routes,$anchor)!==1)throw new RuntimeException('P33 stock routes required');
 $next="        ->whereNumber('job')->whereNumber('part')->name('erp.job-cards.parts.fitted');";
 $routeEnd=strpos($routes,$next,strpos($routes,$anchor));
 if($routeEnd===false)throw new RuntimeException('P33 route ending missing');
 $position=$routeEnd+strlen($next);
 $routes=substr_replace($routes,<<<'ROUTES'

    // P34_ROT_BILLING_CONTROL: billing policy is independent of ROT timer.
    Route::post('/job-cards/{job}/labour/{labour}/billing', [\App\Http\Controllers\Web\AdminRotBillingController::class,'setBilling'])
        ->whereNumber('job')->whereNumber('labour')->name('erp.job-cards.labour.billing');
ROUTES, $position,0);
 $new[$edit[0]]=$routes;

 $view=once34($old[$edit[1]],
    '      <div class="rot-actions">',
    "      {{-- P34_ROT_BILLING_CONTROL --}}\n      @include('job-cards.rot-billing-control')\n      <div class=\"rot-actions\">",'labour_status_card');
 $new[$edit[1]]=$view;

 $page=$old[$edit[2]];
 $oldSum=<<<'PHP'
        $labourTotal = round((float)DB::table('job_card_labour')
            ->where('job_card_id',$jobId)
            ->sum('total'),2);
PHP;
 $newSum=<<<'PHP'
        // P34_ROT_BILLING_CONTROL: do not rebill internal/No Charge work on edits.
        $billingQuery=DB::table('job_card_labour')->where('job_card_id',$jobId);
        if(Schema::hasColumn('job_card_labour','billing_included')){
            $billingQuery->where('billing_included',1);
        }
        $labourTotal = round((float)$billingQuery->sum('total'),2);
PHP;
 $page=once34($page,$oldSum,$newSum,'recalc_labour_sum');
 $oldGrand=<<<'PHP'
        if (Schema::hasColumn('job_cards','grand_total')) {
            $update['grand_total']=round($partsTotal+$labourTotal,2);
        }
PHP;
 $newGrand=<<<'PHP'
        if (Schema::hasColumn('job_cards','grand_total')) {
            if(Schema::hasColumn('job_card_labour','billing_included') &&
               DB::table('job_card_labour')->where('job_card_id',$jobId)->where('billing_included',0)->exists()){
                // P34_ROT_BILLING_CONTROL: keep GST and round-off when No Charge labour exists.
                $gst=(float)DB::table('job_cards')->where('id',$jobId)->value('gst_total');
                $pre=round($partsTotal+$labourTotal+$gst,2);
                $round=round(round($pre,0,PHP_ROUND_HALF_UP)-$pre,2);
                $update['pre_round_total']=$pre;
                $update['round_off']=$round;
                $update['grand_total']=round($pre+$round,2);
            } else {
                $update['grand_total']=round($partsTotal+$labourTotal,2);
            }
        }
PHP;
 $page=once34($page,$oldGrand,$newGrand,'recalc_grand_total');
 $new[$edit[2]]=$page;

 $invoices=$old[$edit[3]];
 $oldFallback=<<<'PHP'
            if($labourTotal<=0 && Schema::hasTable('job_card_labour')){
                $labourTotal=round((float)DB::table('job_card_labour')->where('job_card_id',$job)->sum('total'),2);
            }
PHP;
 $newFallback=<<<'PHP'
            if($labourTotal<=0 && Schema::hasTable('job_card_labour')){
                // P34_ROT_BILLING_CONTROL: never resurrect No Charge labour in invoice fallback.
                $billableLines=DB::table('job_card_labour')->where('job_card_id',$job);
                if(Schema::hasColumn('job_card_labour','billing_included')){
                    $billableLines->where('billing_included',1);
                }
                $labourTotal=round((float)$billableLines->sum('total'),2);
            }
PHP;
 $invoices=once34($invoices,$oldFallback,$newFallback,'invoice_fallback');
 $oldLines=<<<'PHP'
        $labour=Schema::hasTable('job_card_labour')
            ? DB::table('job_card_labour')->where('job_card_id',$job->id)->orderBy('id')->get()
            : collect();
PHP;
 $newLines=<<<'PHP'
        $labour=Schema::hasTable('job_card_labour')
            ? DB::table('job_card_labour')->where('job_card_id',$job->id)
                ->when(Schema::hasColumn('job_card_labour','billing_included'),
                    fn($query)=>$query->where('billing_included',1))
                ->orderBy('id')->get()
            : collect();
PHP;
 $invoices=once34($invoices,$oldLines,$newLines,'invoice_print_items');
 $new[$edit[3]]=$invoices;

 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach(['job_cards','job_card_labour','invoices','job_card_change_audits'] as $table){
    if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('Missing billing/audit table '.$table);
 }
 foreach($new as $p=>$contents){
    if(str_ends_with($p,'.blade.php'))$contents=app('blade.compiler')->compileString($contents);
    token_get_all($contents,TOKEN_PARSE);
 }
 foreach($payload as $p=>$src){
    $contents=(string)file_get_contents($src);
    if(str_ends_with($p,'.blade.php'))$contents=app('blade.compiler')->compileString($contents);
    token_get_all($contents,TOKEN_PARSE);
 }
 echo "P34_ROT_BILLING_ADMIN_ACTION_PHP_BLADE_PASS\n";
 echo "P34_NON_BILLABLE_LABOUR_EXCLUDED_FROM_INVOICE_SUM_AND_PRINT_PASS\n";
 echo "P34_TIMING_ROT_STATUS_AND_HISTORY_PRESERVED_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P34_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P34_DRY_RUN_NO_INVOICE_LEDGER_ROT_OR_STOCK_MUTATION\n";exit(0);}
$bak=$root.'/storage/app/p34_rot_billing_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($old as $p=>$contents){
 $backup=$bak.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$backup)||hash_file('sha256',$backup)!==hash('sha256',$contents)){
   fwrite(STDERR,"P34_NO_GO_BACKUP_VERIFY\n");exit(6);
 }
}
$installed=[];
try{
 foreach($new as $p=>$contents){
  $dest=$root.'/'.$p;
  if(hash_file('sha256',$dest)!==hash('sha256',$old[$p]))throw new RuntimeException('Concurrent modification '.$p);
  $tmp=tempnam(dirname($dest),'.p34_');
  if(!$tmp||file_put_contents($tmp,$contents,LOCK_EX)!==strlen($contents))throw new RuntimeException('Temporary write failed '.$p);
  chmod($tmp,fileperms($dest)&0777);
  if(!rename($tmp,$dest))throw new RuntimeException('Atomic rename failed '.$p);
  $installed[]=$p;
 }
 foreach($payload as $p=>$src){
  if(!copy($src,$root.'/'.$p))throw new RuntimeException('New file copy failed '.$p);
  $installed[]=$p;
 }
 echo 'P34_APPLIED_BACKUP='.$bak."\n";
 echo "P34_ONLY_SOURCE_AND_ADDITIVE_MIGRATION_FILE_INSTALLED\n";
}catch(Throwable $e){
 foreach(array_reverse($installed) as $p){
  if(isset($payload[$p]))@unlink($root.'/'.$p);
  else copy($bak.'/'.str_replace('/','__',$p),$root.'/'.$p);
 }
 fwrite(STDERR,'P34_AUTO_SOURCE_ROLLBACK '.$e->getMessage()."\n");exit(7);
}
