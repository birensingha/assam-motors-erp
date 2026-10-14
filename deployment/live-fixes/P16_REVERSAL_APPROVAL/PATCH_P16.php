<?php
declare(strict_types=1);
/**
 * P16 Admin Approval Desk for staged Job Card Reversal Centre.
 * Adds Owner/Admin review decisions as immutable audit events; does not execute
 * Estimate, stock, vendor or financial reversal. Source-only installation.
 * php PATCH_P16.php check|apply /absolute/staging/laravel/root
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if (!in_array($mode,['check','apply'],true) || !is_file($root.'/artisan')) {
    fwrite(STDERR,"Usage: php PATCH_P16.php check|apply /absolute/staging/laravel/root\n"); exit(2);
}
$files=[
    'routes/web.php',
    'app/Http/Controllers/Web/AdminJobCardUnwindController.php',
    'resources/views/job-cards/unwind.blade.php'
];
$before=[];
foreach ($files as $f) {
    if (!is_file($root.'/'.$f) || is_link($root.'/'.$f)) {
        fwrite(STDERR,"P16_NO_GO_MISSING_SOURCE: $f\n");exit(3);
    }
    $before[$f]=(string)file_get_contents($root.'/'.$f);
}
if (array_reduce($files,fn($carry,$f)=>$carry+((int)str_contains($before[$f],'P16_REVERSAL_APPROVAL_DESK')),0)>0) {
    $all=true;
    foreach($files as $f)$all=$all&&str_contains($before[$f],'P16_REVERSAL_APPROVAL_DESK');
    if($all){echo "P16_ALREADY_INSTALLED_NOOP\n";exit(0);}
    fwrite(STDERR,"P16_NO_GO_PARTIAL_INSTALL\n");exit(4);
}
function p16one(string $s,string $needle,string $repl,string $label): string {
    $n=substr_count($s,$needle);
    if($n!==1)throw new RuntimeException("P16_NO_GO_ANCHOR_".$label."_".$n);
    return str_replace($needle,$repl,$s);
}
try {
    $R=$before[$files[0]];
    $rNeedle="Route::post('/job-cards/{job}/reversal/request', [\\App\\Http\\Controllers\\Web\\AdminJobCardUnwindController::class,'requestReview'])->whereNumber('job')->name('erp.job-cards.reversal.request');";
    $rAdd=<<<'ROUTE'

    // P16_REVERSAL_APPROVAL_DESK: audited decision only, never triggers financial/stock reversals.
    Route::post('/job-cards/{job}/reversal/reviews/{review}/decision', [\App\Http\Controllers\Web\AdminJobCardUnwindController::class,'decideReversalReview'])->whereNumber('job')->whereNumber('review')->name('erp.job-cards.reversal.review-decision');
ROUTE;
    $R=p16one($R,$rNeedle,$rNeedle.$rAdd,'route');

    $C=$before[$files[1]];
    $needle="        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested','rotSessions','voidedRotIds',\n            'reviewCounts','stageStates','stageDone','stagePending','reviewChoices'));";
    $addition=<<<'CODE'
        // P16_REVERSAL_APPROVAL_DESK: latest request per scope; older duplicates are history.
        $approvalScopes=[
          'REVERSAL_REVIEW_ESTIMATE'=>'Estimate Reversal',
          'REVERSAL_REVIEW_ROT'=>'ROT Review',
          'REVERSAL_REVIEW_PARTS'=>'Parts / Stock Review',
          'REVERSAL_REVIEW_FINANCE'=>'Finance / Vendor Review',
          'CANCEL_REVIEW_REQUESTED'=>'Cancellation Review',
        ];
        $scopeRequests=DB::table('job_card_change_audits')
          ->where('job_card_id',$job)->whereIn('action',array_keys($approvalScopes))
          ->orderByDesc('id')->get()->unique('action')->values();
        $reviewIds=$scopeRequests->pluck('id')->all();
        $approvalDecisions=empty($reviewIds)?collect():DB::table('job_card_change_audits')
          ->where('job_card_id',$job)->where('entity_type','REVERSAL_REVIEW')
          ->whereIn('entity_id',$reviewIds)
          ->whereIn('action',['REVERSAL_REVIEW_APPROVED','REVERSAL_REVIEW_REJECTED'])
          ->orderByDesc('id')->get()->unique('entity_id')->keyBy('entity_id');
        $reversalReviews=$scopeRequests->map(function($r)use($approvalScopes,$approvalDecisions,$card){
            $decision=$approvalDecisions->get($r->id);
            $status=$decision ? ($decision->action==='REVERSAL_REVIEW_APPROVED'?'APPROVED':'REJECTED'):'REQUESTED';
            if($r->action==='CANCEL_REVIEW_REQUESTED' && strtolower((string)$card->status)==='cancelled')
                $status='ALREADY_CANCELLED';
            return [
              'id'=>(int)$r->id,'name'=>$approvalScopes[$r->action],
              'action'=>(string)$r->action,'reason'=>(string)$r->reason,
              'status'=>$status,'requested_at'=>(string)$r->created_at,
              'decision_reason'=>$decision?(string)$decision->reason:null,
            ];
        });
        foreach($reversalReviews as $row){
            if($row['action']==='REVERSAL_REVIEW_ESTIMATE'
               && $row['status']==='APPROVED' && $stageStates[3]['state']!=='DONE'){
                $stageStates[3]['state']='APPROVED';
                $stageStates[3]['detail']='Admin review approved. Estimate reversal execution is still pending.';
            }
        }
        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested','rotSessions','voidedRotIds',
            'reviewCounts','stageStates','stageDone','stagePending','reviewChoices','reversalReviews'));
CODE;
    $C=p16one($C,$needle,$addition,'controller_show');

    $method=<<<'METHOD'
    /** P16_REVERSAL_APPROVAL_DESK: approval is not a reversal transaction. */
    public function decideReversalReview(Request $request,int $job,int $review):RedirectResponse {
        $admin=$this->admin($request);
        $input=$request->validate([
            'decision'=>['required','in:APPROVE,REJECT'],
            'reason'=>['required','string','min:12','max:500'],
            'confirm_review_id'=>['required','integer','min:1'],
            'acknowledge_no_reversal'=>['accepted'],
        ]);
        if((int)$input['confirm_review_id']!==$review)
            throw ValidationException::withMessages(['confirm_review_id'=>'Request ID mismatch; approval not recorded.']);
        DB::transaction(function()use($job,$review,$input,$admin){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            if(!$card)abort(404);
            $allowed=['REVERSAL_REVIEW_ESTIMATE','REVERSAL_REVIEW_ROT','REVERSAL_REVIEW_PARTS',
                      'REVERSAL_REVIEW_FINANCE','CANCEL_REVIEW_REQUESTED'];
            $row=DB::table('job_card_change_audits')->where('job_card_id',$job)->where('id',$review)
                ->whereIn('action',$allowed)->lockForUpdate()->first();
            if(!$row)abort(404,'Reversal review request not found.');
            $latest=DB::table('job_card_change_audits')->where('job_card_id',$job)
                ->where('action',$row->action)->orderByDesc('id')->first();
            if(!$latest || (int)$latest->id!==$review)
                throw ValidationException::withMessages(['decision'=>'An updated review request exists. Open the latest request.']);
            if($row->action==='CANCEL_REVIEW_REQUESTED' && strtolower((string)$card->status)==='cancelled')
                throw ValidationException::withMessages(['decision'=>'Job Card is already cancelled; additional cancellation approval is not needed.']);
            $exists=DB::table('job_card_change_audits')->where('job_card_id',$job)
                ->where('entity_type','REVERSAL_REVIEW')->where('entity_id',$review)
                ->whereIn('action',['REVERSAL_REVIEW_APPROVED','REVERSAL_REVIEW_REJECTED'])->exists();
            if($exists)throw ValidationException::withMessages(['decision'=>'This review already has a recorded decision.']);
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,'entity_type'=>'REVERSAL_REVIEW','entity_id'=>$review,
                'action'=>$input['decision']==='APPROVE'?'REVERSAL_REVIEW_APPROVED':'REVERSAL_REVIEW_REJECTED',
                'before_json'=>json_encode(['review_id'=>$review,'scope'=>$row->action,'status'=>'REQUESTED'],JSON_THROW_ON_ERROR),
                'after_json'=>json_encode(['review_id'=>$review,'scope'=>$row->action,
                    'status'=>$input['decision']==='APPROVE'?'APPROVED_FOR_RECONCILIATION':'REJECTED',
                    'estimate_stock_vendor_invoice_unchanged'=>true],JSON_THROW_ON_ERROR),
                'reason'=>trim($input['reason']),'actor_id'=>(int)$admin->id,
                'actor_name'=>mb_substr((string)($admin->email?:$admin->name?:'ADMIN'),0,180),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
        $message=$input['decision']==='APPROVE'
            ? 'Admin review APPROVED for reconciliation only. Actual Estimate/Parts/Vendor/Finance reversal has NOT been executed.'
            : 'Admin review REJECTED with audit reason. No underlying business record changed.';
        return redirect()->route('erp.job-cards.reversal',$job)->with('success',$message);
    }

METHOD;
    $C=p16one($C,'    public function cancel(Request $request,int $job):RedirectResponse {',
        $method.'    public function cancel(Request $request,int $job):RedirectResponse {','controller_action');

    $V=$before[$files[2]];
    $viewAnchor=' <section class="jcu-card">' . "\n" . '  <h2>Linked records &amp; cancellation safety</h2>';
    $approvalPanel=<<<'BLADE'
 {{-- P16_REVERSAL_APPROVAL_DESK: approval queue for Assam Motors Owner/Admin; no automatic reversals --}}
 <section class="jcu-card" id="p16-approval-desk" style="border-top:4px solid #225e9e">
  <h2>Admin Approval Desk — Reversal Requests</h2>
  <p class="jcu-small"><strong>Authorized approver:</strong> Active Assam Motors Admin/Owner account. Approving authorizes review/reconciliation only; it does NOT void an approved Estimate, refund a vendor, change stock, or delete a Job Card.</p>
  <div class="jcu-list">
  @forelse($reversalReviews as $review)
   <article class="jcu-item" id="p16-review-{{ $review['id'] }}">
    <strong>{{ $review['name'] }} · Request #{{ $review['id'] }}</strong>
    <div class="jcu-small">Submitted: {{ $review['requested_at'] }} · Decision: {{ $review['status']==='REQUESTED' ? 'AWAITING ADMIN' : $review['status'] }}</div>
    <p class="jcu-small">Reason: {{ $review['reason'] }}</p>
    @if($review['status']==='REQUESTED')
     <form method="post" class="jcu-form" action="{{ route('erp.job-cards.reversal.review-decision',[$card->id,$review['id']]) }}" onsubmit="return confirm('Record this Admin decision? No Estimate, stock, purchase, invoice or ledger adjustment will occur.')">
      @csrf
      <label>Confirm exact Request ID<input name="confirm_review_id" inputmode="numeric" required placeholder="{{ $review['id'] }}"></label>
      <label>Decision<select name="decision" required><option value="">Select decision</option><option value="APPROVE">Approve for Reconciliation</option><option value="REJECT">Reject Review</option></select></label>
      <label>Decision reason (12+ characters)<textarea name="reason" minlength="12" maxlength="500" required rows="2"></textarea></label>
      <label style="width:100%;font-weight:400"><input type="checkbox" name="acknowledge_no_reversal" value="1" required style="width:auto;min-height:auto"> I understand this records a decision only; actual business reversal requires a separate controlled workflow.</label>
      <button type="submit" class="jcu-action">Save Admin Approval Decision</button>
     </form>
    @elseif($review['status']==='APPROVED')
     <div class="jcu-note">Approved for review/reconciliation. <strong>Actual reversal remains pending.</strong> The original Estimate, stock, vendor and financial records are still preserved.</div>
    @elseif($review['status']==='ALREADY_CANCELLED')
     <div class="jcu-small">Job Card cancellation is complete; no second cancellation approval is needed.</div>
    @else
     <div class="jcu-note">Review rejected with reason: {{ $review['decision_reason'] }}</div>
    @endif
   </article>
  @empty
   <div class="jcu-item">No reversal approval requests registered yet.</div>
  @endforelse
  </div>
 </section>
BLADE;
    $V=p16one($V,$viewAnchor,$approvalPanel."\n".$viewAnchor,'approval_desk_section');
    $V=p16one($V,'Approval / reversal is still pending.',
      'Review approval: use the Admin Approval Desk above. Approval is separate from actual Estimate reversal.','estimate_pending_text');
    $V=p16one($V,'Grey = completed, orange = pending, blue = review requested (NOT yet reversed).',
      'Grey = completed, orange = pending, blue = review requested, and approval alone does not reverse records.','color_explanation');
    // Approved review is a distinct blue/purple state, NEVER marked completed.
    $V=str_replace('.r15-tile.r15-requested{background:#f1f6ff;border:2px solid #668fc1;color:#244f84}',
      '.r15-tile.r15-requested{background:#f1f6ff;border:2px solid #668fc1;color:#244f84}.r15-tile.r15-approved{background:#f4f0ff;border:2px solid #8673b9;color:#51428a}', $V);
    $V=str_replace('.r15-requested .r15-badge{background:#dbe9fb}',
      '.r15-requested .r15-badge{background:#dbe9fb}.r15-approved .r15-badge{background:#e2d9fa}', $V);
    $replacements=[
      <<<'OLD'
($stage['state']==='REQUESTED' ? 'r15-requested' : '')
OLD
      => <<<'NEW'
($stage['state']==='REQUESTED' ? 'r15-requested' : ($stage['state']==='APPROVED' ? 'r15-approved' : ''))
NEW
      ,
      <<<'OLD'
($stage['state']==='REQUESTED' ? 'REVIEW REQUESTED' : 'PENDING')
OLD
      => <<<'NEW'
($stage['state']==='REQUESTED' ? 'REVIEW REQUESTED' : ($stage['state']==='APPROVED' ? 'APPROVED - REVERSAL PENDING' : 'PENDING'))
NEW
      ,
      <<<'OLD'
($stageStates[3]['state']==='REQUESTED' ? 'REVIEW REQUESTED' : 'PENDING')
OLD
      => <<<'NEW'
($stageStates[3]['state']==='REQUESTED' ? 'REVIEW REQUESTED' : ($stageStates[3]['state']==='APPROVED' ? 'APPROVED - REVERSAL PENDING' : 'PENDING'))
NEW
    ];
    foreach($replacements as $from=>$to)$V=str_replace($from,$to,$V);
    $modified=[$files[0]=>$R,$files[1]=>$C,$files[2]=>$V];
    token_get_all($R,TOKEN_PARSE);
    token_get_all($C,TOKEN_PARSE);
    chdir($root);
    require 'vendor/autoload.php';
    $app=require 'bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    token_get_all(app('blade.compiler')->compileString($V),TOKEN_PARSE);
    foreach($files as $f){
        echo "P16_PRECHECK ".$f." OLD=".hash('sha256',$before[$f])." NEW=".hash('sha256',$modified[$f])."\n";
    }
    echo "P16_COMPILED_BLADE_AND_CONTROLLER_PASS\n";
} catch(Throwable $e){
    fwrite(STDERR,"P16_NO_GO_PREFLIGHT: ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){
    echo "P16_DRY_RUN_PASS_NO_SOURCE_OR_DATA_WRITES\n";exit(0);
}
$backup=$root.'/storage/app/p16_approval_desk_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"P16_NO_GO_BACKUP_DIRECTORY\n");exit(6);}
foreach($files as $f){
    $dest=$backup.'/'.str_replace('/','__',$f);
    if(!copy($root.'/'.$f,$dest)||hash_file('sha256',$dest)!==hash('sha256',$before[$f])){
        fwrite(STDERR,"P16_NO_GO_BACKUP_VERIFY\n");exit(7);
    }
}
$written=[];
try{
    foreach($files as $f){
        $dest=$root.'/'.$f;
        if(hash_file('sha256',$dest)!==hash('sha256',$before[$f]))throw new RuntimeException('Concurrent source change '.$f);
        $tmp=tempnam(dirname($dest),'.p16_');
        if(!$tmp||file_put_contents($tmp,$modified[$f],LOCK_EX)!==strlen($modified[$f]))throw new RuntimeException('Temp write failed');
        chmod($tmp,fileperms($dest)&0777);
        if(!rename($tmp,$dest))throw new RuntimeException('Atomic replace failed');
        $written[]=$f;
        if(hash_file('sha256',$dest)!==hash('sha256',$modified[$f]))throw new RuntimeException('New source hash mismatch');
    }
    echo "P16_APPLIED_BACKUP=".$backup."\n";
    echo "P16_SOURCE_ONLY_NO_BUSINESS_RECORD_MUTATIONS\n";
}catch(Throwable $e){
    foreach(array_reverse($written) as $f){
        copy($backup.'/'.str_replace('/','__',$f),$root.'/'.$f);
    }
    fwrite(STDERR,"P16_ROLLBACK_EXECUTED: ".$e->getMessage()."\n");exit(8);
}