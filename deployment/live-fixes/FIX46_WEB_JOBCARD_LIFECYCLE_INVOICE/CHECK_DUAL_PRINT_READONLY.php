<?php
/**
 * Assam Motors dual print preflight: READ ONLY, staging only.
 * php CHECK_DUAL_PRINT_READONLY.php /absolute/laravel/root
 * Does not expose customer details, registration numbers or invoice IDs.
 */
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if (!$root || !is_file($root.'/bootstrap/app.php')) { fwrite(STDERR,"Expected Laravel root.\n"); exit(2); }
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Support\\Facades\\DB;
foreach(['invoices','job_cards','job_card_parts','job_card_labour'] as $table) {
    if(!Schema::hasTable($table)) { echo "MISSING TABLE: $table\n"; continue; }
    $columns=Schema::getColumnListing($table);
    $wanted=['total','qty','rate','labour_rate','discount','discount_percent','gst_percent','gst_amount','tax_amount','cgst_amount','sgst_amount','igst_amount','taxable_amount','taxable_value','hsn','hsn_code','sac','sac_code','parts_total','labour_total','gst_total','tax_total','subtotal','pre_round_total','round_off','grand_total'];
    echo $table.': '.implode(', ',array_values(array_intersect($wanted,$columns)))."\n";
}
if(Schema::hasTable('invoices')){
    $cols=Schema::getColumnListing('invoices');
    if(count(array_diff(['parts_total','labour_total','grand_total'],$cols))===0){
        $query=DB::table('invoices')->orderByDesc('id')->limit(30)->get();
        $checked=0;$invalid=0;
        foreach($query as $row){
            $a=(array)$row;
            $expected=(float)($a['parts_total']??0)+(float)($a['labour_total']??0)+(float)($a['gst_total']??$a['tax_total']??0)+(float)($a['round_off']??0);
            $grand=(float)($a['grand_total']??0);
            $checked++;
            if(abs($grand-$expected)>0.05)$invalid++;
        }
        echo "INVOICE_TOTALS_CHECKED=$checked MISMATCHES=$invalid\n";
        echo "NOTE: A mismatch may reflect other adjustments, discounts or a different round-off formula. Inspect schema before deploying.\n";
    }
}
echo "READ_ONLY_PREFLIGHT_COMPLETE\n";
