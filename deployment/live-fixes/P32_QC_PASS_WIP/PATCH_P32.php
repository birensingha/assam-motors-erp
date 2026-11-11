<?php
declare(strict_types=1);
/**
 * P32_QC_PASS_WIP_STATE_GUARD
 * UI status must follow authoritative Job Card status, not stale public label.
 * Keep QC history, stock and ledger untouched. Existing Ready-to-Delivery
 * guard requiring current WORK_COMPLETE + latest QC PASS is preserved.
 */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P32_USAGE_NO_GO\n");exit(2);}
$files=[
 'resources/views/job-cards/lifecycle-panel.blade.php',
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php'
];
$src=[];
foreach($files as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P32_NO_GO_FILE ".$p."\n");exit(3);}
 $src[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($src[$p],'P32_QC_PASS_WIP_STATE_GUARD')){echo "P32_ALREADY_PATCHED_NOOP\n";exit(0);}
}
function p32Once(string $text,string $old,string $replacement,string $tag):string{
 $n=substr_count($text,$old);if($n!==1)throw new RuntimeException('P32_NO_GO_ANCHOR_'.$tag.'_'.$n);
 return str_replace($old,$replacement,$text);
}
try{
 $view=$src[$files[0]];
 // Current production may show QC_PASSED just from customer-facing display string
 // even when the authoritative JC status is still WIP; this enables false Ready actions.
 $old=<<<'BEFORE'
elseif($amLifeStatus==='closed') $amLifeStage='CLOSED';
elseif(str_contains($amLifePublic,'ready for delivery')) $amLifeStage='READY_FOR_DELIVERY';
elseif(str_contains($amLifePublic,'quality check passed')) $amLifeStage='QC_PASSED';
elseif(str_contains($amLifePublic,'quality check')) $amLifeStage='QC_PENDING';
elseif(str_contains($amLifePublic,'work complete') || $amLifeStatus==='completed') $amLifeStage='WORK_COMPLETE';
elseif(in_array($amLifeStatus,['in progress','in-progress','work in progress','wip'])) $amLifeStage='WIP';
BEFORE;
 $new=<<<'AFTER'
elseif($amLifeStatus==='closed') $amLifeStage='CLOSED';
// P32_QC_PASS_WIP_STATE_GUARD: an uncompleted WIP job cannot progress
// because its customer-facing public label says "Quality Check Passed".
elseif(in_array($amLifeStatus,['in progress','in-progress','work in progress','wip'],true)) $amLifeStage='WIP';
elseif($amLifeStatus==='completed' && str_contains($amLifePublic,'ready for delivery')) $amLifeStage='READY_FOR_DELIVERY';
elseif($amLifeStatus==='completed' && str_contains($amLifePublic,'quality check passed')
    && $amLifeQc && strtoupper(trim((string)$amLifeQc->result))==='PASS') $amLifeStage='QC_PASSED';
elseif($amLifeStatus==='completed' && str_contains($amLifePublic,'quality check')) $amLifeStage='QC_PENDING';
elseif($amLifeStatus==='completed') $amLifeStage='WORK_COMPLETE';
AFTER;
 $view=p32Once($view,$old,$new,'authoritative_stage_selector');
 $marker='<div class="am-life-grid">';
 $notice=<<<'BLADE'
{{-- P32_QC_PASS_WIP_STATE_GUARD: explain stale public QC labels without altering audits. --}}
@if($amLifeStage==='WIP' && str_contains($amLifePublic,'quality check passed'))
<div role="alert" style="border-left:5px solid #cc8f19;border-radius:10px;background:#fff8e8;padding:15px 17px;margin:13px 0;color:#6f4a10">
 <strong>Work Complete is pending — QC PASS cannot release this WIP Job Card.</strong>
 <p style="margin:6px 0 0;font-size:12px">Old QC PASS record exists, but the authoritative Job Card status is still WIP. Finish pending ROT/Labour/Parts/OSL first, then **Work Complete → Start QC → QC PASS → Ready for Delivery**. Old QC records are retained in audit history.</p>
 <p style="margin:6px 0 0;font-size:12px">Pending: ROT {{ $amLifeRotActive }}, Labour {{ $amLifeLabourPending }}, Parts {{ $amLifePartPending }}, OSL {{ $amLifeOslPending }}. Planned Parts must be resolved as fitted/returned using stock controls.</p>
</div>
@endif

BLADE;
 $view=p32Once($view,$marker,$notice.$marker,'status_notice');
 $controller=$src[$files[1]];
 $oldGuard=<<<'BEFORE'
            if(strtolower((string)$card->status)!=='completed'
                || !str_contains(strtolower((string)$card->customer_public_status),'quality check passed')){
                throw ValidationException::withMessages(['status'=>'QC PASS ke baad hi Ready for Delivery mark hoga.']);
            }
BEFORE;
 $newGuard=<<<'AFTER'
            // P32_QC_PASS_WIP_STATE_GUARD: readable error when legacy/shortcut
            // QC PASS has changed the public label but not the actual work order.
            if(in_array(strtolower(trim((string)$card->status)),
                ['in progress','in-progress','work in progress','wip'],true)){
                throw ValidationException::withMessages(['status'=>
                  'Job Card is still WIP, despite a recorded QC PASS. Complete outstanding ROT, Labour and Parts; then mark WORK COMPLETE, Start QC, record a fresh QC PASS, and retry Ready for Delivery.']);
            }
            if(strtolower(trim((string)$card->status))!=='completed'
                || !str_contains(strtolower((string)$card->customer_public_status),'quality check passed')){
                throw ValidationException::withMessages(['status'=>'QC PASS + completed Job Card is required before Ready for Delivery. Check Work Complete and latest QC.']);
            }
AFTER;
 $controller=p32Once($controller,$oldGuard,$newGuard,'ready_error_guard');
 $after=[$files[0]=>$view,$files[1]=>$controller];
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach($after as $p=>$code){
   if(str_ends_with($p,'.blade.php'))$code=app('blade.compiler')->compileString($code);
   token_get_all($code,TOKEN_PARSE);
 }
 if(!str_contains($controller,"orderByDesc('id')->first()")||!
    str_contains($controller,"$"."this->unfinishedWork("))throw new RuntimeException('Ready latest PASS or unfinished-work check changed');
 echo "P32_AUTHORITATIVE_WIP_BEFORE_QC_LABEL_PASS\n";
 echo "P32_READY_SERVER_QC_PASS_AND_UNFINISHED_WORK_GATES_PRESERVED\n";
 echo "P32_BLADE_COMPILER_AND_PHP_PARSE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P32_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P32_DRY_RUN_NO_STATUS_STOCK_QC_OR_INVOICE_WRITES\n";exit(0);}
