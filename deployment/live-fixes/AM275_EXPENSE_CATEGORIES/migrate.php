<?php
/**
 * AM275 — Additive Expense Category Master and audit schema.
 * NEVER modifies previously posted expenses, payments or balances.
 * Old expense_category string keys remain immutable historical identifiers.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

if(!Schema::hasTable('am275_expense_categories')){
  Schema::create('am275_expense_categories',static function(Blueprint $t):void{
    $t->bigIncrements('id');
    $t->string('code',60)->unique();
    $t->string('display_name',120);
    $t->boolean('is_active')->default(true);
    $t->boolean('is_system')->default(false);
    $t->unsignedInteger('sort_order')->default(100);
    $t->string('created_by',160)->nullable();
    $t->timestamps();
  });
  echo "AM275_EXPENSE_CATEGORY_MASTER_CREATED\n";
}
if(!Schema::hasTable('am275_expense_category_audits')){
  Schema::create('am275_expense_category_audits',static function(Blueprint $t):void{
    $t->bigIncrements('id');
    $t->unsignedBigInteger('category_id')->index();
    $t->string('category_code',60);
    $t->string('action',24);
    $t->json('before_json')->nullable();
    $t->json('after_json');
    $t->string('actor',160);
    $t->text('reason');
    $t->timestamps();
  });
  echo "AM275_EXPENSE_CATEGORY_AUDIT_CREATED\n";
}
$defaults=[
 'OUTSIDE_LABOUR'=>'OUTSIDE LABOUR',
 'FUEL'=>'FUEL',
 'FREIGHT'=>'FREIGHT',
 'CONSUMABLES'=>'CONSUMABLES',
 'TRAVEL'=>'TRAVEL / TOUR / LOCAL CONVEYANCE',
 'OFFICE'=>'OFFICE',
 'UTILITIES'=>'UTILITIES',
 'STAFF_WELFARE'=>'STAFF WELFARE',
 'SALARY_WAGES'=>'SALARY WAGES',
 'REPAIR_MAINTENANCE'=>'REPAIR MAINTENANCE',
 'TAX_FEE'=>'TAX / FEE',
 'LUBRICASH_COUPON'=>'LUBRICASH COUPON',
 'OTHER'=>'OTHER',
 'DONATION'=>'DONATION / CHARITY',
];
$number=10;
foreach($defaults as $code=>$label){
  if(!DB::table('am275_expense_categories')->where('code',$code)->exists()){
    DB::table('am275_expense_categories')->insert([
      'code'=>$code,'display_name'=>$label,'is_active'=>1,
      'is_system'=>in_array($code,['LUBRICASH_COUPON','SALARY_WAGES'],true)?1:0,
      'sort_order'=>$number,'created_by'=>'AM275_INITIAL_SEED',
      'created_at'=>now(),'updated_at'=>now(),
    ]);
  }
  $number+=10;
}
// Preserve any old or legacy category that was used by a saved voucher,
// even when not in our original hardcoded list. Never rewrite saved codes.
if(Schema::hasTable('workshop_expenses')){
  $used=DB::table('workshop_expenses')->select('expense_category')
    ->whereNotNull('expense_category')->distinct()->pluck('expense_category');
  foreach($used as $code){
    $code=trim((string)$code);
    if($code===''||DB::table('am275_expense_categories')->where('code',$code)->exists())continue;
    DB::table('am275_expense_categories')->insert([
      'code'=>mb_substr($code,0,60),
      'display_name'=>mb_substr(str_replace('_',' ',$code),0,120),
      'is_active'=>1,'is_system'=>1,'sort_order'=>500,
      'created_by'=>'AM275_LEGACY_CATEGORY_PRESERVE',
      'created_at'=>now(),'updated_at'=>now()
    ]);
  }
}
echo "AM275_DONATION_AND_DEFAULT_CATEGORIES_SEEDED_NO_EXISTING_EXPENSE_OR_PAYMENT_CHANGED\n";
