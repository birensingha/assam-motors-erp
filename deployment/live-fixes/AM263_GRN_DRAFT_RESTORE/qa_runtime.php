<?php
/**
 * AM263 live read-only STAGING test of GRN form and injected browser autosave.
 * User's real localStorage is only on their own device; server NEVER reads it.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
$D=\Illuminate\Support\Facades\DB::class;
$u=\App\Models\ErpAuthUser::where('active',1)->whereIn('role',['owner','admin'])->firstOrFail();
$req=\Illuminate\Http\Request::create('/erp/procurement/receive','GET',[]);
$req->setUserResolver(static fn()=>$u);
$before=[
  'grn'=>$D::table('am260_grns')->count(),
  'movements'=>$D::table('inventory_movements')->count(),
  'purchases'=>$D::table('workshop_purchases')->count(),
];
$view=app(\App\Http\Controllers\Web\AdminProcurementGrnController::class)->newReceipt($req);
if(!$view instanceof \Illuminate\View\View)throw new RuntimeException('AM263_GRN_FORM_VIEW_MISSING');
$html=$view->render();
foreach([
  'am260-grn-form',
  'name="lines[0][part_search_label]"',
  'name="lines[0][part_master_id]"',
  'data-id',
  'am260AddGrnRow',
  'AM263_GRN_DRAFT_RESTORE',
  'am254-global-draft-script',
  'opening_acknowledged',
] as $token){
   if(!str_contains($html,$token))throw new RuntimeException('AM263_RENDER_MISSING_'.preg_replace('/[^A-Za-z0-9_-]/','_',$token));
}
echo "AM263_GLOBAL_AUTOSAVE_PLUS_NAMED_GRN_PART_INPUT_RENDER_PASS\n";
$js=file_get_contents(public_path('js/am254-global-form-autosave.js'));
if(substr_count($js,'AM263_GRN_DRAFT_RESTORE')!==1 ||
   !str_contains($js,'prepareGrnDraftRows(form,draft)') ||
   !str_contains($js,'hydrateGrnPartSearches(form)')){
   throw new RuntimeException('AM263_ACTIVE_AUTOSAVE_PATCH_MISSING');
}
echo "AM263_DYNAMIC_GRN_RESTORE_AND_HISTORIC_PART_ID_HYDRATION_INSTALLED_PASS\n";
$after=[
  'grn'=>$D::table('am260_grns')->count(),
  'movements'=>$D::table('inventory_movements')->count(),
  'purchases'=>$D::table('workshop_purchases')->count(),
];
if($before!==$after)throw new RuntimeException('AM263_BROWSER_QA_MUTATED_BUSINESS_RECORDS');
echo "AM263_BROWSER_DRAFT_STOCK_AND_FINANCIAL_POSTINGS_UNCHANGED_PASS\n";
echo "AM263_STAGING_GRN_DRAFT_RUNTIME_QA_PASS\n";
