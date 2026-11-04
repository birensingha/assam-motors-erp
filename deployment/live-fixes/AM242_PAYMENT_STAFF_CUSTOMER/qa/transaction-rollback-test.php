<?php
/* AM242 Customer Payment test — nested transactional write, OUTER ROLLBACK ALWAYS.
 * No persisted business payment/ledger rows. No secrets printed.
 */
declare(strict_types=1);
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\ErpAuthUser;
use App\Services\CustomerPayoutVoucherService;

$user=ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isAdmin());
if(!$user)throw new RuntimeException('AM242_TEST_NO_ADMIN');
$customer=DB::table('am_customer_master')->orderBy('id')->first(['id']);
if(!$customer)throw new RuntimeException('AM242_TEST_NO_CANONICAL_CUSTOMER');
$key=(string)Str::uuid();
$id=0;
DB::beginTransaction();
try {
 $args=[
  'payee_category'=>'CUSTOMER','customer_id'=>(int)$customer->id,
  'customer_payment_against'=>'ADVANCE','amount'=>'1.00',
  'payment_date'=>now('Asia/Kolkata')->toDateString(),
  'payment_mode'=>'Cash','account_name'=>'Cash Test',
  'notes'=>'AM242 synthetic rollback-only customer advance finance test',
  'request_key'=>$key
 ];
 $req=Request::create('/erp/payments','POST',$args);
 $req->setUserResolver(fn()=>$user);
 app(CustomerPayoutVoucherService::class)->post($req);
 $voucher=DB::table('workshop_payment_vouchers')->where('request_key',$key)->first();
 if(!$voucher || $voucher->source_type!=='CUSTOMER_ADVANCE' ||
    $voucher->payee_type!=='CUSTOMER' || (int)$voucher->customer_id!==(int)$customer->id ||
    abs((float)$voucher->amount-1.0)>0.009)throw new RuntimeException('AM242_TEST_VOUCHER_MISMATCH');
 $id=(int)$voucher->id;
 $deb=DB::table('customer_ledger')->where('reference_type','PAYMENT_VOUCHER')->where('reference_id',$id)->get();
 if($deb->count()!==1 || abs((float)$deb[0]->debit-1)>0.009 || abs((float)$deb[0]->credit)>0.009)
     throw new RuntimeException('AM242_TEST_DEBIT_MISMATCH');
 $void=Request::create('/erp/payments/'.$id.'/void','POST',['reason'=>'AM242 synthetic test rollback only']);
 $void->setUserResolver(fn()=>$user);
 app(CustomerPayoutVoucherService::class)->void($void,$id);
 $credit=DB::table('customer_ledger')->where('reference_type','PAYMENT_VOUCHER_VOID')->where('reference_id',$id)->get();
 if($credit->count()!==1 || abs((float)$credit[0]->credit-1)>0.009 ||
     DB::table('workshop_payment_vouchers')->where('id',$id)->value('record_status')!=='VOID')
     throw new RuntimeException('AM242_TEST_REVERSAL_MISMATCH');
 if(DB::transactionLevel()!==1)throw new RuntimeException('AM242_TEST_OUTER_TRANSACTION_LOST');
 echo "AM242_SYNTHETIC_CUSTOMER_ADVANCE_AND_VOID_PASS_INSIDE_TRANSACTION\n";
}finally {
 // Never commit; even a successful synthetic test is rolled back.
 while(DB::transactionLevel()>0)DB::rollBack();
}
if(DB::table('workshop_payment_vouchers')->where('request_key',$key)->exists())
  throw new RuntimeException('AM242_TEST_ROLLBACK_VOUCHER_PERSISTED');
if($id>0 && DB::table('customer_ledger')->whereIn('reference_type',['PAYMENT_VOUCHER','PAYMENT_VOUCHER_VOID'])->where('reference_id',$id)->exists())
  throw new RuntimeException('AM242_TEST_ROLLBACK_LEDGER_PERSISTED');
echo "AM242_TRANSACTION_ROLLBACK_VERIFIED_NO_POSTED_TEST_MONEY\n";
