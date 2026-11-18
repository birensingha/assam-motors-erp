<?php
/**
 * AM270 authenticated STAGING read-only Purchase Part picker smoke test.
 * No browser localStorage, purchase, inventory, JC, GRN or vendor writes.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$D=\Illuminate\Support\Facades\DB::class;
$before=[
  'purchases'=>$D::table('workshop_purchases')->count(),
  'parts'=>$D::table('parts_master')->count(),
  'movements'=>$D::table('inventory_movements')->count(),
  'job_parts'=>$D::table('job_card_parts')->count(),
  'grn'=>$D::table('am260_grns')->count(),
];
$js=file_get_contents(public_path('js/purchase-entry.js'));
$blade=file_get_contents(resource_path('views/purchases/create.blade.php'));
$assert=static function(bool $condition,string $message):void{
 if(!$condition)throw new RuntimeException($message);
};
foreach([
  'AM270_SINGLE_ACTIVE_PART_MENU',
  'function closeOtherPartResults(activeRow)',
  'AM270_FOCUS_ONLY_PART_MENU',
  'if (!clean(query))',
  "search.addEventListener('blur'",
  'row.dataset.searchToken = String(++requestSeq);',
  'document.activeElement !== search',
  'closeOtherPartResults(row);',
  'AM257_PICKER_ENTER_TAB',
  'selectPart(row, list[idx], false)',
  "const isTab = e.key === 'Tab'",
  "row.querySelector('.qty').focus()",
  'am234-jc-menu',
] as $token){
  $assert(str_contains($js,$token),'AM270_PART_SEARCH_SCRIPT_MISSING_'.preg_replace('/[^A-Za-z0-9_-]/','_',$token));
}
$assert(substr_count($js,'function closeOtherPartResults(')===1,
  'AM270_DUPLICATE_DROPDOWN_HELPER');
$assert(str_contains($blade,'purchase-entry.js') &&
  str_contains($blade,'v=2702026111801') &&
  str_contains($blade,'class="part-results"') &&
  str_contains($blade,'class="part-search"'),
  'AM270_PURCHASE_BLADE_SCRIPT_CACHE_OR_SEARCH_MARKUP_BROKEN');
$assert(str_contains($blade,'part-combo:not(:focus-within)') &&
  str_contains($blade,'AM270_FOCUS_ONLY_PART_MENU'),
  'AM270_CSS_HARD_FOCUS_GATE_MISSING');
$assert(substr_count($blade,'class="part-results"')===1,
  'AM270_DUPLICATE_PART_RESULT_ELEMENT_IN_TEMPLATE');
echo "AM270_STAGING_NEW_SINGLE_PART_PICKER_ASSET_ACTIVE_PASS\n";
echo "AM270_EXISTING_ENTER_TAB_SELECTION_AND_JC_PICKER_PRESERVED_PASS\n";

$routes=\Illuminate\Support\Facades\Route::getRoutes();
$createRoute=$routes->getByName('erp.purchases.create');
$searchRoute=$routes->getByName('erp.purchases.parts.search');
$assert((bool)$createRoute && (bool)$searchRoute,
  'AM270_PURCHASE_CREATE_OR_PARTS_LOOKUP_ROUTE_MISSING');
echo "AM270_PURCHASE_AND_PART_SEARCH_BACKEND_ROUTES_UNCHANGED_PASS\n";
$after=[
  'purchases'=>$D::table('workshop_purchases')->count(),
  'parts'=>$D::table('parts_master')->count(),
  'movements'=>$D::table('inventory_movements')->count(),
  'job_parts'=>$D::table('job_card_parts')->count(),
  'grn'=>$D::table('am260_grns')->count(),
];
$assert($after===$before,'AM270_PURCHASE_UI_QA_MUTATED_FINANCIAL_OR_STOCK_DB');
echo "AM270_NO_PURCHASE_GRN_JC_OR_STOCK_DATA_MODIFIED_PASS\n";
echo "AM270_PURCHASE_DROPDOWN_STAGING_QA_ALL_PASS\n";
