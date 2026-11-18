<?php
/**
 * AM275 STAGING authenticated, transactional QA:
 * Donation Category appears in Expense form; settings Admin create/edit/disable;
 * Expense Voucher + Pay Now fully posted in a transaction then rolled back.
 * NO real expense or payment is retained by QA.
 */
declare(strict_types=1);
error_reporting(E_ALL);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());

$D=\Illuminate\Support\Facades\DB::class;
$S=\Illuminate\Support\Facades\Schema::class;
$assert=static function(bool $ok,string $message):void{
    if(!$ok)throw new RuntimeException($message);
};
foreach(['am275_expense_categories','am275_expense_category_audits'] as $t)
  $assert($S::hasTable($t),'AM275_SCHEMA_MISSING_'.$t);
$donation=$D::table('am275_expense_categories')->where('code','DONATION')->first();
$assert($donation && $donation->is_active,'AM275_DONATION_CATEGORY_NOT_ACTIVE');
$assert(in_array('DONATION',\App\Services\ExpenseCategoryMasterService::activeCodes(),true),
   'AM275_DONATION_NOT_SELECTABLE');
echo "AM275_DONATION_ACTIVE_IN_NEW_EXPENSE_CATEGORY_PICKER_PASS\n";

$routes=\Illuminate\Support\Facades\Route::getRoutes();
$base=$routes->getByName('erp.expenses.create');
foreach(['erp.expense-categories.index'=>'GET','erp.expense-categories.create'=>'POST',
  'erp.expense-categories.update'=>'POST'] as $name=>$method){
    $route=$routes->getByName($name);
    $assert($route && in_array($method,$route->methods(),true) &&
      $route->gatherMiddleware()===$base->gatherMiddleware(),
      'AM275_SETTINGS_ROUTE_AUTH_MISMATCH_'.$name);
}
$user=\App\Models\ErpAuthUser::where('active',1)
  ->whereIn('role',['owner','admin'])->firstOrFail();
$r=static function(string $uri,string $method='GET',array $data=[])use($user){
    $request=\Illuminate\Http\Request::create($uri,$method,$data);
    $request->setUserResolver(static fn()=>$user);
    return $request;
};
$settings=app(\App\Http\Controllers\Web\AdminExpenseCategorySettingsController::class);
$settingView=$settings->index($r('/erp/configuration/expense-categories'),app(\App\Services\ExpenseCategoryMasterService::class));
$assert($settingView instanceof \Illuminate\View\View,'AM275_SETTINGS_NOT_VIEW');
$html=$settingView->render();
foreach(['Expense Category Settings','DONATION','Add New Expense Category',
  'Save Edit','Category Changes / Audit History'] as $token){
    $assert(str_contains($html,$token),'AM275_SETTINGS_VIEW_MISSING_'.$token);
}
$expenseView=app(\App\Http\Controllers\Web\AdminExpensePaymentPageController::class)
  ->createExpense($r('/erp/expenses/create'));
$assert($expenseView instanceof \Illuminate\View\View,'AM275_EXPENSE_FORM_NOT_VIEW');
$expenseHtml=$expenseView->render();
foreach(['value="DONATION"','DONATION / CHARITY','Add / Edit Expense Categories',
    'Record Payment Now'] as $token)
    $assert(str_contains($expenseHtml,$token),'AM275_NEW_EXPENSE_FORM_MISSING_'.$token);
$conf=view('dashboard.configuration')->render();
$assert(str_contains($conf,'Expense Categories'),'AM275_CONFIG_TILE_NOT_VISIBLE');
echo "AM275_SETTINGS_AND_EXPENSE_FORM_RENDER_ADMIN_AUTH_PASS\n";

