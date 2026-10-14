<?php
declare(strict_types=1);
/* P30_LIFECYCLE_NEXT_ACTIONS — show next authorized action directly under status bar.
   Existing POST forms and server lifecycle guards remain authoritative. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$file=$root.'/resources/views/job-cards/lifecycle-panel.blade.php';
if(!is_file($file)||is_link($file))exit(3);
$src=(string)file_get_contents($file);
if(str_contains($src,'P30_LIFECYCLE_NEXT_ACTIONS')){echo "P30_ALREADY_INSTALLED_NOOP\n";exit(0);}
$anchor='<div class="am-life-grid">';
$actionAnchor='<div class="am-life-actions">';
if(substr_count($src,$anchor)!==1 || substr_count($src,$actionAnchor)!==1
 || !str_contains($src,"route('erp.job-cards.lifecycle.work-complete'")
 || !str_contains($src,"route('erp.job-cards.lifecycle.qc-pending'")
 || !str_contains($src,"route('erp.job-cards.lifecycle.close'")
 || !str_contains($src,"route('erp.job-cards.invoice.create'")) {
 fwrite(STDERR,"P30_NO_GO_LIFECYCLE_ANCHORS\n");exit(4);
}
$insert=<<<'BLADE'
{{-- P30_LIFECYCLE_NEXT_ACTIONS: one visible next step, no duplicate business POST. --}}
<style>
.am-next-stage{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:16px 18px;border:1px solid #9ed6e4;border-left:5px solid #1382a1;background:#f0fbff;border-radius:12px;margin:14px 0}
.am-next-stage strong{color:#0d3d60;font-size:15px}.am-next-stage p{font-size:12px;color:#496b85;margin:5px 0 0}
.am-next-stage .am-next-button{border:0;border-radius:9px;background:#12649a;color:#fff;min-height:46px;padding:11px 18px;font-size:13px;font-weight:850;cursor:pointer}
.am-next-stage .am-next-button:disabled{cursor:not-allowed;background:#cad3db;color:#536577}
.am-next-stage.waiting{border-color:#e6c98b;border-left-color:#e39d31;background:#fff9ed}
.am-next-stage.ready{border-color:#a5d9ae;border-left-color:#16835c;background:#effbf1}
.am-next-stage.done{border-color:#d4dfeb;border-left-color:#7893aa;background:#f6f8fb}
.am-life-actions{scroll-margin-top:20px}
@media(max-width:650px){.am-next-stage .am-next-button{width:100%}}
</style>
@php
  $amWorkBlockers=[];
  if(!$diagnosisReady)$amWorkBlockers[]='Diagnosis / Technician Decision';
  if($amLifeRotActive)$amWorkBlockers[]='ROT ('.$amLifeRotActive.')';
  if($amLifeLabourPending)$amWorkBlockers[]='Labour ('.$amLifeLabourPending.')';
  if($amLifePartPending)$amWorkBlockers[]='Parts ('.$amLifePartPending.')';
  if($amLifeOslPending)$amWorkBlockers[]='OSL ('.$amLifeOslPending.')';
  $amNextLabels=[
    'OPEN'=>'Start Work In Progress',
    'WIP'=>'Work Complete',
    'WORK_COMPLETE'=>'Start Quality Check',
    'QC_PENDING'=>'Record QC PASS or FAIL',
    'QC_PASSED'=>'Ready for Delivery',
    'READY_FOR_DELIVERY'=>'Close Job Card',
    'CLOSED'=>'Convert to Invoice',
    'INVOICED'=>'Invoice Completed',
  ];
  $amNextLabel=$amNextLabels[$amLifeStage]??'Review Job Card';
  $amNextReady=$amLifeStage==='WIP'?count($amWorkBlockers)===0:
     ($amLifeStage==='OPEN'?(bool)$diagnosisReady:
       in_array($amLifeStage,['WORK_COMPLETE','QC_PENDING','QC_PASSED','READY_FOR_DELIVERY','CLOSED'],true));
@endphp
<div class="am-next-stage {{ $amNextReady?'ready':($amLifeStage==='INVOICED'?'done':'waiting') }}">
 <div>
  <strong>Next Action → {{ $amNextLabel }}</strong>
  @if($amLifeStage==='WIP' && $amWorkBlockers)
    <p>Complete / resolve first: {{ implode(' · ',$amWorkBlockers) }}. Work Complete is intentionally locked.</p>
  @elseif($amLifeStage==='OPEN' && !$diagnosisReady)
    <p>Inspection → Final Diagnosis → Technician Decision complete kijiye. WIP is locked until then.</p>
  @elseif($amLifeStage==='INVOICED')
    <p>Invoice already created. Job Card remains locked; view the invoice above.</p>
  @elseif($amLifeStage==='CANCELLED')
    <p>Cancelled Job Card. Use audited Reversal Centre for outstanding linkages.</p>
  @else
    <p>Tap the action below to open its existing confirmation form. Only the server can finalize this step.</p>
  @endif
 </div>
 <button type="button" class="am-next-button"
   {{ !$amNextReady?'disabled':'' }}
   onclick="const area=document.getElementById('am-life-action-forms');if(area){const item=area.querySelector('details');if(item)item.open=true;area.scrollIntoView({behavior:'smooth',block:'center'});}">
   {{ $amNextReady?'Open '.$amNextLabel.' Form ↓':'Pending / Locked' }}
 </button>
</div>

BLADE;
$out=str_replace($anchor,$insert.$anchor,$src);
$out=str_replace($actionAnchor,'<div class="am-life-actions" id="am-life-action-forms">',$out);
try {
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $compiled=app('blade.compiler')->compileString($out);
 token_get_all($compiled,TOKEN_PARSE);
 if(!str_contains($compiled,'P30_LIFECYCLE_NEXT_ACTIONS')
 ||!str_contains($compiled,'am-life-action-forms')
 ||!str_contains($compiled,'erp.job-cards.lifecycle'))throw new RuntimeException('Code/view guard');
 echo "P30_BLADE_COMPILED_TOKEN_PARSE_PASS\n";
 echo "P30_EXISTING_APPROVED_LIFECYCLE_ACTION_FORMS_PRESERVED_PASS\n";
} catch(Throwable $e){fwrite(STDERR,"P30_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P30_DRY_RUN_NO_LIFECYCLE_OR_ACCOUNTING_CHANGE\n";exit(0);}
$backDir=$root.'/storage/app/p30_next_action_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backDir,0700,true))exit(6);
$backup=$backDir.'/lifecycle-panel.blade.php';$hash=hash('sha256',$src);
if(!copy($file,$backup)||hash_file('sha256',$backup)!==$hash)exit(6);
if(hash_file('sha256',$file)!==$hash)exit(7);
$tmp=tempnam(dirname($file),'.p30_');
try{
 if(!$tmp||file_put_contents($tmp,$out,LOCK_EX)!==strlen($out))throw new RuntimeException('Temp write');
 chmod($tmp,fileperms($file)&0777);
 if(!rename($tmp,$file))throw new RuntimeException('Atomic rename');
 if(hash_file('sha256',$file)!==hash('sha256',$out))throw new RuntimeException('Checksum');
 echo "P30_APPLIED_BACKUP=".$backup."\n";
 echo "P30_VIEW_ONLY_NO_LIFECYCLE_STATUS_OR_INVOICE_RECORD_MUTATIONS\n";
}catch(Throwable $e){
 @unlink($tmp);copy($backup,$file);
 fwrite(STDERR,'P30_VIEW_ROLLBACK '.$e->getMessage()."\n");exit(8);
}
