<?php
declare(strict_types=1);
/**
 * P15 five-point reversal progress — no financial, stock or ROT mutations.
 * Edits only the Reversal Centre controller and Blade view after verifying anchors,
 * backups, compiled Blade syntax and the checked source hashes.
 * Execute from checked-out package: php PATCH_P15.php check|apply /staging-laravel-root
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if (!in_array($mode,['check','apply'],true) || !is_file($root.'/artisan')) {
    fwrite(STDERR,"Usage: php PATCH_P15.php check|apply /staging-laravel-root\n");exit(2);
}
$controller='app/Http/Controllers/Web/AdminJobCardUnwindController.php';
$view='resources/views/job-cards/unwind.blade.php';
$paths=[$controller,$view];
$src=[];
foreach($paths as $p){
    if(!is_file($root.'/'.$p) || is_link($root.'/'.$p)) {
        fwrite(STDERR,"NO_GO missing regular source: ".$p."\n");exit(3);
    }
    $src[$p]=file_get_contents($root.'/'.$p);
    if($src[$p]===false)exit(3);
}
if(str_contains($src[$controller],'P15_REVERSAL_TRACKER') || str_contains($src[$view],'P15_REVERSAL_TRACKER')){
    if(str_contains($src[$controller],'P15_REVERSAL_TRACKER') && str_contains($src[$view],'P15_REVERSAL_TRACKER')){
        echo "P15_ALREADY_INSTALLED_NOOP\n";exit(0);
    }
    fwrite(STDERR,"NO_GO partial P15 installation detected\n");exit(4);
}
function p15replace(string $s,string $needle,string $replacement,string $name):string{
    $count=substr_count($s,$needle);
    if($count!==1)throw new RuntimeException("NO_GO anchor ".$name." count=".$count);
    return str_replace($needle,$replacement,$s);
}
try{
    $C=$src[$controller];
    $anchor=<<<'OLD'
        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested','rotSessions','voidedRotIds'));
OLD;
    $new=<<<'NEW'
        // P15_REVERSAL_TRACKER. A review request is not a completed reversal.
        $reviewActions=[
            'ESTIMATE'=>'REVERSAL_REVIEW_ESTIMATE',
            'CANCEL'=>'CANCEL_REVIEW_REQUESTED',
            'ROT'=>'REVERSAL_REVIEW_ROT',
            'PARTS'=>'REVERSAL_REVIEW_PARTS',
            'FINANCE'=>'REVERSAL_REVIEW_FINANCE',
        ];
        $countsByAction=DB::table('job_card_change_audits')
            ->where('job_card_id',$job)->whereIn('action',array_values($reviewActions))
            ->select('action',DB::raw('COUNT(*) as total'))->groupBy('action')->get()
            ->pluck('total','action');
        $reviewCounts=[];
        foreach($reviewActions as $scope=>$action){
            $reviewCounts[$scope]=(int)($countsByAction[$action]??0);
        }
        $voidMap=array_fill_keys(array_map('strval',$voidedRotIds),true);
        $rotHandled=$rotSessions->every(
            fn($session)=>isset($voidMap[(string)$session->id])
        );
        $labourHandled=$labour->every(
            fn($line)=>!empty($line->rot_session_id) && isset($voidMap[(string)$line->rot_session_id])
        );
        $step1= $rotHandled && $labourHandled;
        // Stock returns must balance actual issues; Purchase Stock-In is history, not a return.
        $issued=[];$returned=[];$unidentifiedStock=false;
        if(Schema::hasTable('inventory_movements')){
            $stockRows=DB::table('inventory_movements')->where('job_card_id',$job)->get();
            foreach($stockRows as $row){
                $kind=strtoupper(trim((string)($row->movement_type??'')));
                $key=(string)($row->part_master_id??'');
                if($kind==='JOB_ISSUE')$issued[$key]=($issued[$key]??0)+(float)($row->qty_out??0);
                elseif($kind==='JOB_PART_DELETE_REVERSAL')$returned[$key]=($returned[$key]??0)+(float)($row->qty_in??0);
                elseif($kind!=='PURCHASE_IN')$unidentifiedStock=true;
            }
        } else $unidentifiedStock=true;
        foreach(array_unique(array_merge(array_keys($issued),array_keys($returned))) as $key){
            if(abs(($issued[$key]??0)-($returned[$key]??0))>0.0001)$unidentifiedStock=true;
        }
        $step2=$parts->isEmpty() && !$unidentifiedStock;
        $step3=$estimates->every(fn($e)=>in_array(
            strtolower(trim((string)$e->status)),['cancelled','voided','reversed'],true
        ));
        $step4=strtolower(trim((string)$card->status))==='cancelled';
        $financeLinked=((int)$counts['invoices']+(int)$counts['customer_payments']+
            (int)$counts['customer_ledger']+(int)$counts['workshop_payment_vouchers'])>0;
        // Retained purchase rows may represent outstanding vendor liability.
        // Do not classify as completed until independently reconciled.
        $step5=$step1 && $step2 && $step3 && $step4 && !$financeLinked
            && (int)$counts['workshop_purchases']===0
            && (int)$counts['workshop_osl_requests']===0;
        $anyReviews=array_sum($reviewCounts)>0;
        $stageStates=[
          1=>['name'=>'Labour / ROT','state'=>$step1?'DONE':($reviewCounts['ROT']?'REQUESTED':'PENDING'),
               'detail'=>$step1?'ROT reporting action handled; original work history retained.':'ROT or linked Labour still needs review.'],
          2=>['name'=>'Allotted Parts / Stock Return','state'=>$step2?'DONE':($reviewCounts['PARTS']?'REQUESTED':'PENDING'),
               'detail'=>$step2?'No active allocation and job stock issues balanced.':'Part allocation or stock issue needs verification.'],
          3=>['name'=>'Estimate Reversal','state'=>$step3?'DONE':($reviewCounts['ESTIMATE']?'REQUESTED':'PENDING'),
               'detail'=>$step3?'No active Estimate reversal outstanding.':($reviewCounts['ESTIMATE']?
                 'Review requested '.(string)$reviewCounts['ESTIMATE'].' time(s); approved Estimate remains.':'Approved Estimate requires authorized review.')],
          4=>['name'=>'Cancel / Archive','state'=>$step4?'DONE':'PENDING',
               'detail'=>$step4?'Job Card cancelled and archived.':'Job Card cancellation not yet completed.'],
          5=>['name'=>'Final Reconciliation','state'=>$step5?'DONE':($anyReviews?'REQUESTED':'PENDING'),
               'detail'=>$step5?'No unresolved linked obligations detected.':($anyReviews?
                 'Review request recorded; actual settlement pending.':'Final stock, purchase, finance and estimate checks remain.')],
        ];
        $stageDone=count(array_filter($stageStates,fn($s)=>$s['state']==='DONE'));
        $stagePending=5-$stageDone;
        $reviewChoices=[
            'CANCEL'=>'Full Job Card Cancellation',
            'ROT'=>'Completed ROT Review',
            'PARTS'=>'Parts / Stock Review',
            'FINANCE'=>'Invoice / Payment / Purchase Review',
        ];
        foreach($reviewChoices as $scope=>$label){
            if($reviewCounts[$scope]>0 || ($scope==='CANCEL' && $step4))unset($reviewChoices[$scope]);
        }
        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested','rotSessions','voidedRotIds',
            'reviewCounts','stageStates','stageDone','stagePending','reviewChoices'));
NEW;
    $C=p15replace($C,$anchor,$new,'controller_view_return');

    $V=$src[$view];
    $needle=<<<'OLD'
 @if(session('success'))<div class="jcu-card" style="border-left:4px solid #16a34a">{{ session('success') }}</div>@endif
OLD;
    $new=<<<'NEW'
 @if(session('success'))<div class="jcu-card" style="border-left:4px solid #16a34a">{{ session('success') }}</div>@endif
 {{-- P15_REVERSAL_TRACKER: status from read-only live database evidence --}}
 <style>
 .r15-head{background:#fff;border:1px solid #d9e3ec;border-radius:14px;padding:15px;margin:13px 0}
 .r15-head h2{font-size:19px;color:#103b5d;margin:0 0 5px}
 .r15-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:9px;margin-top:13px}
 .r15-tile{padding:12px;border:2px solid #e6a042;border-radius:12px;background:#fff8e9;color:#7d4712;text-decoration:none}
 .r15-tile.r15-done{background:#f2f4f6;border:1px solid #dce2e8;color:#77818a}
 .r15-tile.r15-requested{background:#f1f6ff;border:2px solid #668fc1;color:#244f84}
 .r15-tile strong{display:block;font-size:13px}.r15-tile small{display:block;font-size:11px;margin-top:6px}
 .r15-badge{display:inline-block;font-size:11px;font-weight:800;padding:4px 8px;border-radius:15px;background:#ffe5ba;margin-top:6px}
 .r15-done .r15-badge{background:#e0e4e9}.r15-requested .r15-badge{background:#dbe9fb}
 .jcu-card.r15-done{background:#f5f6f8;border:1px solid #dfe4ea;box-shadow:none}
 .jcu-card.r15-done h2{color:#7b858f}
 .jcu-card.r15-pending{border:2px solid #e6a042;box-shadow:0 2px 12px #e6a04218}
 .jcu-card.r15-requested{border:2px solid #668fc1;box-shadow:0 2px 12px #668fc118}
 .r15-section-status{font-size:12px;border-radius:12px;padding:4px 9px;display:inline-block;background:#e6edf5;color:#32516b}
 .r15-info{border-radius:8px;padding:12px;background:#eff5ff;color:#244f84;border-left:4px solid #668fc1;margin-top:10px}
 @media(max-width:640px){.r15-grid{grid-template-columns:1fr}}
 </style>
 <section class="r15-head" aria-label="Job Card reversal five point progress">
  <h2>5-point Job Card Reversal Progress — {{ $stageDone }}/5 handled</h2>
  <p class="jcu-small">Grey = completed, orange = pending, blue = review requested (NOT yet reversed). Status refreshes from the server on page reload.</p>
  <div class="r15-grid">
   @foreach($stageStates as $num=>$stage)
    <a class="r15-tile {{ $stage['state']==='DONE' ? 'r15-done' : ($stage['state']==='REQUESTED' ? 'r15-requested' : '') }}" href="#r15-step-{{ $num }}">
     <strong>{{ $num }}. {{ $stage['name'] }}</strong>
     <span class="r15-badge">{{ $stage['state']==='DONE' ? 'COMPLETE' : ($stage['state']==='REQUESTED' ? 'REVIEW REQUESTED' : 'PENDING') }}</span>
     <small>{{ $stage['detail'] }}</small>
    </a>
   @endforeach
  </div>
  <p class="jcu-small" style="margin-top:10px"><strong>{{ $stagePending }} remaining.</strong> Registered review requests never auto-clear Estimate, vendor bills or stock returns.</p>
 </section>
NEW;
    $V=p15replace($V,$needle,$new,'checklist_header');
    $names=[
       1=>'1 · Labour / ROT removal',
       2=>'2 · Allotted Parts / Stock Return',
       3=>'3 · Estimate Reversal',
       4=>'4 · Cancel &amp; archive the Job Card safely',
       5=>'5 · Full reversal / cancellation review (any status)'
    ];
    foreach($names as $num=>$title){
       $old='<h2>'.$title.'</h2>';
       $fresh='<h2>'.$title.' <span class="r15-section-status">{{ $stageStates['.$num."]['state']==='DONE' ? 'COMPLETE' : (\$stageStates[".$num."]['state']==='REQUESTED' ? 'REVIEW REQUESTED' : 'PENDING') }}</span></h2>";
       $V=p15replace($V,$old,$fresh,'heading_'.$num);
    }
    $replacements=[
      1=>'<section id="r15-step-1" class="jcu-card {{ $stageStates[1][\'state\']===\'DONE\' ? \'r15-done\' : ($stageStates[1][\'state\']===\'REQUESTED\' ? \'r15-requested\' : \'r15-pending\') }}">',
      2=>'<section id="r15-step-2" class="jcu-card {{ $stageStates[2][\'state\']===\'DONE\' ? \'r15-done\' : ($stageStates[2][\'state\']===\'REQUESTED\' ? \'r15-requested\' : \'r15-pending\') }}">',
      3=>'<section id="r15-step-3" class="jcu-card {{ $stageStates[3][\'state\']===\'DONE\' ? \'r15-done\' : ($stageStates[3][\'state\']===\'REQUESTED\' ? \'r15-requested\' : \'r15-pending\') }}">',
      4=>'<section id="r15-step-4" class="jcu-card {{ $stageStates[4][\'state\']===\'DONE\' ? \'r15-done\' : \'r15-pending\' }}" style="border-top:5px solid #e25b22">',
      5=>'<section id="r15-step-5" class="jcu-card {{ $stageStates[5][\'state\']===\'DONE\' ? \'r15-done\' : ($stageStates[5][\'state\']===\'REQUESTED\' ? \'r15-requested\' : \'r15-pending\') }}">',
    ];
    foreach($names as $n=>$title){
       $start=($n===4?'<section class="jcu-card" style="border-top:5px solid #e25b22">':'<section class="jcu-card">')."\n  <h2>".$title;
       $V=p15replace($V,$start,$replacements[$n]."\n  <h2>".$title,'section_'.$n);
    }
    $needle=<<<'OLD'
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf <input type="hidden" name="scope" value="ESTIMATE">
OLD;
    $new=<<<'NEW'
  @if($stageStates[3]['state']==='DONE')
   <div class="r15-info">Estimate action is already cleared. Original Estimate history is preserved for audit.</div>
  @elseif(($reviewCounts['ESTIMATE'] ?? 0)>0)
   <div class="r15-info">Estimate reversal review requested {{ $reviewCounts['ESTIMATE'] }} time(s). No further request needed. Approval / reversal is still pending.</div>
  @else
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf <input type="hidden" name="scope" value="ESTIMATE">
NEW;
    $V=p15replace($V,$needle,$new,'estimate_request_begin');
    $needle=<<<'OLD'
   <button class="jcu-action">Request Estimate Reversal Review</button>
  </form>
OLD;
    $new=<<<'NEW'
   <button class="jcu-action">Request Estimate Reversal Review</button>
  </form>
  @endif
NEW;
    $V=p15replace($V,$needle,$new,'estimate_request_end');
    $needle=<<<'OLD'
  <form method="post" action="{{ route('erp.job-cards.reversal.cancel',$card->id) }}" class="jcu-form" onsubmit="return confirm('Cancel this Job Card? All work, stock and accounting history will stay recorded. No financial reversal is automatic.')">
OLD;
    $new=<<<'NEW'
  @if($stageStates[4]['state']==='DONE')
   <div class="r15-info">Cancellation COMPLETE — this Job Card is already CANCELLED. Do not submit Cancel again. Linked Estimate, Purchase and history must still be reviewed separately.</div>
  @else
  <form method="post" action="{{ route('erp.job-cards.reversal.cancel',$card->id) }}" class="jcu-form" onsubmit="return confirm('Cancel this Job Card? All work, stock and accounting history will stay recorded. No financial reversal is automatic.')">
NEW;
    $V=p15replace($V,$needle,$new,'cancel_form_begin');
    $needle=<<<'OLD'
   <button class="jcu-action jcu-danger" type="submit">Cancel / Archive Job Card →</button>
  </form>
OLD;
    $new=<<<'NEW'
   <button class="jcu-action jcu-danger" type="submit">Cancel / Archive Job Card →</button>
  </form>
  @endif
NEW;
    $V=p15replace($V,$needle,$new,'cancel_form_end');
    $needle=<<<'OLD'
  <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
   @csrf
   <label>Review type<select name="scope" required>
    <option value="CANCEL">Full Job Card Cancellation</option><option value="ROT">Completed ROT Review</option>
    <option value="PARTS">Parts / Stock Review</option><option value="FINANCE">Invoice / Payment / Purchase Review</option>
   </select></label>
   <label>Reason<input name="reason" required minlength="12" maxlength="500"></label>
   <button class="jcu-action">Register Audited Review</button>
  </form>
OLD;
    $new=<<<'NEW'
  @if(($reviewCounts['CANCEL'] ?? 0) || ($reviewCounts['ROT'] ?? 0) || ($reviewCounts['PARTS'] ?? 0) || ($reviewCounts['FINANCE'] ?? 0))
   <div class="r15-info">
    Review requests saved: Cancellation {{ $reviewCounts['CANCEL'] }}, ROT {{ $reviewCounts['ROT'] }}, Parts {{ $reviewCounts['PARTS'] }}, Finance {{ $reviewCounts['FINANCE'] }}.
    <strong>Recorded is not completed.</strong> Pending reconciliation stays highlighted until independently approved and verified.
   </div>
  @endif
  @if(count($reviewChoices))
   <form method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}" class="jcu-form">
    @csrf
    <label>Review type<select name="scope" required>
      @foreach($reviewChoices as $value=>$label)
       <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select></label>
    <label>Reason<input name="reason" required minlength="12" maxlength="500"></label>
    <button class="jcu-action">Request Pending Review</button>
   </form>
  @else
   <p class="jcu-small">All available review request types already registered or not applicable. No duplicate request is required.</p>
  @endif
NEW;
    $V=p15replace($V,$needle,$new,'final_review_options');
    foreach(['P15_REVERSAL_TRACKER','r15-step-5','Review requested'] as $m){
        if(!str_contains($V,$m) && $m!=='Review requested')throw new RuntimeException('Missing view marker '.$m);
    }
    // Parse controller candidate before any writes.
    token_get_all($C,TOKEN_PARSE);
    $staging=[$controller=>$C,$view=>$V];
    echo "P15_PRECHECK=PASS\n";
    foreach($paths as $p)echo 'P15_CHECK '.$p.' OLD='.hash('sha256',$src[$p]).' NEW='.hash('sha256',$staging[$p])."\n";
} catch(Throwable $e){
    fwrite(STDERR,'P15_NO_GO_PRECHECK '.$e->getMessage()."\n");exit(5);
}
// Validate actual compiled Blade PHP rather than trusting view:cache only.
try{
    chdir($root);
    require 'vendor/autoload.php';
    $app=require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $compiled=app('blade.compiler')->compileString($V);
    token_get_all($compiled,TOKEN_PARSE);
    echo "P15_COMPILED_BLADE_PHP_LINT=PASS\n";
} catch(Throwable $e){
    fwrite(STDERR,"P15_NO_GO_BLADE_LINT ".$e->getMessage()."\n");exit(6);
}
if($mode==='check'){echo "P15_DRYRUN_NO_SOURCE_OR_DATA_MODIFICATION\n";exit(0);}
$backup=$root.'/storage/app/p15_reversal_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true))exit(7);
foreach($paths as $p){
    $b=$backup.'/'.str_replace('/','__',$p);
    if(!copy($root.'/'.$p,$b)||hash_file('sha256',$b)!==hash('sha256',$src[$p])){
        fwrite(STDERR,"P15_NO_GO_BACKUP_VERIFICATION\n");exit(8);
    }
}
$writes=[];
try{
    foreach($paths as $p){
        $dst=$root.'/'.$p;
        if(hash_file('sha256',$dst)!==hash('sha256',$src[$p]))throw new RuntimeException('Concurrent source change: '.$p);
        $t=tempnam(dirname($dst),'.p15_');
        if(!$t||file_put_contents($t,$staging[$p],LOCK_EX)!==strlen($staging[$p]))throw new RuntimeException('temporary file');
        chmod($t,fileperms($dst)&0777);
        if(!rename($t,$dst))throw new RuntimeException('atomic replace');
        $writes[]=$p;
        if(hash_file('sha256',$dst)!==hash('sha256',$staging[$p]))throw new RuntimeException('hash verify failed');
    }
    echo "P15_APPLIED_VIEW_AND_CONTROLLER_BACKUP=".$backup."\n";
    echo "P15_NO_BUSINESS_RECORDS_MUTATED\n";
} catch(Throwable $e){
    foreach(array_reverse($writes) as $p)copy($backup.'/'.str_replace('/','__',$p),$root.'/'.$p);
    fwrite(STDERR,"P15_INSTALL_ROLLBACK ".$e->getMessage()."\n");exit(9);
}
