<?php
/* AM261 read-only staff accounting schema, no personal values or DB writes. */
$root=realpath($argv[1]??'')?:'';if(basename($root)!=='assam-erp-staging'||!is_file($root.'/artisan'))exit("AM261_STOP_NOT_STAGING\n");
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
foreach(['staff','workshop_expenses','workshop_payment_vouchers','hr_salary_impact_profiles','hr_salary_profiles','hr_salary_provisions','expense_heads','ledger_accounts','account_heads','workshop_staff_finance_vouchers','workshop_staff_finance_lines'] as $name){
 if(!Schema::hasTable($name)){echo "AM261_ABSENT $name\n";continue;}
 $cols=Schema::getColumnListing($name);
 echo "AM261_COLUMNS $name ".implode(',',$cols)."\n";
}
foreach(['staff','workshop_expenses','workshop_payment_vouchers'] as $name){
 $n=DB::table($name)->count();echo "AM261_COUNT $name $n\n";
}
echo "AM261_READONLY_PASS\n";
