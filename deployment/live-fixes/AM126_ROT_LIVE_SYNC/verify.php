<?php
declare(strict_types=1);
/** AM126 READ-ONLY rendering verification. No DB writes. */
if(!is_file(getcwd().'/artisan')){echo "AM126_NOT_STAGING\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

function reportFail(string $reason): never { echo "AM126_VERIFY_FAIL_".$reason."\n";exit(3); }
$line=DB::table('job_card_labour')->where('job_card_id',40)->where('rot_code','ROT-CUS-0198')->first();
if(!$line||!$line->rot_start||!$line->rot_end||!$line->rot_session_id)reportFail('SOURCE_DATA_MISSING');
$start=\Carbon\CarbonImmutable::parse((string)$line->rot_start,'Asia/Kolkata');
$end=\Carbon\CarbonImmutable::parse((string)$line->rot_end,'Asia/Kolkata');
$tat=$end->getTimestamp()-$start->getTimestamp();
$std=(int)round((float)$line->standard_hours*3600);
if($tat<0||$std<=0)reportFail('INVALID_TEST_TIMESTAMP');
$param=(new \ReflectionMethod(\App\Support\RotLiveClock::class,'snapshot'))->getParameters()[0];
$paramType=$param->getType();
if(!$paramType||!($paramType instanceof \ReflectionNamedType))reportFail('UNRECOGNIZED_SESSION_MODEL_TYPE');
$class=$paramType->getName();
if(!class_exists($class)||!method_exists($class,'query'))reportFail('SESSION_MODEL_UNAVAILABLE');
$session=$class::query()->whereKey($line->rot_session_id)->first();
if(!$session||strtolower(trim((string)$session->status))!=='completed')reportFail('SESSION_NOT_COMPLETED');
if((int)$session->labour_line_id!==(int)$line->id)reportFail('SESSION_LINE_MISMATCH');
$snapshot=\App\Support\RotLiveClock::snapshot($session);
$html=view('dashboard.partials.rot-live-clock',['session'=>$session,'line'=>$line])->render();
if(!preg_match('/data-am-elapsed="([0-9]+)"/',$html,$matches))reportFail('RENDERED_CLOCK_ABSENT');
$rendered=(int)$matches[1];
if($rendered!==$tat)reportFail('CORRECTED_TAT_NOT_DISPLAYED');
if(!str_contains($html,'COMPLETED ELAPSED TAT'))reportFail('COMPLETED_LABEL_ABSENT');
if(!str_contains($html,'Productive Work Saved:'))reportFail('SAVED_PRODUCTIVE_LABEL_ABSENT');
if(!str_contains($html,'data-am-running="0"'))reportFail('COMPLETED_CLOCK_MUST_NOT_TICK');
if(!str_contains($html,'data-am-standard="'.$std.'"'))reportFail('STANDARD_MISMATCH');
$expectedBal=$tat>$std?'OVER +':'REMAINING ';
if(!str_contains($html,$expectedBal))reportFail('BALANCE_STYLE_MISMATCH');
$productive=max(0,(int)$session->total_seconds);
$prodText=sprintf('%02d:%02d:%02d',intdiv($productive,3600),intdiv($productive%3600,60),$productive%60);
if(!str_contains($html,$prodText))reportFail('ORIGINAL_PRODUCTIVE_NOT_SHOWN');
if((int)$session->total_seconds!==(int)($snapshot['elapsed_seconds']??-1))reportFail('ORIGINAL_CLOCK_TRUTH_DISAGREES');
echo "AM126_COMPLETED_RENDER_EXPECTED_TAT_SECONDS=".$tat."\n";
echo "AM126_COMPLETED_RENDER_ELAPSED_SECONDS=".$rendered."\n";
echo "AM126_UNCHANGED_PRODUCTIVE_SECONDS=".$productive."\n";
echo "AM126_COMPLETED_TAT_AND_PRODUCTIVE_DISPLAY_PASS\n";
// Running / Paused continue to use the original V639 stopwatch semantics.
// No data updates: compare rendered clock against fresh service snapshot.
foreach(['Running','Paused'] as $state){
    $candidate=DB::table('rot_sessions')->where('status',$state)->first();
    if(!$candidate){echo 'AM126_'.$state.'_NO_SAMPLE_SKIP'."\n";continue;}
    $other=$class::query()->whereKey($candidate->id)->first();
    if(!$other)reportFail('MISSING_'.$state.'_MODEL');
    $base=\App\Support\RotLiveClock::snapshot($other);
    $nonCompletedHtml=view('dashboard.partials.rot-live-clock',['session'=>$other])->render();
    if(!preg_match('/data-am-elapsed="([0-9]+)"/',$nonCompletedHtml,$matches2))reportFail('MISSING_'.$state.'_CLOCK');
    if(abs((int)$matches2[1]-(int)$base['elapsed_seconds'])>5)reportFail('CHANGED_'.$state.'_PRODUCTIVE_CLOCK');
    echo 'AM126_'.$state.'_PRODUCTIVE_CLOCK_UNCHANGED=YES'."\n";
}
echo "AM126_READ_ONLY_STAGING_RENDER_VERIFIED\n";