$guest=\Illuminate\Http\Request::create('/erp/configuration/expense-categories','GET');
$guest->setUserResolver(static fn()=>null);
try{$settings->index($guest,app(\App\Services\ExpenseCategoryMasterService::class));throw new RuntimeException('AM275_GUEST_SETTINGS_ALLOWED');}
catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 $assert($e->getStatusCode()===403,'AM275_GUEST_SETTINGS_WRONG_STATUS');
}
echo "AM275_UNAUTHENTICATED_SETTINGS_ACCESS_BLOCKED_PASS\n";
$before=[
 'expenses'=>$D::table('workshop_expenses')->count(),
 'expenseSum'=>(string)$D::table('workshop_expenses')->sum('total_amount'),
 'payments'=>$D::table('workshop_payment_vouchers')->count(),
 'paymentSum'=>(string)$D::table('workshop_payment_vouchers')->sum('amount'),
 'movements'=>$D::table('inventory_movements')->count(),
 'categories'=>$D::table('am275_expense_categories')->count(),
 'audits'=>$D::table('am275_expense_category_audits')->count(),
];
$D::beginTransaction();
try{
    $service=app(\App\Services\ExpensePaymentService::class);
    $expense=$service->saveExpense([
       'expense_date'=>now('Asia/Kolkata')->toDateString(),
       'expense_category'=>'DONATION',
       'expense_against'=>'REFERENCE',
       'expense_reference'=>'AM275-TEST-ROLLBACK-ONLY',
       'payee_name'=>'AM275 TEST CHARITY (DO NOT PAY)',
       'description'=>'Donation test: rolled back without leaving a real voucher',
       'taxable_amount'=>25.00,
       'gst_percent'=>0,
       'notes'=>'Automated STAGING QA within outer rollback transaction',
       'pay_now'=>true,
       'payment_amount'=>25.00,
       'payment_date'=>now('Asia/Kolkata')->toDateString(),
       'payment_mode'=>'Cash',
       'account_name'=>'AM275_QA_NOT_REAL_CASH',
       'payment_reference_no'=>'AM275_QA_ROLLBACK',
       'payment_notes'=>'AM275 QA TEST ONLY',
    ],'AM275 AUTOMATED ROLLBACK QA');
    $assert($expense && $expense->expense_category==='DONATION'
      && abs((float)$expense->total_amount-25.0)<0.001,
      'AM275_DONATION_EXPENSE_NOT_CREATED_IN_TEST_TRANSACTION');
    $payment=$D::table('workshop_payment_vouchers')
        ->where('source_type','EXPENSE_VOUCHER')
        ->where('source_id',$expense->id)->first();
    $assert($payment && abs((float)$payment->amount-25.0)<0.001 &&
       $payment->record_status==='ACTIVE',
       'AM275_DONATION_PAYMENT_VOUCHER_NOT_LINKED');
    echo "AM275_DONATION_EXPENSE_PLUS_PAY_NOW_PAYMENT_LINKED_QA_PASS\n";

    $settings->create($r('/erp/configuration/expense-categories','POST',[
      'display_name'=>'AM275 QA Custom Category','reason'=>'AM275 testing category creation rollback'
    ]),app(\App\Services\ExpenseCategoryMasterService::class));
    $new=$D::table('am275_expense_categories')->where('code','AM275_QA_CUSTOM_CATEGORY')->first();
    $assert((bool)$new,'AM275_CUSTOM_CATEGORY_CREATE_DID_NOT_WORK');
    $settings->update($r('/erp/configuration/expense-categories/'.$new->id,'POST',[
      'display_name'=>'AM275 QA Renamed Label','is_active'=>'0',
      'reason'=>'AM275 testing rename and disable rollback'
    ]),(int)$new->id,app(\App\Services\ExpenseCategoryMasterService::class));
    $changed=$D::table('am275_expense_categories')->where('id',$new->id)->first();
    $assert($changed && !$changed->is_active &&
       $changed->display_name==='AM275 QA Renamed Label',
       'AM275_CATEGORY_RENAME_DISABLE_FAILED');
    try{
      \App\Services\ExpenseCategoryMasterService::requireActive($new->code);
      throw new RuntimeException('AM275_DISABLED_CATEGORY_ALLOWED_FOR_NEW_EXPENSE');
    }catch(\Illuminate\Validation\ValidationException $e){
      $assert(isset($e->errors()['expense_category']),
         'AM275_DISABLED_CATEGORY_BAD_ERROR');
    }
    $assert($D::table('am275_expense_category_audits')->where('category_id',$new->id)
      ->count()===2,'AM275_CREATE_EDIT_AUDIT_MISSING');
    echo "AM275_NEW_EDIT_DISABLE_AUDIT_AND_INACTIVE_POST_BLOCK_PASS\n";
}finally{
    $D::rollBack();
}
$after=[
 'expenses'=>$D::table('workshop_expenses')->count(),
 'expenseSum'=>(string)$D::table('workshop_expenses')->sum('total_amount'),
 'payments'=>$D::table('workshop_payment_vouchers')->count(),
 'paymentSum'=>(string)$D::table('workshop_payment_vouchers')->sum('amount'),
 'movements'=>$D::table('inventory_movements')->count(),
 'categories'=>$D::table('am275_expense_categories')->count(),
 'audits'=>$D::table('am275_expense_category_audits')->count(),
];
$assert($before===$after,'AM275_TEST_LEFT_REAL_FINANCIAL_OR_CATEGORY_WRITES');
echo "AM275_REAL_EXPENSE_PAYMENT_CATEGORY_AND_STOCK_TOTALS_UNCHANGED_PASS\n";
echo "AM275_FULL_STAGING_DONATION_CATEGORY_AND_SETTINGS_QA_PASS\n";