$bakdir=$root.'/storage/app/p32_qc_wip_view_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bakdir,0700,true))exit(6);
foreach($src as $p=>$s){
 $dest=$bakdir.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$dest)||hash_file('sha256',$dest)!==hash('sha256',$s)){fwrite(STDERR,"P32_NO_GO_BACKUP\n");exit(6);}
}
$written=[];
try{
 foreach($src as $p=>$s){
  $target=$root.'/'.$p;
  if(hash_file('sha256',$target)!==hash('sha256',$s))throw new RuntimeException('Concurrent file change '.$p);
  $tmp=tempnam(dirname($target),'.p32_');
  if(!$tmp||file_put_contents($tmp,$after[$p],LOCK_EX)!==strlen($after[$p]))throw new RuntimeException('Temp write '.$p);
  chmod($tmp,fileperms($target)&0777);
  if(!rename($tmp,$target))throw new RuntimeException('Atomic replace '.$p);
  $written[]=$p;
 }
 echo 'P32_APPLIED_BACKUP='.$bakdir."\n";
 echo "P32_ONLY_VIEW_AND_VALIDATION_MESSAGE_UPDATED\n";
}catch(Throwable $e){
 foreach(array_reverse($written) as $p)copy($bakdir.'/'.str_replace('/','__',$p),$root.'/'.$p);
 fwrite(STDERR,"P32_AUTO_SOURCE_ROLLBACK ".$e->getMessage()."\n");exit(7);
}
