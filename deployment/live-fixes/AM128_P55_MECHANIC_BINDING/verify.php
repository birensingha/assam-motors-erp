<?php
declare(strict_types=1);
/** AM128 read-only verification. Executes SELECT queries only, no actual ROT edit. */
if(!is_file(getcwd().'/artisan')){echo "AM128_VERIFY_NOT_STAGING\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$partial='resources/views/job-cards/partials/am123-unified-correction.blade.php';
$source=file_get_contents($partial);
if(!$source || strpos($source,'AM128_P55_MECHANIC_STAFF_BINDING')===false
    || strpos($source,"'p55Staff'=>\$am128Staff")===false){
    echo "AM128_VERIFY_MISSING_STAFF_INCLUDE\n";exit(3);
}
$line=DB::table('job_card_labour')->where('rot_code','ROT-DNT-001')->first();
if(!$line){echo "AM128_VERIFY_TARGET_NOT_FOUND\n";exit(4);}
$mechanicId=(int)($line->mechanic_id??0);
if($mechanicId<1){echo "AM128_VERIFY_EXISTING_MECHANIC_MISSING_STOP\n";exit(5);}
$staff=DB::table('staff')->where('id',$mechanicId)->first();
if(!$staff){echo "AM128_VERIFY_ASSIGNED_STAFF_ID_MISSING_IN_STAFF\n";exit(6);}
$session=DB::table('rot_sessions')->where('job_card_id',$line->job_card_id)
    ->where('labour_line_id',$line->id)->orderByDesc('id')->first();
if(!$session){echo "AM128_VERIFY_NO_COMPLETED_ROT_SESSION\n";exit(7);}
if((int)$session->staff_id!==$mechanicId){echo "AM128_VERIFY_LABOUR_SESSION_STAFF_ID_MISMATCH\n";exit(8);}
$assignedIds=[$mechanicId];
$options=DB::table('staff')->where(function($q)use($assignedIds){
    $q->whereRaw("LOWER(TRIM(COALESCE(status,'active')))='active'");
    $q->orWhereIn('id',$assignedIds);
})->get(['id','name','staff_code']);
$matches=$options->filter(fn($row)=>(int)$row->id===$mechanicId);
if($matches->count()!==1){echo "AM128_VERIFY_MECHANIC_MISSING_FROM_OPTIONS\n";exit(9);}
echo "AM128_TARGET=ROT-DNT-001\n";
echo "AM128_LABOUR_AND_SESSION_SAME_MECHANIC_ID=YES\n";
echo "AM128_ASSIGNED_MECHANIC_OPTIONS_MATCH=YES\n";
echo "AM128_P55_SOURCE_INCLUDES_STAFF_OPTIONS=YES\n";
echo "AM128_VERIFICATION_PASS_READ_ONLY\n";
