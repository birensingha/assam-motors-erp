<?php
declare(strict_types=1);
/**
 * Assam Motors FIX49 — READ ONLY diagnostic for WIP diagnosis / ROT Pause /
 * additional-work estimate deadlock. Run in staging Laravel APP_ROOT.
 * No credentials or customer/vehicle/financial fields are printed.
 * Usage: php /path/to/AUDIT_FIX49_READONLY.php JC-2026-0011
 */
if (PHP_SAPI !== 'cli' || !is_file('artisan') || !is_file('vendor/autoload.php')) {
    fwrite(STDERR, "ERROR: Run inside STAGING Laravel application root.\n"); exit(2);
}
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$jobNo = trim((string)($argv[1] ?? 'JC-2026-0011'));
if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $jobNo)) {
    fwrite(STDERR, "ERROR: Invalid job number.\n"); exit(2);
}
if (!Schema::hasTable('job_cards')) { echo "ERROR=JOB_CARDS_TABLE_MISSING\n"; exit(3); }
$job = DB::table('job_cards')->where('job_no',$jobNo)->first();
if (!$job) { echo "ERROR=JOB_CARD_NOT_FOUND\n"; exit(4); }
$id=(int)$job->id;
function showColumns(string $label, object $obj, array $columns): void {
    foreach($columns as $column) {
        if (property_exists($obj,$column)) {
            $v=$obj->$column;
            echo $label.'_'.strtoupper($column).'='.(is_null($v)?'NULL':str_replace(["\n","\r"],' ',(string)$v))."\n";
        }
    }
}
echo "FIX49_READONLY_AUDIT=BEGIN\n";
echo "JOB_NO=$jobNo\n";
echo "JOB_ID=$id\n";
showColumns('JOB',$job,['status','inspection_status','diagnosis_status','decision_status','estimate_allowed','current_estimate_id','work_started_at']);
foreach ([
    'rot_sessions'=>['id','status','job_card_labour_id','labour_id','rot_code','staff_id','first_start_at','start_time','end_time','updated_at'],
    'job_card_labour'=>['id','rot_session_id','execution_status','rot_code','mechanic_name'],
    'job_card_parts'=>['id','issue_status'],
    'job_estimates'=>['id','estimate_kind','status','converted_to_job_at','current_flag'],
    'workshop_job_estimates'=>['id','estimate_kind','status','converted_to_job_at','current_flag'],
] as $table=>$fields) {
    if(!Schema::hasTable($table) || !Schema::hasColumn($table,'job_card_id')) {
        echo 'TABLE_'.strtoupper($table)."=NOT_APPLICABLE\n";
        continue;
    }
    $columns=array_values(array_filter($fields, fn($col)=>Schema::hasColumn($table,$col)));
    $rows=DB::table($table)->where('job_card_id',$id)->orderBy('id')->limit(60)->get($columns);
    echo 'COUNT_'.strtoupper($table).'='.$rows->count()."\n";
    $idx=0;
    foreach($rows as $row) {
        $idx++;
        $base=strtoupper($table).'_'.$idx;
        showColumns($base,$row,$columns);
    }
}
foreach([
 'app/Services/RotWorkflowService.php',
 'app/Services/EstimateService.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'app/Http/Controllers/Web/AdminEstimatePageController.php',
 'resources/views/job-cards/show.blade.php',
] as $path) {
    echo 'SOURCE_SHA256 '.$path.' '.(is_file($path)?hash_file('sha256',$path):'MISSING')."\n";
}
if (is_file('app/Services/EstimateService.php')) {
    $src=file_get_contents('app/Services/EstimateService.php');
    echo 'SUPPLEMENTARY_GATE_MARKER='.(str_contains($src,'Supplementary Estimate requires a previously approved Estimate')?'PRESENT':'MISSING')."\n";
}
if (is_file('app/Http/Controllers/Web/AdminJobCardPageController.php')) {
    $src=file_get_contents('app/Http/Controllers/Web/AdminJobCardPageController.php');
    echo 'DIAGNOSIS_WIP_BLOCK_MARKER='.(str_contains($src,'Diagnosis can be completed only before Work In Progress')?'PRESENT':'MISSING')."\n";
}
echo "FIX49_READONLY_AUDIT=PASS\n";
