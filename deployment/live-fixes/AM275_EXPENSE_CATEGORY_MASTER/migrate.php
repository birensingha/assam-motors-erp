<?php
/**
 * AM275 additive Expense Category Master.
 * No existing Expense Voucher, Payment, Job Card or stock data is modified.
 * Codes remain permanent; display-name/group edits are audited separately.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if(!Schema::hasTable('workshop_expense_categories')){
    Schema::create('workshop_expense_categories',static function(Blueprint $t){
        $t->bigIncrements('id');
        $t->string('code',60)->unique();
        $t->string('display_name',100);
        $t->string('ledger_group',32)->default('INDIRECT_EXPENSES');
        $t->boolean('is_active')->default(true);
        $t->boolean('is_system')->default(false);
        $t->unsignedInteger('sort_order')->default(100);
        $t->string('created_by',120)->nullable();
        $t->string('updated_by',120)->nullable();
        $t->timestamps();
    });
    echo "AM275_CATEGORY_MASTER_SCHEMA_CREATED\n";
}
if(!Schema::hasTable('am275_expense_category_audits')){
    Schema::create('am275_expense_category_audits',static function(Blueprint $t){
        $t->bigIncrements('id');
        $t->unsignedBigInteger('category_id')->index();
        $t->string('action',25);
        $t->json('before_json')->nullable();
        $t->json('after_json');
        $t->string('actor',120);
        $t->text('reason');
        $t->timestamp('created_at');
    });
    echo "AM275_EXPENSE_CATEGORY_AUDIT_SCHEMA_CREATED\n";
}
$defaults=[
    ['OUTSIDE_LABOUR','Outside Labour','DIRECT_EXPENSES'],
    ['FUEL','Fuel','INDIRECT_EXPENSES'],
    ['FREIGHT','Freight','DIRECT_EXPENSES'],
    ['CONSUMABLES','Consumables','DIRECT_EXPENSES'],
    ['TRAVEL','Travel / Tour / Local Conveyance','INDIRECT_EXPENSES'],
    ['OFFICE','Office','INDIRECT_EXPENSES'],
    ['UTILITIES','Utilities','INDIRECT_EXPENSES'],
    ['STAFF_WELFARE','Staff Welfare','INDIRECT_EXPENSES'],
    ['SALARY_WAGES','Salary / Wages (via Staff Ledger)','INDIRECT_EXPENSES'],
    ['REPAIR_MAINTENANCE','Repair & Maintenance','INDIRECT_EXPENSES'],
    ['TAX_FEE','Tax / Fee','INDIRECT_EXPENSES'],
    ['LUBRICASH_COUPON','Lubricash Coupon','DIRECT_EXPENSES'],
    ['OTHER','Other Expenses','INDIRECT_EXPENSES'],
    ['DONATION','Donation','INDIRECT_EXPENSES'],
];
DB::transaction(function()use($defaults){
    foreach($defaults as $i=>$r){
        DB::table('workshop_expense_categories')->insertOrIgnore([
            'code'=>$r[0],'display_name'=>$r[1],'ledger_group'=>$r[2],
            'is_active'=>true,'is_system'=>true,'sort_order'=>($i+1)*10,
            'created_by'=>'AM275_SEED','updated_by'=>'AM275_SEED',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    $used=DB::table('workshop_expenses')->select('expense_category')
       ->distinct()->pluck('expense_category')->all();
    foreach($used as $code){
       $code=trim((string)$code);
       if(!$code||mb_strlen($code)>60)continue;
       DB::table('workshop_expense_categories')->insertOrIgnore([
           'code'=>$code,'display_name'=>ucwords(strtolower(str_replace('_',' ',$code))),
           'ledger_group'=>'INDIRECT_EXPENSES','is_active'=>true,
           'is_system'=>false,'sort_order'=>500,'created_by'=>'AM275_LEGACY',
           'updated_by'=>'AM275_LEGACY','created_at'=>now(),'updated_at'=>now(),
       ]);
    }
});
echo 'AM275_DONATION_AND_HISTORICAL_CATEGORIES_AVAILABLE_NO_EXISTING_VOUCHERS_MUTATED'.PHP_EOL;
