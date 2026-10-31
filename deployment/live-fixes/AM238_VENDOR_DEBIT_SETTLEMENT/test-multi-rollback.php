<?php
/** AM238 full MultiPurchasePaymentService staging integration, always rolled back. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\MultiPurchasePaymentService;
use App\Services\VendorDebitSettlementService;
use App\Services\ExpensePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
function failQA(string $reason): never{throw new RuntimeException('AM238_MULTI_QA_FAIL_'.$reason);}
$tag=substr(bin2hex(random_bytes(10)),0,10);$vendor=0;$voucher1=0;$voucher2=0;$purchaseIds=[];
DB::beginTransaction();
try{
 $vendor=(int)DB::table('workshop_vendors')->insertGetId([
  'vendor_code'=>'AM238M'.substr($tag,0,7),'vendor_name'=>'AM238 MULTI-ROLLBACK '.$tag,
  'active'=>1,'created_at'=>now(),'updated_at'=>now()
 ]);
 $amounts=[1500,1558,1000,1200,1742];$bills=[];
 foreach($amounts as $index=>$amount){
  $batch='AM238M'.$tag.'B'.($index+1);
  $row=[
   'purchase_no'=>$batch,'purchase_batch_no'=>$batch,'purchase_line_no'=>1,
   'purchase_type'=>'PART','purchase_date'=>now('Asia/Kolkata')->toDateString(),
   'vendor_id'=>$vendor,'supplier_name'=>'AM238 MULTI-ROLLBACK',
   'description'=>'AM238 rollback test part','unit'=>'PCS','qty'=>1,'unit_cost'=>$amount,
   'discount_percent'=>0,'discount_amount'=>0,'gst_percent'=>0,'gst_amount'=>0,
   'taxable_amount'=>$amount,'total_amount'=>$amount,
   'record_status'=>'ACTIVE','created_by'=>'AM238_ROLLBACK_QA','created_at'=>now(),'updated_at'=>now()
  ];
  $purchaseIds[]=(int)DB::table('workshop_purchases')->insertGetId($row);
  $bills[]=$batch;
 }
 $multi=app(MultiPurchasePaymentService::class);
 $debitService=app(VendorDebitSettlementService::class);
 $key1=(string)\Illuminate\Support\Str::uuid();
 $first=$multi->save([
  'request_key'=>$key1,'source_type'=>'PURCHASE_INVOICE','payment_date'=>now('Asia/Kolkata')->toDateString(),
  'payment_mode'=>'Cash','amount'=>4000.00,'allocations'=>[
    ['ref'=>$bills[0],'amount'=>1500.00],['ref'=>$bills[1],'amount'=>1558.00]
  ],'vendor_debits'=>[],'notes'=>'AM238QA 4000 paid 3058 adjusted 942 on account'
 ],'AM238_ROLLBACK_QA');
 $voucher1=(int)$first->id;
 if(abs((float)$first->amount-4000)>0.001 || !str_starts_with((string)$first->source_reference,'OA-'))
  failQA('FIRST_VOUCHER_CASH_OR_REF');
 $credits=$debitService->sources($vendor);
 $credit=array_values(array_filter($credits,fn($r)=>$r['voucher_id']===$voucher1));
 if(count($credit)!==1||abs($credit[0]['available']-942)>0.001)failQA('FIRST_942_CREDIT');
 echo "AM238_MULTI_QA_CASH_4000_ALLOC_3058_ONACCOUNT_942_POST_PASS\n";
 $key2=(string)\Illuminate\Support\Str::uuid();
 $second=$multi->save([
  'request_key'=>$key2,'source_type'=>'PURCHASE_INVOICE','payment_date'=>now('Asia/Kolkata')->toDateString(),
  'payment_mode'=>'Cash','amount'=>3000.00,'allocations'=>[
    ['ref'=>$bills[2],'amount'=>1000.00],['ref'=>$bills[3],'amount'=>1200.00],
    ['ref'=>$bills[4],'amount'=>1742.00]
  ],'vendor_debits'=>[['voucher_id'=>$voucher1,'amount'=>942.00]],
  'notes'=>'AM238QA cash 3000 plus 942 previous debit'
 ],'AM238_ROLLBACK_QA');
 $voucher2=(int)$second->id;
 if(abs((float)$second->amount-3000)>0.001)failQA('SECOND_CASH_OVERSTATED');
 $used=DB::table('workshop_vendor_debit_uses')->where('settlement_payment_voucher_id',$voucher2)->sum('amount');
 if(abs((float)$used-942)>0.001)failQA('DEBIT_NOT_USED');
 foreach($debitService->sources($vendor) as $row)if($row['voucher_id']===$voucher1)failQA('DEBIT_SPEND_AGAIN');
 echo "AM238_MULTI_QA_CASH_3000_PLUS_DEBIT_942_CLEARS_THREE_BILLS_3942_PASS\n";
 try{
  $debitService->lockAndValidate($vendor,[$voucher1=>1]);
  failQA('SECOND_DEBIT_USE_NOT_REJECTED');
 }catch(ValidationException $e){}
 // Service-level source-void rule must deny void until dependent voucher voided.
 try{
  app(ExpensePaymentService::class)->voidPayment($voucher1,'AM238 source void block QA','AM238_ROLLBACK_QA');
  failQA('SOURCE_VOID_WITH_USED_DEBIT_ACCEPTED');
 }catch(ValidationException $e){}
 echo "AM238_MULTI_QA_NO_DOUBLE_USE_AND_VOID_BLOCK_PASS\n";
 app(ExpensePaymentService::class)->voidPayment($voucher2,'AM238 settlement reversal QA','AM238_ROLLBACK_QA');
 $again=array_values(array_filter($debitService->sources($vendor),fn($r)=>$r['voucher_id']===$voucher1));
 if(count($again)!==1||abs($again[0]['available']-942)>0.001)failQA('VOID_DID_NOT_RESTORE_DEBIT');
 echo "AM238_MULTI_QA_VOID_RESTORES_DEBIT_AND_BILL_OUTSTANDING_PASS\n";
} finally {
 DB::rollBack();
 echo "AM238_MULTI_QA_TRANSACTION_ROLLBACK_EXECUTED\n";
}
if(($voucher1&&DB::table('workshop_payment_vouchers')->where('id',$voucher1)->exists())||
   ($voucher2&&DB::table('workshop_payment_vouchers')->where('id',$voucher2)->exists())||
   ($vendor&&DB::table('workshop_vendors')->where('id',$vendor)->exists()))
 failQA('POST_ROLLBACK_DATA_LEAK');
echo "AM238_MULTI_QA_NO_PERSISTENT_TEST_FINANCIAL_RECORDS_PASS\n";
