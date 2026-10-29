<?php
declare(strict_types=1);
/** AM130 Staging read-only Admin register rendering and audit guard verification. */
if (!is_file(getcwd().'/artisan')) { echo "AM130_STOP_NOT_STAGING\n";exit(2); }
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function am130Stop(string $why): never {
    echo "AM130_VERIFY_FAIL_{$why}\n";exit(3);
}
$source=file_get_contents('app/Http/Controllers/Web/AdminNativeModuleController.php');
$view=file_get_contents('resources/views/admin/native/attendance.blade.php');
$partial='resources/views/admin/native/partials/am130-missed-checkout.blade.php';
if(!is_file($partial) || !$source || !$view)am130Stop('SOURCE_OR_PARTIAL_MISSING');
if(!str_contains($source,'AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION')
   || !str_contains($source,'AM130_MISSED_CHECKOUT_ADMIN_REGISTER')
   || !str_contains($view,"@include('admin.native.partials.am130-missed-checkout')"))
    am130Stop('GUARDED_CONTROLLER_OR_VIEW_MISSING');
if(!Schema::hasTable('hr_audit_logs'))am130Stop('HR_AUDIT_TABLE_MISSING');
$cols=Schema::getColumnListing('hr_audit_logs');
foreach(['staff_id','action','subject_type','subject_id','details','created_at'] as $col) {
    if(!in_array($col,$cols,true))am130Stop('HR_AUDIT_COLUMN_MISSING_'.$col);
}
$start=strpos($source,'public function updateAttendance(');
$end=strpos($source,'public function staffLocation(',$start);
if($start===false||$end===false)am130Stop('UPDATE_HANDLER_MISSING');
$handler=substr($source,$start,$end-$start);
foreach(['am130_resolution','am130_checkout_time','am130_reason',
         'lockForUpdate()','hr_audit_logs','DB::transaction',
         "ATTENDANCE_MISSED_CHECKOUT_RESOLVED",
         "'min:12'","'max:1000'"] as $phrase) {
    if(!str_contains($handler,$phrase))am130Stop('AUDIT_GUARD_MISSING_'.preg_replace('/[^a-z0-9]/i','_',$phrase));
}
$today=now('Asia/Kolkata')->toDateString();
$query=DB::table('attendance')->where('date','<',$today)
    ->whereNotNull('check_in')->whereNull('check_out');
$pending=$query->count();
$staff=(clone $query)->distinct()->count('staff_id');
echo "AM130_PREVIOUS_MISSING_CHECKOUT_ROWS={$pending}\n";
echo "AM130_DISTINCT_STAFF_BLOCKED={$staff}\n";
$sample=DB::table('attendance as a')
    ->leftJoin('staff as s','s.id','=','a.staff_id')
    ->where('a.date','<',$today)->whereNotNull('a.check_in')
    ->whereNull('a.check_out')->orderBy('a.date')
    ->select('a.id','a.staff_id','a.date','a.check_in',
        's.name as employee_name','s.staff_code','s.department')->first();
$rows=$sample?collect([$sample]):collect();
try {
    $html=view('admin.native.partials.am130-missed-checkout',[
        'am130PendingRows'=>$rows,'am130PendingCount'=>$pending,
        'errors'=>new \Illuminate\Support\ViewErrorBag(),
    ])->render();
}catch(\Throwable $e){
    echo 'AM130_RENDER_EXCEPTION_DETAIL='.substr(preg_replace('/\s+/',' ',$e->getMessage()),0,320)."\n";
    am130Stop('VIEW_RENDER_EXCEPTION_'.get_class($e));
}
if(!str_contains($html,'Missed Check-Out Register')
    || !str_contains($html,'Admin Correction')
    || !str_contains($html,'Staff cannot back-date Check-Out'))
    am130Stop('RENDERED_VIEW_CONTENT_MISSING');
if($sample){
    foreach(['am130_resolution','am130_checkout_time','am130_reason',
             'Save & Audit','name="_token"'] as $part){
        if(!str_contains($html,$part))am130Stop('RENDERED_FORM_MISSING_'.preg_replace('/[^a-z0-9]/i','_',$part));
    }
    $target=route('erp.attendance.update',$sample->id);
    if(!str_contains($html,htmlspecialchars($target,ENT_QUOTES,'UTF-8'))
      && !str_contains($html,$target))am130Stop('FORM_ROUTE_MISSING');
}
echo "AM130_ADMIN_FORM_RENDER_PASS\n";
echo "AM130_TRANSACTIONAL_AUDIT_GUARD_SOURCE_PASS\n";
echo "AM130_VERIFICATION_COMPLETE_NO_ATTENDANCE_DATA_WRITES\n";
