#!/usr/bin/env bash
# Safe V647 negative-boundary smoke test; no POST and no SQL updates.
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Http\Controllers\Web\AdminRotTimelineController as C;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
$tz='Asia/Kolkata';
$c=DB::table('job_cards')->where('job_no','JC-20261018-00037')->first();
if(!$c||!$c->created_at)throw new RuntimeException('JC37 opening not found');
$opened=CarbonImmutable::parse((string)$c->created_at,$tz);
$start=$opened->addMinutes(1);
$end=$start->addMinutes(10);
C::assertRange($opened,$start,$end);
echo 'V647_VALID_AFTER_JC_OPENING=PASS'.PHP_EOL;
try{
 C::assertRange($opened,$opened->subSecond(),$end);
 throw new RuntimeException('ROT Start before JC opening was accepted');
}catch(ValidationException $e){
 if(!str_contains(implode(' ',$e->errors()['start_datetime']??[]),'earlier than Job Card opening'))
  throw new RuntimeException('Wrong JC opening error');
}
echo 'V647_ROT_BEFORE_JC_OPENING_REJECTED=PASS'.PHP_EOL;
try{
 C::assertRange($opened,$start,$start->subSecond());
 throw new RuntimeException('ROT End earlier than Start was accepted');
}catch(ValidationException $e){
 if(!isset($e->errors()['end_datetime']))throw new RuntimeException('Wrong End error');
}
echo 'V647_END_BEFORE_START_REJECTED=PASS'.PHP_EOL;
try{
 C::parseLocal('2026-02-30T12:00:00');
 throw new RuntimeException('Invalid calendar day was accepted');
}catch(ValidationException $e){
 if(!isset($e->errors()['start_datetime']))throw new RuntimeException('Invalid calendar error missing');
}
echo 'V647_INVALID_DATE_REJECTED=PASS'.PHP_EOL;
if(C::parseLocal('2026-10-18T13:11')->format('Y-m-d H:i:s')!=='2026-10-18 13:11:00'){
 throw new RuntimeException('Browser without explicit seconds failed parsing');
}
echo 'V647_DATETIME_LOCAL_SECONDS_NORMALIZED=PASS'.PHP_EOL;
$code=file_get_contents(base_path('app/Http/Controllers/Web/AdminRotTimelineController.php'));
$view=file_get_contents(base_path('resources/views/job-cards/rot-time-editor.blade.php'));
if(!str_contains($code,'CORRECT_START_END_DATETIME') ||
 !str_contains($code,'raw_events_unchanged') ||
 !str_contains($code,"'rot_start'=>") ||
 !str_contains($code,'total_seconds') ||
 !str_contains($code,'invoice_unchanged')){
 throw new RuntimeException('Audit or unchanged productive time evidence missing');
}
if(substr_count($view,'@if($rotClockStandardAllowed)')!==1 ||
 substr_count($view,"route('erp.rot.time.dates'")!==1 ||
 !str_contains($view,'JC Opening') ||
 !str_contains($view,'datetime-local')){
 throw new RuntimeException('Visible UI missing/duplicated');
}
echo 'V647_SINGLE_EDITOR_AND_AUDIT=PASS'.PHP_EOL;
$rot=DB::table('rot_sessions')->where('id','ROT261016193126LSFNZ')->first();
$invoice=DB::table('invoices')->where('job_card_id',36)->first();
if(!$rot || !$invoice)throw new RuntimeException('Known invoice-linked example changed');
echo 'V647_INVOICED_EXAMPLE_NO_WRITES='.json_encode(['rot_status'=>$rot->status,'total_seconds'=>$rot->total_seconds,'invoice_id'=>$invoice->id]).PHP_EOL;
echo 'V647_ROT_DATE_READONLY_QA=PASS'.PHP_EOL;
PHP
