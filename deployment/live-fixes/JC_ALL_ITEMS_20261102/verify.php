<?php
/**
 * Read-only STAGING acceptance test for JC-20261016-00035.
 * Renders the actual updated Blade from the existing JobCardService source.
 * No DB, JC, invoice, estimate or ledger writes.
 */
declare(strict_types=1);
$root=getcwd();
if (!is_file($root.'/artisan')) {echo "STOP_NOT_APP_ROOT\n";exit(2);}
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function failAllLines(string $reason): never {
    echo "AM_ALL_LINES_QA_FAIL_".$reason."\n";
    exit(4);
}
try {
    $service=$app->make(\App\Services\JobCardService::class);
    $data=$service->detail(35);
    if (($data['job']->job_no ?? '')!=='JC-20261016-00035') failAllLines('WRONG_JOB_ID');
    $parts=$data['parts'];
    $labour=$data['labour'];
    $expectedParts=$parts->count();
    $expectedLabour=$labour->filter(fn($l)=>!isset($l->billing_included)||(int)$l->billing_included===1)->count();
    $html=view('job-cards.summary-print',$data)->render();
    if (!str_contains($html,'AM_JC_ALL_LINES_FULL_SUMMARY_20261102')) failAllLines('MARKER_MISSING');
    if (!preg_match('~Parts & Consumables.*?<tbody>(.*?)</tbody>~s',$html,$pm)) failAllLines('PARTS_TABLE_MISSING');
    if (!preg_match('~Labour / ROT & OSL.*?<tbody>(.*?)</tbody>~s',$html,$lm)) failAllLines('LABOUR_TABLE_MISSING');
    $actualParts=preg_match_all('~<tr\b~',$pm[1]);
    $actualLabour=preg_match_all('~<tr\b~',$lm[1]);
    if ($expectedParts!==$actualParts) failAllLines('PARTS_ROWS_'.$actualParts.'_EXPECTED_'.$expectedParts);
    if ($expectedLabour!==$actualLabour) failAllLines('LABOUR_ROWS_'.$actualLabour.'_EXPECTED_'.$expectedLabour);
    if (str_contains($html,'condensed for one-page') || str_contains($html,'Other 9 Part line') || str_contains($html,'Other 10 Labour line')) {
        failAllLines('STILL_TRUNCATED');
    }
    if (!str_contains($html,'Job Card Grand Total')) failAllLines('GRAND_TOTAL_MISSING');
    echo "AM_ALL_LINES_JOB_35_PARTS=".$actualParts."\n";
    echo "AM_ALL_LINES_JOB_35_BILLABLE_LABOUR=".$actualLabour."\n";
    echo "AM_ALL_LINES_RUNTIME_RENDER_PASS_NO_DATA_WRITES\n";
} catch (\Throwable $e) {
    echo "AM_ALL_LINES_QA_EXCEPTION_CLASS=".get_class($e)."\n";
    echo "AM_ALL_LINES_QA_EXCEPTION_HINT=".substr($e->getMessage(),0,200)."\n";
    exit(3);
}
