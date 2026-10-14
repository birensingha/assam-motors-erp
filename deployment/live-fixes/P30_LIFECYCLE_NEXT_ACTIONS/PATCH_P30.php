<?php
declare(strict_types=1);
/* P30_LIFECYCLE_NEXT_ACTIONS: visible jump to existing guarded lifecycle action form. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$file=$root.'/resources/views/job-cards/lifecycle-panel.blade.php';
if(!is_file($file)||is_link($file))exit(3);
$before=(string)file_get_contents($file);
if(str_contains($before,'P30_LIFECYCLE_NEXT_ACTIONS')){echo "P30_ALREADY_INSTALLED_NOOP\n";exit(0);}
$area='<div class="am-life-grid">';
$actions='<div class="am-life-actions">';
if(substr_count($before,$area)!==1 || substr_count($before,$actions)!==1 ||
  !str_contains($before,"route('erp.job-cards.lifecycle.work-complete'") ||
  !str_contains($before,"route('erp.job-cards.lifecycle.qc-pending'") ||
  !str_contains($before,"route('erp.job-cards.lifecycle.close'") ||
  !str_contains($before,"route('erp.job-cards.invoice.create'")){
 fwrite(STDERR,"P30_NO_GO_LIFECYCLE_ANCHORS\n");exit(4);
}
$insert=<<<'BLADE'
{{-- P30_LIFECYCLE_NEXT_ACTIONS --}}
<style>
.p30-next-action{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:16px 17px;margin:15px 0;border:1px solid #a0d2e1;border-left:5px solid #167e9d;background:#eefbff;border-radius:12px}
.p30-next-action strong{font-size:15px;color:#173d5e}.p30-next-action p{font-size:12px;color:#496980;margin:6px 0 0}
.p30-next-action a{display:inline-flex;align-items:center;justify-content:center;padding:12px 16px;min-height:45px;border-radius:10px;background:#165d94;color:#fff;text-decoration:none;font-weight:800;font-size:13px}
.p30-next-action.blocked{background:#fff8e8;border-color:#e8c888;border-left-color:#e0a027}
.p30-next-action.completed{background:#f2f6fa;border-color:#d3e0e8;border-left-color:#799ab0}
#p30-current-stage-action{scroll-margin-top:25px}
@media(max-width:650px){.p30-next-action a{width:100%}}
</style>
<div class="p30-next-action {{ $amLifeStage==='INVOICED'?'completed':($amLifeStage==='WIP' && ($amLifeRotActive || $amLifeLabourPending || $amLifePartPending || $amLifeOslPending || !$diagnosisReady)?'blocked':'') }}">
 <div>
  <strong>Next Step:
   @if($amLifeStage==='OPEN')
      Start WIP
   @elseif($amLifeStage==='WIP')
      Work Complete
   @elseif($amLifeStage==='WORK_COMPLETE')
      Start Quality Check
   @elseif($amLifeStage==='QC_PENDING')
      QC PASS / FAIL
   @elseif($amLifeStage==='QC_PASSED')
      Ready for Delivery
   @elseif($amLifeStage==='READY_FOR_DELIVERY')
      Close Job Card
   @elseif($amLifeStage==='CLOSED')
      Convert to Invoice
   @elseif($amLifeStage==='INVOICED')
      Invoice Completed
   @else
      Review Job Card Status
   @endif
  </strong>
  @if($amLifeStage==='WIP' && ($amLifeRotActive || $amLifeLabourPending || $amLifePartPending || $amLifeOslPending || !$diagnosisReady))
   <p>Work Complete locked. ROT pending: {{ $amLifeRotActive }}, Labour: {{ $amLifeLabourPending }}, Parts: {{ $amLifePartPending }}, OSL: {{ $amLifeOslPending }}. Diagnosis/Decision must also be completed.</p>
  @elseif($amLifeStage==='OPEN' && !$diagnosisReady)
   <p>Inspection → Final Diagnosis → Technician Decision complete hone ke baad WIP start hoga.</p>
  @elseif($amLifeStage==='INVOICED')
   <p>Invoice already created; Job Card billing is locked.</p>
  @else
   <p>Click below to reach the existing stage confirmation button. Save sirf server validation ke baad hoga.</p>
  @endif
 </div>
 <a href="#p30-current-stage-action">↓ Go to Step-wise Action Buttons</a>
</div>

BLADE;
$after=str_replace($area,$insert.$area,$before);
$after=str_replace($actions,'<div class="am-life-actions" id="p30-current-stage-action">',$after);
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $compiled=app('blade.compiler')->compileString($after);
 token_get_all($compiled,TOKEN_PARSE);
 if(!str_contains($compiled,'p30-next-action')||
    !str_contains($compiled,'p30-current-stage-action')||
    !str_contains($compiled,'erp.job-cards.lifecycle')){
   throw new RuntimeException('Compiled source marker missing');
 }
 echo "P30_BLADE_NEXT_ACTION_COMPILED_PASS\n";
 echo "P30_EXISTING_STEP_ACTION_ROUTES_PRESERVED_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P30_NO_GO_COMPILE ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P30_DRY_RUN_NO_BUSINESS_MUTATIONS\n";exit(0);}
$dir=$root.'/storage/app/p30_lifecycle_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($dir,0700,true))exit(6);
$bak=$dir.'/lifecycle-panel.blade.php';$old=hash('sha256',$before);
if(!copy($file,$bak)||hash_file('sha256',$bak)!==$old)exit(6);
if(hash_file('sha256',$file)!==$old)exit(7);
$tmp=tempnam(dirname($file),'.p30_');
try{
 if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after))throw new RuntimeException('Temp write');
 chmod($tmp,fileperms($file)&0777);
 if(!rename($tmp,$file))throw new RuntimeException('Atomic update');
 if(hash_file('sha256',$file)!==hash('sha256',$after))throw new RuntimeException('Checksum');
 echo 'P30_APPLIED_BACKUP='.$bak."\n";
 echo "P30_VIEW_ONLY_NO_WORKFLOW_RECORDS_CHANGED\n";
}catch(Throwable $e){
 @unlink($tmp);copy($bak,$file);
 fwrite(STDERR,"P30_AUTO_ROLLBACK ".$e->getMessage()."\n");exit(8);
}
