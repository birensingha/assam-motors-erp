<?php
declare(strict_types=1);
/**
 * P15 Point 5 review status: STAGING view-only correction.
 * Never alters Job Card / ROT / estimate / purchase / stock / invoice / ledger records.
 * Usage: php PATCH_P15.php check|apply [Laravel staging root]
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??(rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging')),'/');
if (!in_array($mode,['check','apply'],true) || !is_file($root.'/artisan')) {
    fwrite(STDERR,"Usage: php PATCH_P15.php check|apply /laravel/staging/root\n");exit(2);
}
$target=$root.'/resources/views/job-cards/unwind.blade.php';
if (!is_file($target)||is_link($target)) { fwrite(STDERR,"P15_NO_GO_MISSING_VIEW\n");exit(2); }
$old=file_get_contents($target);
if ($old===false) { fwrite(STDERR,"P15_NO_GO_READ_VIEW\n");exit(2); }
if (str_contains($old,'P15_POINT5_REVIEW_STATUS')) {
    echo "P15_ALREADY_INSTALLED_NOOP\n";exit(0);
}
if (!str_contains($old,'P14_REVERSAL_PROGRESS_STATUS') ||
    !str_contains($old,'p14-step-5') ||
    !str_contains($old,'5 · Full reversal / cancellation review (any status)')) {
    fwrite(STDERR,"P15_NO_GO_REQUIRES_P14_FIVE_STEP_VIEW\n");exit(3);
}
$anchor='  <p>Closed or invoiced records may require credit notes, receipt reversal, vendor returns and additional authorization. The review request is available for every Job Card status; it never erases data.</p>';
if (substr_count($old,$anchor)!==1) {
    fwrite(STDERR,"P15_NO_GO_POINT5_ANCHOR_CHANGED\n");exit(3);
}
$block=<<<'BLADE'
 {{-- P15_POINT5_REVIEW_STATUS — distinguishes registered review from completed reversal --}}
 @php
   $p15ReviewTypes=[
     'CANCEL'=>'Full Job Card Cancellation',
     'ROT'=>'Completed ROT Review',
     'PARTS'=>'Parts / Stock Review',
     'FINANCE'=>'Invoice / Payment / Purchase Review',
     'ESTIMATE'=>'Estimate Reversal Review',
   ];
   $p15Actions=array_map(fn($scope)=>$scope==='CANCEL'?'CANCEL_REVIEW_REQUESTED':'REVERSAL_REVIEW_'.$scope,array_keys($p15ReviewTypes));
   $p15ReviewCounts=\Illuminate\Support\Facades\DB::table('job_card_change_audits')
     ->where('job_card_id',$card->id)
     ->whereIn('action',$p15Actions)
     ->select('action')
     ->get()
     ->countBy('action');
   $p15RemainingScopes=array_filter(array_keys($p15ReviewTypes),function($scope)use($p15ReviewCounts){
     $action=$scope==='CANCEL'?'CANCEL_REVIEW_REQUESTED':'REVERSAL_REVIEW_'.$scope;
     return (int)$p15ReviewCounts->get($action,0)===0;
   });
 @endphp
 <div class="jcu-note" style="border-left-color:#0b6095;background:#f1f8fc">
  <strong>Review request status — registration is NOT reversal completion</strong>
  <p style="margin:7px 0">This action records the case for approval. Linked Estimate, Purchase, Stock or Finance entries do not change automatically. Pending points stay highlighted until actually reconciled.</p>
  <div class="jcu-grid">
   @foreach($p15ReviewTypes as $scope=>$label)
    @php($action=$scope==='CANCEL'?'CANCEL_REVIEW_REQUESTED':'REVERSAL_REVIEW_'.$scope)
    @php($count=(int)$p15ReviewCounts->get($action,0))
    <div class="jcu-box" style="{{ $count ? 'background:#f1f3f6;border-color:#d6dfe7' : 'background:#fff4e1;border-color:#f3c785' }}">
     <span class="jcu-small">{{ $label }}</span>
     <b style="font-size:13px">{{ $count ? 'REGISTERED · REVIEW PENDING' : 'NOT REQUESTED' }}</b>
     @if($count)<small>{{ $count }} existing request(s); no further duplicate submission needed</small>@endif
    </div>
   @endforeach
  </div>
 </div>
BLADE;
$updated=str_replace($anchor,$anchor."\n".$block,$old);
// Only adjust the Point 5 form. Other 4 steps remain unchanged.
$formStart=<<<'START'
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf
   <label>Review type<select name="scope" required>
    <option value="CANCEL">Full Job Card Cancellation</option><option value="ROT">Completed ROT Review</option>
    <option value="PARTS">Parts / Stock Review</option><option value="FINANCE">Invoice / Payment / Purchase Review</option>
   </select></label>
START;
$formNew=<<<'NEW'
  @if(count($p15RemainingScopes))
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf
   <label>New review type<select name="scope" required>
    <option value="">Select an unregistered review</option>
    @foreach($p15ReviewTypes as $scope=>$title)
      @if(in_array($scope,$p15RemainingScopes,true))<option value="{{ $scope }}">{{ $title }}</option>@endif
    @endforeach
   </select></label>
NEW;
if(substr_count($updated,$formStart)!==1){
    fwrite(STDERR,"P15_NO_GO_POINT5_FORM_ANCHOR_CHANGED\n");exit(3);
}
$updated=str_replace($formStart,$formNew,$updated);
$formEnd=<<<'END'
   <button class="jcu-action">Register Audited Review</button>
  </form>
  <p class="jcu-small">Cancellation requests recorded: {{ $requested }}. Existing strict-empty Delete button remains separate and cannot remove inspection/ROT/financial history.</p>
END;
$formEndNew=<<<'ENDNEW'
   <button class="jcu-action">Register New Review Request</button>
  </form>
  @else
   <div class="jcu-note">All review categories are already registered. Duplicate requests are unnecessary. Reversal execution and approvals must follow the separate verified accounting/workshop process.</div>
  @endif
  <p class="jcu-small">Cancellation requests recorded: {{ $requested }}. Existing strict-empty Delete button remains separate and cannot remove inspection/ROT/financial history.</p>
ENDNEW;
if(substr_count($updated,$formEnd)!==1){
    fwrite(STDERR,"P15_NO_GO_POINT5_FORM_END_CHANGED\n");exit(3);
}
$updated=str_replace($formEnd,$formEndNew,$updated);
if(substr_count($updated,'P15_POINT5_REVIEW_STATUS')!==1) {
    fwrite(STDERR,"P15_NO_GO_INTEGRITY\n");exit(3);
}
echo "P15_SOURCE_CHECK=PASS ORIGINAL_SHA256=".hash('sha256',$old)." NEW_SHA256=".hash('sha256',$updated)."\n";
if($mode==='check') {echo "P15_DRY_RUN_NO_WRITES=PASS\n";exit(0);}
$directory=$root.'/storage/app/p15_point5_view_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($directory,0700,true)) {fwrite(STDERR,"P15_NO_GO_BACKUP_DIR\n");exit(4);}
$backup=$directory.'/unwind.blade.php';
if(!copy($target,$backup) || hash_file('sha256',$backup)!==hash('sha256',$old)) {
    fwrite(STDERR,"P15_NO_GO_BACKUP_VERIFICATION\n");exit(4);
}
if(hash_file('sha256',$target)!==hash('sha256',$old)) {
    fwrite(STDERR,"P15_NO_GO_CONCURRENT_VIEW_CHANGE\n");exit(4);
}
$tmp=tempnam(dirname($target),'.p15_');
if(!$tmp) { fwrite(STDERR,"P15_NO_GO_TEMP\n");exit(4); }
try{
    if(file_put_contents($tmp,$updated,LOCK_EX)!==strlen($updated)) throw new RuntimeException("tmp-write");
    chmod($tmp,fileperms($target)&0777);
    if(!rename($tmp,$target))throw new RuntimeException("rename");
    if(hash_file('sha256',$target)!==hash('sha256',$updated))throw new RuntimeException("hash");
    echo "P15_APPLY_PASS BACKUP=".$backup."\n";
    echo "P15_NO_BUSINESS_DB_WRITES_OR_REVERSALS\n";
}catch(Throwable $ex){
    @unlink($tmp);
    @copy($backup,$target);
    fwrite(STDERR,"P15_ROLLBACK_DONE: ".get_class($ex)."\n");exit(5);
}
