<?php
/**
 * AM195: Idempotently register Donation in the EXISTING AM275 Expense Category Master.
 * STAGING only. Does not modify or create any voucher or payment records.
 */
declare(strict_types=1);
chdir($argv[1] ?? '');
if (!is_file('artisan') || !is_file('vendor/autoload.php')) {
    fwrite(STDERR, "AM195_STOP_WRONG_APP_ROOT\n");
    exit(2);
}
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('am275_expense_categories')) {
    fwrite(STDERR, "AM195_STOP_EXISTING_CATEGORY_TABLE_MISSING\n");
    exit(3);
}
$columns=Schema::getColumnListing('am275_expense_categories');
foreach (['code','display_name','is_active','is_system','sort_order'] as $required) {
    if (!in_array($required,$columns,true)) {
        fwrite(STDERR, "AM195_STOP_CATEGORY_SCHEMA_DRIFT\n");
        exit(4);
    }
}
DB::transaction(function () use ($columns) {
    $item = DB::table('am275_expense_categories')->where('code','DONATION')->lockForUpdate()->first();
    if ($item) {
        if (!(bool)$item->is_active) {
            DB::table('am275_expense_categories')->where('id',$item->id)->update([
                'is_active'=>true,'updated_at'=>now()
            ]);
            echo "AM195_DONATION_REACTIVATED\n";
        } else {
            echo "AM195_DONATION_ALREADY_ACTIVE\n";
        }
    } else {
        $record=[
            'code'=>'DONATION', 'display_name'=>'Donation',
            'is_active'=>true, 'is_system'=>false, 'sort_order'=>990,
            'created_at'=>now(), 'updated_at'=>now()
        ];
        DB::table('am275_expense_categories')->insert($record);
        echo "AM195_DONATION_CREATED\n";
    }
});
if (!in_array('DONATION', \App\Services\ExpenseCategoryMasterService::activeCodes(),true)) {
    fwrite(STDERR,"AM195_STOP_DONATION_NOT_IN_EXPENSE_PICKER\n");
    exit(5);
}
echo "AM195_DONATION_EXPENSE_PICKER_READY\n";
echo "AM195_PREVIOUS_EXPENSES_PAYMENTS_UNCHANGED\n";
