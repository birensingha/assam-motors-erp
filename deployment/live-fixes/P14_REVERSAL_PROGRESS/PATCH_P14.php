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
 @endphp
 <section class="jcu-card" style="border-top:4px solid #1674ad" aria-label="Reversal progress and safety">
  <h2>Reversal progress &amp; safe next actions</h2>
  <p class="jcu-small">A ROT reporting void is not a Job Card deletion or an Estimate/Parts/Purchase reversal. Every linked obligation needs a separate reviewed action.</p>
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
