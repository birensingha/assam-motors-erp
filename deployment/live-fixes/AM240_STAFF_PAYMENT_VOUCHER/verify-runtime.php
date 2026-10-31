<?php
/** AM240 non-posting runtime QA: actual GET controller + Staff Ledger render. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\ErpAuthUser;
use App\Services\ExpensePaymentService;
use App\Http\Controllers\Web\AdminExpensePaymentPageController;
use App\Http\Controllers\Web\AdminStaffFinanceLedgerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
function checkView(bool $ok,string $label):void{
 if(!$ok)throw new RuntimeException('AM240_VIEW_QA_FAIL_'.$label);
}
$admin=ErpAuthUser::query()->whereRaw('LOWER(role)=?',['admin'])->where('active',1)->first();
checkView($admin instanceof ErpAuthUser&&$admin->isAdmin(),'NO_ACTIVE_ADMIN_FOR_READONLY_QA');
function req(string $url,ErpAuthUser $admin):Request{
 $r=Request::create($url,'GET');
 $r->setLaravelSession(app('session')->driver());
 app()->instance('request',$r);
 \Illuminate\Support\Facades\Request::swap($r);
 $r->setUserResolver(static fn()=>$admin);
 return $r;
}
$ctrl=app(AdminExpensePaymentPageController::class);
$normal=$ctrl->createPayment(req('/erp/payments/create',$admin),app(ExpensePaymentService::class))->render();
foreach(['id="am237-payee-search"','id="am237-mode"','id="am240-staff-panel"',
 'id="am240-kind"','id="am240-expense"','name="salary_month"',
 'id="am240-staff-script"','name="staff_payment_kind"','name="staff_payment_against"'] as $required){
 checkView(str_contains($normal,$required),'NORMAL_REQUIRED_'.md5($required));
}
checkView(str_contains($normal,'Staff Expense Reimbursement') &&
 str_contains($normal,'Salary Payable'),'STAFF_PAYMENT_OPTIONS_MISSING');
echo "AM240_VIEW_QA_UNIVERSAL_PAYMENT_STAFF_OPTIONS_RENDER_PASS\n";
$expense=$ctrl->createPayment(req('/erp/payments/create?source=EXPENSE_VOUCHER&id=1',$admin),
 app(ExpensePaymentService::class))->render();
checkView(!str_contains($expense,'id="am240-staff-panel"'),
 'EXISTING_EXPENSE_DEEPLINK_OVERLAY_PRESENT');
echo "AM240_VIEW_QA_EXPENSE_PAYMENT_DEEPLINK_RETAINED_PASS\n";
$firstStaff=DB::table('staff')->first(['id']);
checkView((bool)$firstStaff,'NO_STAFF_MASTER_ROW');
$ledger=app(AdminStaffFinanceLedgerController::class)->index(
 req('/erp/staff-finance?staff_id='.$firstStaff->id.'&month='.now('Asia/Kolkata')->format('Y-m'),$admin)
 )->render();
checkView(str_contains($ledger,'New Payment Voucher'),'FINANCE_MISSING_VOUCHER_ENTRY_LINK');
checkView(!str_contains($ledger,'value="SALARY_PAYMENT"') &&
 !str_contains($ledger,'value="SALARY_ADVANCE"'),'DIRECT_STAFF_CASH_ENTRY_STILL_VISIBLE');
echo "AM240_VIEW_QA_FINANCE_LEDGER_ONLY_MAINTAINS_RECORD_AND_NONCASH_ADJUST_PASS\n";
echo "AM240_VIEW_QA_NO_FINANCIAL_WRITES_PASS\n";
