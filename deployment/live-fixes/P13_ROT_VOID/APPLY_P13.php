<?php
declare(strict_types=1);
/** P13 ROT Void reporting and Cancelled JC lifecycle fix. Backups mandatory; no customer/stock/ROT data writes on install. */
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: php APPLY_P13.php check|apply\n");exit(2);}
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
$payload=__DIR__.'/payload';
if(realpath($root)!==$root||!is_file($root.'/artisan')||!is_file($payload.'/AdminRotVoidController.php')||!is_file($payload.'/2026_10_13_104500_create_job_card_rot_voids.php')){fwrite(STDERR,"NO-GO: missing canonical staging or signed payload\n");exit(2);}
$routes='routes/web.php';
$edit=[
 'routes/web.php','app/Services/HrOperationsService.php',
 'app/Http/Controllers/Web/AdminJobCardUnwindController.php',
 'resources/views/job-cards/unwind.blade.php',
 'resources/views/job-cards/lifecycle-panel.blade.php',
 'resources/views/job-cards/show.blade.php',
 'resources/views/job-cards/index.blade.php'
];
$new=[
 'app/Http/Controllers/Web/AdminRotVoidController.php'=>$payload.'/AdminRotVoidController.php',
 'database/migrations/2026_10_13_104500_create_job_card_rot_voids.php'=>$payload.'/2026_10_13_104500_create_job_card_rot_voids.php',
];
function changeOne(string $s,string $find,string $replace,string $label):string{
 $n=substr_count($s,$find);
 if($n!==1)throw new RuntimeException("NO-GO anchor mismatch ".$label.": count=".$n);
 return str_replace($find,$replace,$s);
}
try{
 foreach($new as $dst=>$src)if(is_file($root.'/'.$dst)||is_link($root.'/'.$dst))throw new RuntimeException("NO-GO destination exists ".$dst);
 $map=[];
 foreach($edit as $f){if(!is_file($root.'/'.$f)||is_link($root.'/'.$f))throw new RuntimeException("NO-GO source ".$f);$map[$f]=['before'=>(string)file_get_contents($root.'/'.$f),'after'=>''];}
 $f='routes/web.php';$s=$map[$f]['before'];
 $old="Route::post('/job-cards/{job}/reversal/cancel', [\\App\\Http\\Controllers\\Web\\AdminJobCardUnwindController::class,'cancel'])->whereNumber('job')->name('erp.job-cards.reversal.cancel');";
 $newRoute="\n    // P13_ROT_VOID_AUDIT: original ROT remains immutable, reporting exclusion stored separately.\n".
 "    Route::post('/job-cards/{job}/reversal/rot/{rot}/void', [\\App\\Http\\Controllers\\Web\\AdminRotVoidController::class,'voidCompleted'])->whereNumber('job')->name('erp.job-cards.rot.void');";
 $map[$f]['after']=changeOne($s,$old,$old.$newRoute,'ROT Void route');
 $f='app/Http/Controllers/Web/AdminJobCardUnwindController.php';$s=$map[$f]['before'];
 $old="        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested'));";
 $newController="        \$rotSessions=DB::table('rot_sessions')->where('job_card_id',\$job)->orderBy('created_at')->get();\n".
 "        \$voidedRotIds=Schema::hasTable('job_card_rot_voids')\n".
 "            ? DB::table('job_card_rot_voids')->where('job_card_id',\$job)->pluck('rot_session_id')->all():[];\n".
 "        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested','rotSessions','voidedRotIds'));";
 $map[$f]['after']=changeOne($s,$old,$newController,'Reversal Centre ROT records');
 $f='resources/views/job-cards/unwind.blade.php';$s=$map[$f]['before'];
 $needle=" <section class=\"jcu-card\">\n  <h2>2 · Allotted Parts / Stock Return</h2>";
 $panel=<<<'BLADE'
 <section class="jcu-card" style="border-top:4px solid #ef9707">
  <h2>Completed ROT · Audited Void for Testing</h2>
  <div class="jcu-note">ROT session history, original time and technician logs are not deleted. Void excludes the session from HR productivity, TAT and salary-impact reports. It does NOT reverse Labour billing, released Estimates, purchased Parts or supplier liability.</div>
  <div class="jcu-list">
  @forelse($rotSessions as $session)
   @php($isVoided=in_array((string)$session->id,$voidedRotIds,true))
   <div class="jcu-item">
    <strong>{{ $session->rot_code }} — {{ $session->rot_desc }}</strong>
    <div class="jcu-small">ROT ID: {{ $session->id }} · Original status: {{ $session->status }} · Original time: {{ gmdate('H:i:s',(int)($session->total_seconds??0)) }}</div>
    @if($isVoided)
     <div class="jcu-note" style="background:#eaf7ed;border-color:#159947"><strong>VOIDED FROM HR REPORTING</strong> · Original ROT preserved for audit.</div>
    @elseif(strtolower((string)$card->status)==='cancelled' && strtolower((string)$session->status)==='completed')
     <form method="post" class="jcu-form" action="{{ route('erp.job-cards.rot.void',[$card->id,$session->id]) }}" onsubmit="return confirm('Exclude this completed testing ROT from HR productivity/TAT reports? This is audited. Original time remains in history.')">
      @csrf
      <label>Type exact ROT ID<input name="confirm_rot_id" maxlength="40" required autocomplete="off" placeholder="{{ $session->id }}"></label>
      <label>Reason for Void (12+ characters)<textarea name="reason" rows="2" minlength="12" maxlength="500" required></textarea></label>
      <label style="width:100%;font-weight:400"><input type="checkbox" name="acknowledge_productivity" value="1" required style="width:auto;min-height:auto"> I understand this is a reporting correction, not a stock/financial reversal.</label>
      <button type="submit" class="jcu-action jcu-danger">↶ Void ROT from HR Productivity</button>
     </form>
    @else
     <div class="jcu-small">Void available for completed ROT on cancelled, non-invoiced Job Card only.</div>
    @endif
   </div>
  @empty <div class="jcu-item">No ROT history.</div>@endforelse
  </div>
 </section>
BLADE;
 $map[$f]['after']=changeOne($s,$needle,$panel."\n".$needle,'ROT Void UI panel');
 $f='app/Services/HrOperationsService.php';$s=$map[$f]['before'];
 $old="            ->leftJoin('rot_sessions as rot', 'rot.id', '=', 'seg.rot_session_id')";
 $newReport=$old."\n".
 "            // P13_ROT_VOID_AUDIT: HR productive/TAT/salary-impact excludes audited voids.\n".
 "            ->whereNotExists(function (\$q){\n".
 "                \$q->selectRaw('1')->from('job_card_rot_voids as v')->whereColumn('v.rot_session_id','seg.rot_session_id');\n".
 "            })";
 $map[$f]['after']=changeOne($s,$old,$newReport,'HR report void filtering');
 $f='resources/views/job-cards/lifecycle-panel.blade.php';$s=$map[$f]['before'];
 $s=changeOne($s,
 "\$amLifeStage='OPEN';\nif(\$amLifeStatus==='invoiced'",
 "\$amLifeStage='OPEN';\nif(\$amLifeStatus==='cancelled') \$amLifeStage='CANCELLED';\nelseif(\$amLifeStatus==='invoiced'",
 'Cancelled lifecycle stage');
 $s=changeOne($s," 'INVOICED'=>'Invoiced',"," 'INVOICED'=>'Invoiced',\n 'CANCELLED'=>'Cancelled',",'Cancelled stage label');
 $s=changeOne($s,"@if(\$amLifeStage==='INVOICED' && \$amLifeInvoice)",
 "       @if(\$amLifeStage==='CANCELLED')\n".
 "         <p><strong>Job Card Cancelled.</strong> No Start WIP, QC or Invoice conversion actions are allowed. Visit Reversal Centre for preserved ROT, Purchase and Estimate history.</p>\n".
 "@elseif(\$amLifeStage==='INVOICED' && \$amLifeInvoice)",
 'Cancelled billing safety panel');
 $map[$f]['after']=$s;
 $f='resources/views/job-cards/show.blade.php';$s=$map[$f]['before'];
 $map[$f]['after']=changeOne($s,"@if(in_array(strtolower((string)\$job->status),['open','cancelled'],true))",
  "@if(strtolower((string)\$job->status)==='open')",'hide Delete Empty for cancelled summary');
 $f='resources/views/job-cards/index.blade.php';$s=$map[$f]['before'];
 $map[$f]['after']=changeOne($s,"@if(in_array(strtolower((string)\$r->status),['open','cancelled'],true))",
  "@if(strtolower((string)\$r->status)==='open')",'hide Delete Empty for cancelled register');
 foreach($map as $f=>$x){
  if(str_ends_with($f,'.php'))token_get_all($x['after'],TOKEN_PARSE);
  echo "P13_PRECHECK ".basename($f)." OLD=".hash('sha256',$x['before'])." NEW=".hash('sha256',$x['after'])."\n";
 }
 foreach($new as $dest=>$source)token_get_all((string)file_get_contents($source),TOKEN_PARSE);
} catch(Throwable $e){fwrite(STDERR,"NO-GO ".$e->getMessage()."\n");exit(2);}
if($mode==='check'){echo "P13_DRY_RUN_PASS_NO_SOURCE_OR_DATA_MODIFICATION\n";exit(0);}
$backup=$root.'/storage/app/jc_rot_void_p13_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO backup dir\n");exit(2);}
foreach($map as $f=>$x){$file=$backup.'/'.str_replace('/','__',$f);if(!copy($root.'/'.$f,$file)||hash_file('sha256',$file)!==hash('sha256',$x['before'])){fwrite(STDERR,"NO-GO backup verify ".$f."\n");exit(2);}}
$writes=[];
try{
 foreach($map as $f=>$x){
  $dest=$root.'/'.$f;
  if(hash_file('sha256',$dest)!==hash('sha256',$x['before']))throw new RuntimeException("Concurrent edit ".$f);
  $tmp=tempnam(dirname($dest),'.p13_');
  if(!$tmp||file_put_contents($tmp,$x['after'],LOCK_EX)!==strlen($x['after']))throw new RuntimeException("Stage ".$f);
  chmod($tmp,fileperms($dest)&0777);
  if(!rename($tmp,$dest))throw new RuntimeException("Replace ".$f);
  $writes[]=$f;
  if(hash_file('sha256',$dest)!==hash('sha256',$x['after']))throw new RuntimeException("Verify ".$f);
 }
 foreach($new as $dest=>$source){
  if(!copy($source,$root.'/'.$dest))throw new RuntimeException("Copy ".$dest);
  $writes[]=$dest;
  if(hash_file('sha256',$root.'/'.$dest)!==hash_file('sha256',$source))throw new RuntimeException("Payload sha ".$dest);
 }
} catch(Throwable $e){
 foreach(array_reverse($writes) as $f) {
  if(isset($new[$f]))@unlink($root.'/'.$f);
  else copy($backup.'/'.str_replace('/','__',$f),$root.'/'.$f);
 }
 fwrite(STDERR,"P13_NO_GO_SOURCES_ROLLED_BACK ".$e->getMessage()."\n");exit(2);
}
echo "P13_ROT_VOID_CODE_APPLIED: BACKUP=".$backup."\n";
echo "P13_CODE_ONLY: NO JOB CARDS, ROT, STOCK OR FINANCIAL RECORDS MODIFIED.\n";
