<?php
declare(strict_types=1);
/**
 * P14 REVERSAL CENTRE STATUS: visibility-only staging improvement.
 * Does not mutate job cards, ROT sessions, stock, estimates, invoices or ledger.
 * Usage: php PATCH_P14.php check|apply [staging_laravel_root]
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??(rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging')),'/');
if (!in_array($mode,['check','apply'],true) || !is_file($root.'/artisan')) {
    fwrite(STDERR,"Usage: php PATCH_P14.php check|apply /laravel/root\n");exit(2);
}
$relative='resources/views/job-cards/unwind.blade.php';
$target=$root.'/'.$relative;
if (!is_file($target) || is_link($target)) {
    fwrite(STDERR,"NO-GO: canonical Reversal Centre view not found or is symlink\n");exit(2);
}
$before=file_get_contents($target);
if ($before===false) { fwrite(STDERR,"NO-GO: unable to read source\n");exit(2); }
$marker='P14_REVERSAL_PROGRESS_STATUS';
if (str_contains($before,$marker)) {
    echo "P14_ALREADY_INSTALLED: no source changes\n";exit(0);
}
if (!str_contains($before,'Void ROT from HR Productivity')
    || !str_contains($before,'$voidedRotIds')
    || !str_contains($before,'Linked records &amp; cancellation safety')) {
    fwrite(STDERR,"NO-GO: P13 Reversal Centre prerequisites missing\n");exit(3);
}
$anchor=<<<'ANCHOR'
 <section class="jcu-card">
  <h2>Linked records &amp; cancellation safety</h2>
ANCHOR;
$panel=<<<'BLADE'
 {{-- P14_REVERSAL_PROGRESS_STATUS — read-only summary; no automatic reversal or deletion --}}
 @php
   $p14VoidedCount=count($voidedRotIds ?? []);
   $p14RotCount=(int)($counts['rot_sessions'] ?? 0);
   $p14EstimateCount=(int)($counts['job_estimates'] ?? 0);
   $p14PurchaseCount=(int)($counts['workshop_purchases'] ?? 0);
   $p14StockCount=(int)($counts['inventory_movements'] ?? 0);
   $p14FinanceCount=(int)($counts['invoices'] ?? 0)+(int)($counts['customer_ledger'] ?? 0)+(int)($counts['customer_payments'] ?? 0);
   $p14Cancelled=strtolower(trim((string)$card->status))==='cancelled';

   $p14VoidMap=array_fill_keys(array_map('strval',$voidedRotIds ?? []),true);
   $p14UnresolvedRot=0;
   foreach(($rotSessions ?? collect()) as $rs) {
      if(!isset($p14VoidMap[(string)$rs->id])) $p14UnresolvedRot++;
   }
   $p14UnresolvedLabour=0;
   foreach($labour as $line) {
      if(empty($line->rot_session_id) || !isset($p14VoidMap[(string)$line->rot_session_id])) $p14UnresolvedLabour++;
   }
   // Handled means HR reporting exclusion done. Original Labour/ROT history stays.
   $p14Step1Done=$p14Cancelled && $p14UnresolvedRot===0 && $p14UnresolvedLabour===0;
   $p14Step2Done=$parts->isEmpty();
   $p14Step3Done=$estimates->every(fn($e)=>in_array(strtolower(trim((string)$e->status)),['cancelled','voided','reversed'],true));
   $p14Step4Done=$p14Cancelled;
   // Retained Purchase/Stock/Finance records need verified reconciliation, not auto-clear.
   $p14Step5Done=$p14Step1Done && $p14Step2Done && $p14Step3Done && $p14Step4Done
       && $p14PurchaseCount===0 && $p14StockCount===0 && $p14FinanceCount===0;
   $p14Stages=[
      ['id'=>1,'title'=>'Labour / ROT','done'=>$p14Step1Done,'note'=>'ROT HR-reporting void / Labour review'],
      ['id'=>2,'title'=>'Allotted Parts','done'=>$p14Step2Done,'note'=>'Active Job Card part allocations'],
      ['id'=>3,'title'=>'Estimate Reversal','done'=>$p14Step3Done,'note'=>'Approved / released Estimate review'],
      ['id'=>4,'title'=>'Cancel / Archive','done'=>$p14Step4Done,'note'=>'Job Card cancellation status'],
      ['id'=>5,'title'=>'Final Reconciliation','done'=>$p14Step5Done,'note'=>'Purchase / Stock / Finance checks'],
   ];
   $p14DoneCount=count(array_filter($p14Stages,fn($x)=>$x['done']));
 @endphp
 <section class="jcu-card" style="border-top:4px solid #1674ad" aria-label="Reversal progress and safety">
  <h2>Reversal progress &amp; safe next actions</h2>
  <p class="jcu-small">A ROT reporting void is not a Job Card deletion or an Estimate/Parts/Purchase reversal. Every linked obligation needs a separate reviewed action.</p>

  <style>
    .p14-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:9px;margin:14px 0}
    .p14-link{display:block;text-decoration:none;border-radius:12px;border:2px solid #e6a24a;background:#fff7e8;color:#7d4812;padding:12px}
    .p14-link.p14-done{border:1px solid #e1e5ea;background:#f3f5f7;color:#78818c}
    .p14-link strong{display:block;font-size:13px}.p14-link small{display:block;font-size:11px;margin-top:5px}
    .p14-status{display:inline-block;font-size:11px;font-weight:800;border-radius:12px;padding:4px 9px;margin-top:7px}
    .p14-status.p14-done{background:#e5e9ec;color:#66717d}.p14-status.p14-needed{background:#fce5c7;color:#8a4908}
    .jcu-card.p14-complete{background:#f4f6f8;border-color:#e0e5ea;box-shadow:none}
    .jcu-card.p14-complete h2{color:#77828e}.jcu-card.p14-pending{border:2px solid #e8a242;box-shadow:0 4px 16px #e8a2421c}
    .p14-complete .jcu-form{opacity:.65}
    @media(max-width:640px){.p14-steps{grid-template-columns:1fr}}
  </style>
  <h3 style="margin:16px 0 5px">5-point Reversal Checklist · {{ $p14DoneCount }}/5 handled</h3>
  <p class="jcu-small">Grey = handled, orange = pending. Status is recalculated whenever this page loads. Existing history is preserved.</p>
  <nav class="p14-steps" aria-label="Reversal checklist">
   @foreach($p14Stages as $p14Step)
    <a href="#p14-step-{{ $p14Step['id'] }}" class="p14-link {{ $p14Step['done'] ? 'p14-done' : 'p14-needed' }}">
     <strong>{{ $p14Step['id'] }}. {{ $p14Step['title'] }}</strong>
     <span class="p14-status {{ $p14Step['done'] ? 'p14-done' : 'p14-needed' }}">{{ $p14Step['done'] ? 'HANDLED' : 'PENDING' }}</span>
     <small>{{ $p14Step['note'] }}</small>
    </a>
   @endforeach
  </nav>

  <div class="jcu-grid">
   <div class="jcu-box"><span class="jcu-small">Job Card</span><b>{{ $p14Cancelled ? 'CANCELLED — ARCHIVED' : strtoupper((string)$card->status) }}</b></div>
   <div class="jcu-box"><span class="jcu-small">ROT HR-reporting void</span><b>{{ $p14VoidedCount }} of {{ $p14RotCount }}</b><small>Original ROT records remain in history</small></div>
   <div class="jcu-box"><span class="jcu-small">Estimate history</span><b>{{ $p14EstimateCount ? 'REVIEW REQUIRED' : 'NONE LINKED' }}</b><small>{{ $p14EstimateCount }} saved version(s)</small></div>
   <div class="jcu-box"><span class="jcu-small">Supplier purchase</span><b>{{ $p14PurchaseCount ? 'VENDOR RECONCILIATION' : 'NONE LINKED' }}</b><small>{{ $p14PurchaseCount }} purchase record(s)</small></div>
   <div class="jcu-box"><span class="jcu-small">Stock movements</span><b>{{ $p14StockCount ? 'HISTORY RETAINED' : 'NONE' }}</b><small>{{ $p14StockCount }} movement(s)</small></div>
   <div class="jcu-box"><span class="jcu-small">Customer finance</span><b>{{ $p14FinanceCount ? 'FINANCE REVIEW REQUIRED' : 'NO DIRECT LINKS' }}</b><small>Invoices, payments and ledger checked separately</small></div>
  </div>
  @if($p14Cancelled)
   <div class="jcu-note"><strong>Cancelled Archive is the safe current state.</strong> Never use Delete Empty Job Card to remove a cancelled Job Card with linked work, Estimate, Purchase or stock history. The original records remain for audit.</div>
  @elseif($p14RotCount || $p14EstimateCount || $p14PurchaseCount || $p14StockCount || $p14FinanceCount)
   <div class="jcu-note"><strong>Permanent deletion is not available while linked history exists.</strong> Use the verified return, accounting reconciliation and cancellation workflow below.</div>
  @endif
  @if($p14VoidedCount)
   <p class="jcu-small" style="margin-top:10px">Already voided ROT(s): existing audit is preserved. The Void ROT action must not be repeated for those sessions.</p>
  @endif
 </section>
BLADE;
if (substr_count($before,$anchor)!==1) {
    fwrite(STDERR,"NO-GO: status panel insertion anchor changed\n");exit(3);
}
$after=str_replace($anchor,$panel."\n".$anchor,$before);

function p14ReplaceOnce(string $s,string $old,string $new,string $label):string {
    $count=substr_count($s,$old);
    if($count!==1)throw new RuntimeException('P14_ANCHOR_MISMATCH_'.$label.'='.$count);
    return str_replace($old,$new,$s);
}
$sections=[
  ['id'=>1,'done'=>'$p14Step1Done','heading'=>'1 · Labour / ROT removal','prefix'=>' <section class="jcu-card">'],
  ['id'=>2,'done'=>'$p14Step2Done','heading'=>'2 · Allotted Parts / Stock Return','prefix'=>' <section class="jcu-card">'],
  ['id'=>3,'done'=>'$p14Step3Done','heading'=>'3 · Estimate Reversal','prefix'=>' <section class="jcu-card">'],
  ['id'=>4,'done'=>'$p14Step4Done','heading'=>'4 · Cancel &amp; archive the Job Card safely','prefix'=>' <section class="jcu-card" style="border-top:5px solid #e25b22">'],
  ['id'=>5,'done'=>'$p14Step5Done','heading'=>'5 · Full reversal / cancellation review (any status)','prefix'=>' <section class="jcu-card">'],
];
foreach($sections as $step){
  $head=$step['prefix']."\n  <h2>".$step['heading']."</h2>";
  $when=$step['done'];
  $out=' <section id="p14-step-'.$step['id'].'" class="jcu-card {{ '.$when.' ? \'p14-complete\' : \'p14-pending\' }}"';
  if($step['id']===4)$out.=' style="border-top:5px solid #e25b22"';
  $badge=<<<'BADGE'
 <span class="p14-status {{ %1$s ? 'p14-done' : 'p14-needed' }}">{{ %1$s ? 'HANDLED' : 'PENDING' }}</span>
BADGE;
  $out.=">\n  <h2>".$step['heading'].sprintf($badge,$when)."</h2>";
  $after=p14ReplaceOnce($after,$head,$out,'STEP_'.$step['id']);
}

if (substr_count($after,$marker)!==1) {
    fwrite(STDERR,"NO-GO: replacement integrity failed\n");exit(3);
}
echo "P14_CHECK=PASS PRE_SHA256=".hash('sha256',$before)." POST_SHA256=".hash('sha256',$after)."\n";
if ($mode==='check') { echo "P14_DRY_RUN_PASS_NO_WRITES\n";exit(0); }
$dir=$root.'/storage/app/p14_reversal_view_backup_'.date('Ymd_His').'_'.getmypid();
if (!mkdir($dir,0700,true)) { fwrite(STDERR,"NO-GO: backup directory\n");exit(4); }
$backup=$dir.'/unwind.blade.php';
if (!copy($target,$backup) || hash_file('sha256',$backup)!==hash('sha256',$before)) {
    fwrite(STDERR,"NO-GO: immutable backup verification failed\n");exit(4);
}
if (hash_file('sha256',$target)!==hash('sha256',$before)) {
    fwrite(STDERR,"NO-GO: source changed concurrently\n");exit(4);
}
$tmp=tempnam(dirname($target),'.p14_');
if (!$tmp) { fwrite(STDERR,"NO-GO: temporary file\n");exit(4); }
try {
    if (file_put_contents($tmp,$after,LOCK_EX)!==strlen($after)) throw new RuntimeException("staging write incomplete");
    chmod($tmp,fileperms($target)&0777);
    if (!rename($tmp,$target)) throw new RuntimeException("atomic view replacement failed");
    if (hash_file('sha256',$target)!==hash('sha256',$after)) throw new RuntimeException("post-write verification failed");
    echo "P14_APPLY_PASS BACKUP=".$backup."\n";
    echo "NO_DB_WRITES_NO_ROT_VOID_NO_PURCHASE_REVERSAL_NO_DELETE\n";
} catch (Throwable $e) {
    @unlink($tmp);
    if (is_file($backup)) copy($backup,$target);
    fwrite(STDERR,"P14_ROLLBACK_EXECUTED: ".$e->getMessage()."\n");exit(5);
}
