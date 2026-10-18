#!/usr/bin/env bash
# Read-only behavior verification. Expected exceptions happen BEFORE ANY update.
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\ErpAuthUser;
use App\Http\Controllers\Web\AdminRotTimeEditController;

$card=DB::table('job_cards')->where('job_no','JC-20261016-00036')->firstOrFail();
$rot=DB::table('rot_sessions')->where('id','ROT261016193126LSFNZ')->firstOrFail();
$inv=DB::table('invoices')->where('job_card_id',$card->id)->where('status','issued')->first();
if($card->status!=='invoiced' || !$inv || $rot->status!=='Completed')
 throw new RuntimeException('Test reference changed; stop QA');
$original=[(int)$rot->total_seconds,(float)$rot->std_hours,
 (int)DB::table('rot_admin_corrections')->where('rot_session_id',$rot->id)->count()];
$actor=new ErpAuthUser;
$actor->role='admin';$actor->active=true;
$c=app(AdminRotTimeEditController::class);
$controllerSrc=file_get_contents(base_path('app/Http/Controllers/Web/AdminRotTimeEditController.php'));
$serviceSrc=file_get_contents(base_path('app/Services/RotWorkflowService.php'));
$viewSrc=file_get_contents(base_path('resources/views/job-cards/rot-time-editor.blade.php'));
if(!str_contains($serviceSrc,"V646: WIP status alone") ||
   !str_contains($serviceSrc,"whereRaw(\"LOWER(COALESCE(status,'')) <> 'reversed'\")"))
 throw new RuntimeException('Shared Start/Resume active invoice lock absent');
echo "V646_SHARED_START_RESUME_INVOICE_GUARD=PASS\n";

$std=Request::create('/erp/rot/'.$rot->id.'/time/standard','POST',[
 'new_std_hours'=>'1.25','expected_std_hours'=>(string)$rot->std_hours,
 'reason'=>'Audit-only negative test on invoiced Job Card'
]);$std->setUserResolver(fn()=>$actor);
try{$c->standard($std,$rot->id);throw new RuntimeException('INVOICED standard edit allowed');}
catch(ValidationException $e){
 $messages=implode(' ',array_merge(...array_values($e->errors())));
 if(!str_contains($messages,'Standard Hours editing requires an uninvoiced WIP')){
  throw new RuntimeException('Unexpected standard lock: '.$messages);
 }
}
echo "V646_ISSUED_INVOICE_STANDARD_EDIT_BLOCKED=PASS\n";

$actual=Request::create('/erp/rot/'.$rot->id.'/time/actual','POST',[
 'new_actual_hms'=>'00:48:00','expected_actual_seconds'=>0,
 'reason'=>'Audit-only stale value test before time correction'
]);$actual->setUserResolver(fn()=>$actor);
try{$c->actual($actual,$rot->id);throw new RuntimeException('Unexpected productive time write');}
catch(ValidationException $e){
 $messages=implode(' ',array_merge(...array_values($e->errors())));
 if(!str_contains($messages,'ROT time was updated by another device')){
   throw new RuntimeException('Invoice TAT path did not reach expected stale-value check: '.$messages);
 }
}
echo "V646_INVOICED_COMPLETED_TAT_ALLOWED_THROUGH_POLICY_WITH_STALE_BLOCK=PASS\n";

if(!str_contains($viewSrc,'$rotClockFinalStage') || !str_contains($viewSrc,'$rotClockStandardAllowed') ||
   !str_contains($viewSrc,'position:static!important') ||
   !str_contains($controllerSrc,'original_events_preserved'))
 throw new RuntimeException('Admin invoice TAT UI or audit fields absent');
echo "V646_INLINE_TAT_PANEL_AND_ORIGINAL_HISTORY=PASS\n";
$rotFresh=DB::table('rot_sessions')->where('id',$rot->id)->firstOrFail();
$last=(int)DB::table('rot_admin_corrections')->where('rot_session_id',$rot->id)->count();
if((int)$rotFresh->total_seconds!==$original[0] ||
   (float)$rotFresh->std_hours!==$original[1] || $last!==$original[2])
 throw new RuntimeException('No-write QA modified user ROT or correction audit');
echo "V646_NO_CUSTOMER_ROT_DATA_CHANGED=PASS\n";
echo "V646_READONLY_POLICY_QA=PASS\n";
PHP
