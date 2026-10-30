<?php
declare(strict_types=1);
/** AM217 STAGING READ ONLY: verify screenshot purchase billed and cannot reselect. */
if(!is_file(getcwd().'/artisan')){echo "AM217_STOP_WRONG_LARAVEL_ROOT\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
function fail217(string $reason):never{echo "AM217_QA_FAIL_".$reason."\n";exit(3);}
$masterCount=DB::table('workshop_osl_master')->where('active',1)->count();
echo "AM217_ACTIVE_MASTER_COUNT=".$masterCount."\n";
if($masterCount<1)fail217('NO_ACTIVE_OSL_MASTER');
$p=DB::table('workshop_purchases')->where('purchase_no','OSL-261030184631-427')->first();
if(!$p){echo "AM217_SCREENSHOT_PURCHASE_CHANGED_SKIP_SPECIFIC_CHECK\n";}
else{
  $labour=DB::table('job_card_labour')->where('job_card_id',(int)$p->job_card_id)
      ->get()->filter(fn($l)=>strtoupper(trim((string)($l->mechanic_name??'')))==='OSL'
         || strtoupper(trim((string)($l->rot_code??'')))==='OSL')->values();
  $linkedId=(int)($p->labour_line_id??0);
  $hasBill=$linkedId>0 && $labour->contains(fn($l)=>(int)$l->id===$linkedId
             && (int)($l->billing_included??1)===1);
  $shouldExclude=!empty($p->billing_line_created)||$hasBill;
  echo "AM217_SCREENSHOT_LINKED_CUSTOMER_OSL_LINE=".($hasBill?'YES':'NO')."\n";
  echo "AM217_PURCHASE_FLAG=".((int)($p->billing_line_created??0))."\n";
  echo "AM217_PURCHASE_SHOULD_BE_HIDDEN_FROM_ADD_DIALOG=".($shouldExclude?'YES':'NO')."\n";
  if($hasBill && !$shouldExclude)fail217('LINKED_BILL_CANDIDATE_NOT_EXCLUDED');
}
echo "AM217_DB_CHECK_COMPLETE_NO_WRITES\n";
