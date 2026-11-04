<?php
/**
 * AM245 isolated rollback-only accounting test on STAGING.
 * Creates two QA expenses inside an OUTER InnoDB transaction, posts one multi
 * voucher using the real service, verifies allocations/partial, voids it,
 * verifies restored status, then ALWAYS rolls back the outer transaction.
 * No purchases/expenses/payment vouchers are committed.
 */
declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ErpAuthUser;
use App\Services\StaffMultiExpensePaymentService;


$root=realpath($argv[1]??'');
if(!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan'))
    throw new RuntimeException('AM245_STOP_NOT_STAGING');
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();


foreach(['staff','workshop_expenses','workshop_payment_vouchers','workshop_payment_allocations'] as $name){
    $engine=DB::selectOne('SELECT ENGINE AS engine FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name]);
    if(!$engine||strtoupper((string)$engine->engine)!=='INNODB')
        throw new RuntimeException('AM245_STOP_NOT_TRANSACTIONAL_TABLE '.$name);
}
$staff=DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")->orderBy('id')->first();
if(!$staff)throw new RuntimeException('AM245_STOP_NO_ACTIVE_STAFF_FOR_TEST');
$admin=ErpAuthUser::query()->where('active',1)->get()->first(static fn($u)=>$u->isAdmin());
if(!$admin)throw new RuntimeException('AM245_STOP_NO_ADMIN_TEST_CONTEXT');

function qaRequest(array $values,ErpAuthUser $admin): Request {
    $req=Request::create('/erp/payments/staff-expenses/multi','POST',$values);
    $req->setUserResolver(static fn()=>$admin);
    return $req;
}
DB::beginTransaction();
try{
    $now=now('Asia/Kolkata');
    $ref='QA-AM245-'.$now->format('YmdHis').'-'.random_int(100000,999999);
    $ids=[];
    foreach([100.0,200.0] as $index=>$total){
        $ids[]=DB::table('workshop_expenses')->insertGetId([
            'expense_no'=>$ref.'-'.$index,'expense_date'=>$now->toDateString(),
            'expense_category'=>'OTHER','expense_against'=>'GENERAL',
            'job_card_id'=>null,'expense_reference'=>null,'vendor_id'=>null,
            'payee_name'=>(string)$staff->name,'supplier_invoice'=>null,
            'description'=>'AM245 synthetic rollback-only QA Staff expense',
            'taxable_amount'=>$total,'gst_percent'=>0,'gst_amount'=>0,'total_amount'=>$total,
            'paid_amount'=>0,'payment_status'=>'UNPAID','notes'=>'ROLLBACK ONLY',
            'record_status'=>'ACTIVE','created_by'=>'AM245_QA_NO_COMMIT',
            'created_at'=>$now,'staff_id'=>(int)$staff->id
        ]);
    }
    $key=(string)Str::uuid();
    $payload=[
        'payee_category'=>'STAFF','staff_multi_expense'=>'1','staff_id'=>(string)$staff->id,
        'staff_payment_kind'=>'EXPENSE_REIMBURSEMENT','staff_payment_against'=>'AGAINST',
        'payment_date'=>$now->toDateString(),'amount'=>'250.00',
        'payment_mode'=>'Cash','account_name'=>'QA CASH COUNTER',
        'reference_no'=>'AM245 QA','notes'=>'AM245 rollback-only accounting transaction',
        'request_key'=>$key,'allocations'=>[
            ['expense_id'=>$ids[0],'amount'=>'100.00'],
            ['expense_id'=>$ids[1],'amount'=>'150.00']
        ],
    ];
    $svc=app(StaffMultiExpensePaymentService::class);
    $svc->post(qaRequest($payload,$admin));
    $v=DB::table('workshop_payment_vouchers')->where('request_key',$key)->first();
    if(!$v || $v->source_reference!=='STAFF_EXPENSE_MULTI' ||
       $v->source_type!=='EXPENSE_VOUCHER' ||
       abs((float)$v->amount-250.00)>0.001)
        throw new RuntimeException('AM245_TEST_VOUCHER_HEADER_BAD');
    $alloc=DB::table('workshop_payment_allocations')->where('payment_voucher_id',$v->id)
        ->orderBy('purchase_ref')->get();
    if($alloc->count()!==2||abs((float)$alloc->sum('amount')-250)>0.001)
        throw new RuntimeException('AM245_TEST_ALLOCATION_BAD');
    $first=DB::table('workshop_expenses')->where('id',$ids[0])->first();
    $second=DB::table('workshop_expenses')->where('id',$ids[1])->first();
    if($first->payment_status!=='PAID'||abs((float)$first->paid_amount-100)>0.001 ||
       $second->payment_status!=='PARTIAL'||abs((float)$second->paid_amount-150)>0.001)
        throw new RuntimeException('AM245_TEST_PAID_PARTIAL_BALANCE_BAD');
    echo "AM245_POST_AND_PARTIAL_ALLOCATION_TEST_PASS_ROLLBACK_PENDING\n";
    $svc->void(qaRequest(['reason'=>'QA rollback-only reversal test'], $admin),(int)$v->id);
    $first=DB::table('workshop_expenses')->where('id',$ids[0])->first();
    $second=DB::table('workshop_expenses')->where('id',$ids[1])->first();
    $voided=DB::table('workshop_payment_vouchers')->where('id',$v->id)->first();
    if($voided->record_status!=='VOID' ||
       $first->payment_status!=='UNPAID'||$second->payment_status!=='UNPAID' ||
       abs((float)$first->paid_amount)>0.001||abs((float)$second->paid_amount)>0.001)
        throw new RuntimeException('AM245_TEST_VOID_RESTORE_BAD');
    echo "AM245_VOID_RESTORES_TWO_BALANCES_PASS_ROLLBACK_PENDING\n";
} finally {
    DB::rollBack();
}
echo "AM245_ACCOUNTING_TRANSACTION_ROLLBACK_FINISHED_NO_COMMITTED_ROWS\n";
