<?php
declare(strict_types=1);
/** P36_VISIBLE_PARTS_ACTIONS — exactly scoped, backup-guarded UI relocation. No stock/business writes. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$views=['resources/views/job-cards/show.blade.php','resources/views/job-cards/lifecycle-panel.blade.php'];
$newPath='resources/views/job-cards/part-actions-visible.blade.php';
$payload=__DIR__.'/payload/part-actions-visible.blade.php';
$original=[];
foreach($views as $path){
 if(!is_file($root.'/'.$path)||is_link($root.'/'.$path)){fwrite(STDERR,"P36_NO_GO_SOURCE ".$path."\n");exit(3);}
 $original[$path]=(string)file_get_contents($root.'/'.$path);
 if(str_contains($original[$path],'P36_VISIBLE_PARTS_ACTIONS')){fwrite(STDERR,"P36_NO_GO_ALREADY_INSTALLED\n");exit(3);}
}
if(file_exists($root.'/'.$newPath)||!is_file($payload)){fwrite(STDERR,"P36_NO_GO_PARTIAL_DESTINATION\n");exit(4);}
function only36($txt,$old,$new,$label) {
 $n=substr_count($txt,$old);
 if($n!==1)throw new RuntimeException('P36_NO_GO_ANCHOR_'.$label.'_COUNT_'.$n);
 return str_replace($old,$new,$txt);
}
try{
 $show=$original[$views[0]];
 $show=only36($show,
 '<div class="card section-gap jc-purchase-block jc-parts-block">',
 '<div class="card section-gap jc-purchase-block jc-parts-block">'."\n".
 "      {{-- P36_VISIBLE_PARTS_ACTIONS: prominent always-visible action panel above Parts table --}}\n".
 "      @include('job-cards.part-actions-visible')",
 'parts_card');
 $show=only36($show,
 "          {{-- P33_JOB_PART_REQUISITION_ISSUE_FITTED --}}\n          @include('job-cards.part-stock-control')",
 "          {{-- P36: actionable stock forms moved ABOVE scrollable table; Edit and Delete remain in Action column --}}",
 'hidden_right_column');
 $life=$original[$views[1]];
 $link='<a href="#p30-current-stage-action">↓ Go to Step-wise Action Buttons</a>';
 $replaced=<<<'BLADE'
@if($amLifeStage==='WIP' && $amLifePartPending>0)
 <a href="#p36-parts-actions" style="background:#a56812;color:#fff">📦 Parts Pending: {{ $amLifePartPending }} — Open Requisition / Issue / Fitted ↓</a>
@else
 <a href="#p30-current-stage-action">↓ Go to Step-wise Action Buttons</a>
@endif
BLADE;
 $life=only36($life,$link,$replaced,'next_action_jump');
 $changed=[$views[0]=>$show,$views[1]=>$life];
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($changed as $path=>$contents){
   token_get_all(app('blade.compiler')->compileString($contents),TOKEN_PARSE);
 }
 token_get_all(app('blade.compiler')->compileString((string)file_get_contents($payload)),TOKEN_PARSE);
 if(!str_contains($show,'p36')||!str_contains($life,'p36-parts-actions'))throw new RuntimeException('Panel injection lost');
 echo "P36_COMPILED_SOURCE_AND_PARTIAL_PASS\n";
 echo "P36_MOVE_OUT_OF_HORIZONTAL_ACTION_COLUMN_PASS\n";
 echo "P36_TOP_NEXT_STEP_LINK_TO_PENDING_PARTS_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P36_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P36_DRY_RUN_NO_STOCK_FINANCE_OR_JC_WRITES\n";exit(0);}
$dir=$root.'/storage/app/p36_visible_parts_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($dir,0700,true))exit(6);
foreach($original as $path=>$contents){
 $dest=$dir.'/'.str_replace('/','__',$path);
 if(!copy($root.'/'.$path,$dest)||hash_file('sha256',$dest)!==hash('sha256',$contents)){
    fwrite(STDERR,"P36_NO_GO_BACKUP_VERIFY\n");exit(6);
 }
}
$done=[];
try{
 foreach($changed as $path=>$contents){
   $file=$root.'/'.$path;
   if(hash_file('sha256',$file)!==hash('sha256',$original[$path]))throw new RuntimeException('Concurrent source change '.$path);
   $tmp=tempnam(dirname($file),'.p36_');
   if(!$tmp||file_put_contents($tmp,$contents,LOCK_EX)!==strlen($contents))throw new RuntimeException('Temp write');
   chmod($tmp,fileperms($file)&0777);
   if(!rename($tmp,$file))throw new RuntimeException('Atomic replace');
   $done[]=$path;
 }
 if(!copy($payload,$root.'/'.$newPath))throw new RuntimeException('Partial write');
 $done[]=$newPath;
 echo 'P36_APPLIED_BACKUP='.$dir."\n";
 echo "P36_VIEW_ONLY_NO_JOB_PART_OR_STOCK_MOVEMENT_CHANGED\n";
}catch(Throwable $e){
 foreach(array_reverse($done) as $path){
    if($path===$newPath)@unlink($root.'/'.$path);
    else copy($dir.'/'.str_replace('/','__',$path),$root.'/'.$path);
 }
 fwrite(STDERR,"P36_AUTO_ROLLBACK ".$e->getMessage()."\n");exit(7);
}
