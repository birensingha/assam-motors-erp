#!/usr/bin/env bash
set -Eeuo pipefail
cd "$1";test -f artisan
echo AM233_VERIFY_BEGIN
for f in resources/views/expenses/create.blade.php app/Http/Controllers/Web/AdminExpensePaymentPageController.php app/Services/ExpensePaymentService.php resources/views/expenses/index.blade.php resources/views/expenses/show.blade.php;do
 echo "FILE=$f"
 grep -nE -m 8 'AM233_|LUBRICASH_COUPON|coupon_company|staff_id' "$f" | cut -c1-205 || true
done
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['staff_id','coupon_company'] as $c)echo 'AM233_SCHEMA_'.$c.'='.(\Illuminate\Support\Facades\Schema::hasColumn('workshop_expenses',$c)?'YES':'NO')."\n";
echo 'AM233_CATEGORY_COUNT='.\Illuminate\Support\Facades\DB::table('workshop_expenses')->where('expense_category','LUBRICASH_COUPON')->count()."\n";
PHP
echo AM233_VERIFY_READONLY_COMPLETE
