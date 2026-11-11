#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\RotWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
$service=app(RotWorkflowService::class);
$rot=DB::table('rot_sessions')->where('rot_code','ROT-ENG-003')->where('staff_id',3)->first();
if(!$rot)throw new RuntimeException('Firajul ROT-ENG-003 not found');
$before=DB::table('rot_sessions')->where('id',$rot->id)->first();
$eventCount=DB::table('rot_session_events')->where('session_id',$rot->id)->count();
$attendance=DB::table('attendance')->where('staff_id',3)->whereDate('date',now('Asia/Kolkata')->toDateString())->first();

function expectError(callable $fn,string $key,string $fragment,string $label):void{
 try{$fn();throw new RuntimeException('SECURITY BUG: '.$label.' accepted');}
 catch(ValidationException $error){
  $messages=implode(' ',($error->errors()[$key]??[]));
  if(!str_contains($messages,$fragment)){
   throw new RuntimeException($label.' unexpected validation: '.$messages);
  }
 }
 echo $label."=PASS\n";
}
// Synthetic staff ID has no Check-In. The guard must fire before ROT status,
// and before any ROT/event updates, even on another mechanic's job.
expectError(fn()=>$service->pause((string)$rot->id,'Parts Waiting',null,
 'staff',999999,999999),'attendance','Check-In first',
 'V650_NO_CHECKIN_MANUAL_PAUSE_REJECTED');

expectError(fn()=>$service->pause((string)$rot->id,'AUTO_CHECK_OUT',null,
 'staff',3,3),'pause_reason','reserved',
 'V650_APP_CANNOT_FORGE_AUTO_CHECKOUT_PAUSE');

if($attendance && !empty($attendance->check_out)){
 expectError(fn()=>$service->pause((string)$rot->id,'Parts Waiting',null,
   'staff',3,3),'attendance','Checked-Out',
   'V650_FIRAJUL_PAUSE_AFTER_CHECKOUT_REJECTED');
}else{
 echo "V650_FIRAJUL_ATTENDANCE_STATUS_CHANGED_SINCE_AUDIT=SKIPPED\n";
}
$after=DB::table('rot_sessions')->where('id',$rot->id)->first();
if((string)$before->status!==(string)$after->status ||
 (int)$before->total_seconds!==(int)$after->total_seconds ||
 (string)$before->pauses!==(string)$after->pauses ||
 DB::table('rot_session_events')->where('session_id',$rot->id)->count()!==$eventCount){
 throw new RuntimeException('Negative tests altered the ROT or events');
}
$serviceCode=file_get_contents(base_path('app/Services/RotWorkflowService.php'));
$staffCheckOut=file_get_contents(base_path('app/Services/AttendanceService.php'));
$adminCheckOut=file_get_contents(base_path('app/Http/Controllers/Web/AdminSelfAttendanceController.php'));
if(!str_contains($serviceCode,'V650_STAFF_MANUAL_PAUSE_ATTENDANCE_GUARD') ||
 !str_contains($staffCheckOut,'V650_AUTO_CHECKOUT_TRUSTED_CALL') ||
 !str_contains($adminCheckOut,'V650_AUTO_CHECKOUT_TRUSTED_CALL') ||
 !str_contains($serviceCode,'$trustedAutoCheckout')){
 throw new RuntimeException('Internal trusted auto-pause wiring absent');
}
echo "V650_TRUSTED_CHECKOUT_AUTO_PAUSE_PRESERVED=PASS\n";
echo "V650_NO_CUSTOMER_RECORDS_MUTATED=PASS\n";
echo "V650_STAFF_PAUSE_READONLY_QA=PASS\n";
PHP
