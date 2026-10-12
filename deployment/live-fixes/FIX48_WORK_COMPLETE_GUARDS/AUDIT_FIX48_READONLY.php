<?php
declare(strict_types=1);
/**
 * FIX48 READ-ONLY staging audit. Never alters tables, source, invoices or ledger.
 * Run from staging Laravel APP_ROOT: php /path/to/AUDIT_FIX48_READONLY.php
 * Does not emit names, vehicle registrations or row-level financial data.
 */
if (!is_file('artisan') || !is_file('vendor/autoload.php')) {
    fwrite(STDERR, "Run from the staging Laravel APP_ROOT\n"); exit(2);
}
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

foreach(['job_cards','invoices','customer_ledger','rot_sessions','job_card_parts','job_card_labour'] as $t){
    echo 'TABLE_'.strtoupper($t).'='.(Schema::hasTable($t)?'PRESENT':'MISSING')."\n";
}
foreach(['job_cards','invoices','customer_ledger','workshop_qc_checks','invoice_line_snapshots'] as $t){
    if(Schema::hasTable($t)) echo 'COUNT_'.strtoupper($t).'='.DB::table($t)->count()."\n";
}
if(Schema::hasTable('job_cards')){
    $cols=['status','inspection_status','diagnosis_status','decision_status','customer_public_status','invoice_id'];
    foreach($cols as $col)echo 'JOB_COL_'.strtoupper($col).'='.(Schema::hasColumn('job_cards',$col)?'PRESENT':'MISSING')."\n";
    if(count(array_filter(array_slice($cols,0,4),fn($x)=>Schema::hasColumn('job_cards',$x)))===4){
        $wip=DB::table('job_cards')->whereRaw("LOWER(TRIM(COALESCE(status,''))) IN ('in-progress','in progress','wip','work in progress')");
        echo 'WIP_TOTAL='.(clone $wip)->count()."\n";
        echo 'WIP_WITH_UNFINISHED_DIAGNOSIS='.(clone $wip)->where(function($q){
            $q->whereRaw("LOWER(TRIM(COALESCE(inspection_status,''))) NOT IN ('completed','skipped')")
              ->orWhereRaw("LOWER(TRIM(COALESCE(diagnosis_status,''))) <> 'completed'")
              ->orWhereRaw("LOWER(TRIM(COALESCE(decision_status,''))) <> 'completed'");
        })->count()."\n";
    }
}
if(Schema::hasTable('invoices')){
    foreach(['job_card_id','grand_total','status'] as $col)
        echo 'INVOICE_COL_'.strtoupper($col).'='.(Schema::hasColumn('invoices',$col)?'PRESENT':'MISSING')."\n";
    if(Schema::hasTable('job_cards') && Schema::hasColumn('invoices','job_card_id')){
        echo 'UNLINKED_INVOICES='.DB::table('invoices as i')
            ->leftJoin('job_cards as j','i.job_card_id','=','j.id')
            ->whereNull('j.id')->count()."\n";
    }
}
if(Schema::hasTable('rot_sessions'))
    echo 'ACTIVE_ROT_COUNT='.DB::table('rot_sessions')
      ->whereRaw("LOWER(TRIM(COALESCE(status,''))) IN ('assigned','running','paused')")->count()."\n";
$cpath='app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php';
$vpath='resources/views/job-cards/lifecycle-panel.blade.php';
foreach([$cpath,$vpath] as $p){
    echo 'SOURCE_SHA256 '.$p.' '.(is_file($p)?hash_file('sha256',$p):'MISSING')."\n";
}
$c=is_file($cpath)?file_get_contents($cpath):'';
$v=is_file($vpath)?file_get_contents($vpath):'';
echo 'FIX47_MARKER='.((str_contains($c,'FIX47_QC_FAILED_ROT_SESSION') && str_contains($v,'FIX47_QC_FAILED_ROT_SESSION'))?'PRESENT':'MISSING')."\n";
echo 'FIX48_MARKER='.((str_contains($c,'FIX48_WORK_COMPLETE_GUARDS') && str_contains($v,'FIX48_WORK_COMPLETE_GUARDS'))?'PRESENT':'MISSING')."\n";
echo "AUDIT_FIX48_READONLY=PASS\n";
