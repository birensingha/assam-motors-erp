<?php
declare(strict_types=1);
// P10: staging JC delete form, guarded controller, backup, no data deletion.
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: APPLY_P10.php check|apply\n");exit(2);}
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
$payload=__DIR__.'/payload/AdminJobCardDeletePageController.php';
if(realpath($root)!==$root||!is_file($root.'/artisan')||!is_file($payload)){fwrite(STDERR,"NO-GO staging/payload unavailable\n");exit(2);}
$controller=$root.'/app/Http/Controllers/Web/AdminJobCardDeletePageController.php';
if(is_file($controller)){echo "ALREADY_PRESENT: no changes\n";exit(0);}
$rels=['routes/web.php','resources/views/job-cards/index.blade.php','resources/views/job-cards/show.blade.php'];
$files=[];
function patchExactly(string $source,string $old,string $new,string $label):string {
 $n=substr_count($source,$old);
 if($n!==1)throw new RuntimeException("NO-GO ".$label." occurrence=".$n);
 return str_replace($old,$new,$source);
}
try {
 foreach($rels as $rel){
  $path=$root.'/'.$rel;
  if(!is_file($path)||is_link($path))throw new RuntimeException("NO-GO file missing: ".$rel);
  $files[$rel]=['original'=>(string)file_get_contents($path),'patched'=>''];
 }
 $rel='routes/web.php';$s=$files[$rel]['original'];
 $anchor="Route::get('/job-cards/{job}', [AdminJobCardPageController::class,'show'])->whereNumber('job')->name('erp.job-cards.show');";
 $addition="\n    // JC_DELETE_GUARD_P10\n    Route::delete('/job-cards/{job}', [\\App\\Http\\Controllers\\Web\\AdminJobCardDeletePageController::class,'destroy'])->whereNumber('job')->name('erp.job-cards.destroy');";
 $files[$rel]['patched']=patchExactly($s,$anchor,$anchor.$addition,'delete route');

 $rel='resources/views/job-cards/index.blade.php';$s=$files[$rel]['original'];
 $anchor='<a class="btn" href="{{ route('."'erp.job-cards.show'".',$r->id) }}">▣ JC Summary</a>';
 $link='<a class="btn" href="{{ route('."'erp.job-cards.show'".',$r->id) }}#jc-delete-panel" style="color:#b91c1c">🗑 Delete</a>';
 $addition="\n              @if(in_array(strtolower((string)\$r->status),['open','cancelled'],true))".$link."@endif";
 $files[$rel]['patched']=patchExactly($s,$anchor,$anchor.$addition,'index delete link');

 $rel='resources/views/job-cards/show.blade.php';$s=$files[$rel]['original'];
 $anchor="@include('job-cards.lifecycle-panel')";
 $panel=<<<'BLADE'

@if(in_array(strtolower((string)$job->status),['open','cancelled'],true))
<section id="jc-delete-panel" class="card section-gap" style="border:1px solid #fecaca;border-top:4px solid #dc2626;padding:18px;border-radius:14px">
  <h3 style="margin:0 0 7px;color:#991b1b">🗑 Delete Empty Job Card</h3>
  <p class="muted">Admin only. Delete requires NO linked Inspection, ROT, Parts, Labour, Purchase, Estimate, Invoice, Stock, Payment, or other work records. If anything is linked, server refuses deletion. A reason and snapshot audit are retained.</p>
  <form method="POST" action="{{ route('erp.job-cards.destroy',$job->id) }}" onsubmit="return confirm('Delete this EMPTY Job Card permanently? This cannot be undone.')">
    @csrf
    @method('DELETE')
    <div class="form-grid">
      <label>Confirm Job Card Number *
        <input type="text" name="confirm_job_no" required maxlength="80" autocomplete="off" placeholder="Type exact {{ $job->job_no }}">
      </label>
      <label>Deletion Reason * (at least 12 characters)
        <textarea name="delete_reason" required minlength="12" maxlength="500" rows="2" placeholder="Why delete this empty card?">{{ old('delete_reason') }}</textarea>
      </label>
    </div>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit" style="background:#b91c1c;color:#fff">Permanently Delete Empty Job Card →</button>
    </div>
  </form>
</section>
@endif

BLADE;
 $files[$rel]['patched']=patchExactly($s,$anchor,$anchor."\n".$panel,'show delete form');
 foreach($files as $rel=>$item){
  if(str_ends_with($rel,'.php'))token_get_all($item['patched'],TOKEN_PARSE);
  echo "P10_PRECHECK ".basename($rel)." OLD_SHA256=".hash('sha256',$item['original'])." NEW_SHA256=".hash('sha256',$item['patched'])."\n";
 }
 token_get_all((string)file_get_contents($payload),TOKEN_PARSE);
 if(!str_contains((string)file_get_contents($payload),'JC_DELETE_GUARD_P10'))throw new RuntimeException('No controller guard');
} catch(Throwable $e){fwrite(STDERR,"NO-GO ".$e->getMessage()."\n");exit(2);}
if($mode==='check'){echo "P10_DRY_RUN_PASS: no files or Job Cards modified\n";exit(0);}
$backup=$root.'/storage/app/jc_p10_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO backup directory\n");exit(2);}
foreach($files as $rel=>$item){
 $b=$backup.'/'.str_replace('/','__',$rel);
 if(!copy($root.'/'.$rel,$b)||hash_file('sha256',$b)!==hash('sha256',$item['original'])){fwrite(STDERR,"NO-GO backup ".$rel."\n");exit(2);}
}
$written=[];$created=false;
try {
 foreach($files as $rel=>$item){
  $p=$root.'/'.$rel;
  if(hash_file('sha256',$p)!==hash('sha256',$item['original']))throw new RuntimeException("Concurrent source edit ".$rel);
  $tmp=tempnam(dirname($p),'.jc10_');
  if(!$tmp||file_put_contents($tmp,$item['patched'],LOCK_EX)!==strlen($item['patched']))throw new RuntimeException("Write failed ".$rel);
  chmod($tmp,fileperms($p)&0777);
  if(!rename($tmp,$p))throw new RuntimeException("Rename failed ".$rel);
  $written[]=$rel;
  if(hash_file('sha256',$p)!==hash('sha256',$item['patched']))throw new RuntimeException("Digest mismatch ".$rel);
 }
 if(!copy($payload,$controller))throw new RuntimeException("Controller copy failed");
 $created=true;
 if(hash_file('sha256',$controller)!==hash_file('sha256',$payload))throw new RuntimeException("Controller SHA mismatch");
} catch(Throwable $e){
 if($created)@unlink($controller);
 foreach($written as $rel)copy($backup.'/'.str_replace('/','__',$rel),$root.'/'.$rel);
 fwrite(STDERR,"NO-GO ROLLED BACK: ".$e->getMessage()."\n");exit(2);
}
echo "P10_JOBCARD_DELETE_APPLIED: BACKUP=".$backup."\n";
echo "NO JOB CARDS DELETED, no data modified.\n";
