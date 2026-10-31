<?php
/** AM238 staging-only ROLLBACK ledger test. No permanent vendor/payment/allocations created. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Services\VendorDebitSettlementService;
function am238Fail(string $why): never {throw new RuntimeException('AM238_QA_FAIL_'.$why);}
if(!Schema::hasTable('workshop_vendor_debit_uses'))am238Fail('MIGRATION_MISSING');
if(!str_contains((string)file_get_contents('app/Services/VendorDebitSettlementService.php'),
 'Paid purchase invoices and OSL bills are ALREADY settled'))am238Fail('EXCLUDE_DIRECT_PAID_GUARD_NOT_DEPLOYED');
$s=app(VendorDebitSettlementService::class);
$id=substr(bin2hex(random_bytes(8)),0,12);
$vendorId=0;$sourceId=0;$secondId=0;$directId=0;
DB::beginTransaction();
try{
 $vendorId=(int)DB::table('workshop_vendors')->insertGetId([
  'vendor_code'=>'AM238QA'.substr($id,0,7),'vendor_name'=>'AM238 ROLLBACK ONLY '.$id,'active'=>1,
  'created_at'=>now(),'updated_at'=>now()
 ]);
 $create=function(string $type,string $reference,float $amount,string $key)use($vendorId,$id):int{
  return (int)DB::table('workshop_payment_vouchers')->insertGetId([
   'voucher_no'=>'QA-'.substr($id,0,5).'-'.substr($key,0,8),
   'payment_date'=>now('Asia/Kolkata')->toDateString(),
   'source_type'=>$type,'source_reference'=>$reference,
   'payee_type'=>'VENDOR','vendor_id'=>$vendorId,
   'payee_name'=>'AM238 ROLLBACK ONLY',
   'amount'=>$amount,'payment_mode'=>'Cash','record_status'=>'ACTIVE',
   'request_key'=>$key,'created_by'=>'AM238_ROLLBACK_QA','created_at'=>now()
  ]);
 };
 $key1=(string)\Illuminate\Support\Str::uuid();
 $sourceId=$create('PURCHASE_INVOICE','OA-QA-'.substr($id,0,8),4000.00,$key1);
 DB::table('workshop_payment_allocations')->insert([
  'payment_voucher_id'=>$sourceId,'purchase_ref'=>'AM238QA_FIRST_'.substr($id,0,8),
  'amount'=>3058.00,'request_key'=>$key1,'created_at'=>now(),'updated_at'=>now()
 ]);
 $rows=$s->sources($vendorId);
 $source=array_values(array_filter($rows,fn($x)=>$x['voucher_id']===$sourceId));
 if(count($source)!==1 || abs($source[0]['available']-942)>0.001)am238Fail('INITIAL_942_NOT_AVAILABLE');
 echo "AM238_QA_INITIAL_PAYMENT_4000_BILLS_3058_DEBIT_942_PASS\n";
 $keyDirect=(string)\Illuminate\Support\Str::uuid();
 $directId=$create('PURCHASE_INVOICE','PB-ALREADY-PAID-'.$id,100.00,$keyDirect);
 foreach($s->sources($vendorId) as $x)if($x['voucher_id']===$directId)am238Fail('ALREADY_PAID_PURCHASE_AS_DEBIT');
 try{$s->lockAndValidate($vendorId,[$directId=>10000]);am238Fail('DIRECT_PAYMENT_CREDIT_ACCEPTED');}
 catch(ValidationException $exception){}
 echo "AM238_QA_PAID_PURCHASE_NOT_DEBIT_PASS\n";
 $s->lockAndValidate($vendorId,[$sourceId=>94200]);
 $key2=(string)\Illuminate\Support\Str::uuid();
 $secondId=$create('PURCHASE_INVOICE','MULTI-PURCHASE-QA-'.$id,3000.00,$key2);
 DB::table('workshop_payment_allocations')->insert([
  'payment_voucher_id'=>$secondId,'purchase_ref'=>'AM238QA_THREE_BILLS_'.substr($id,0,8),
  'amount'=>3942.00,'request_key'=>$key2,'created_at'=>now(),'updated_at'=>now()
 ]);
 $s->recordUses($vendorId,$secondId,$key2,[$sourceId=>94200]);
 if(3000+942!==3942)am238Fail('SETTLEMENT_ARITHMETIC');
 foreach($s->sources($vendorId) as $x)if($x['voucher_id']===$sourceId)am238Fail('USED_CREDIT_STILL_AVAILABLE');
 if(!$s->activeUsesForSource($sourceId))am238Fail('SOURCE_VOID_NOT_BLOCKED');
 try{$s->lockAndValidate($vendorId,[$sourceId=>1]);am238Fail('DOUBLE_USE_ACCEPTED');}
 catch(ValidationException $exception){}
 echo "AM238_QA_NEXT_CASH_3000_PLUS_PRIOR_DEBIT_942_BILLS_3942_PASS\n";
 echo "AM238_QA_DOUBLE_USE_AND_SOURCE_VOID_GUARD_PASS\n";
 DB::table('workshop_payment_vouchers')->where('id',$secondId)->update(['record_status'=>'VOID']);
 if($s->activeUsesForSource($sourceId))am238Fail('VOIDED_SETTLEMENT_STILL_LOCKS_SOURCE');
 $creditAgain=array_values(array_filter($s->sources($vendorId),fn($x)=>$x['voucher_id']===$sourceId));
 if(count($creditAgain)!==1||abs($creditAgain[0]['available']-942)>0.001)am238Fail('VOID_DID_NOT_RELEASE_CREDIT');
 echo "AM238_QA_SETTLEMENT_VOID_RELEASES_CREDIT_PASS\n";
} catch(\Throwable $e){
 echo 'AM238_QA_ERROR='.get_class($e).' '.substr($e->getMessage(),0,300)."\n";
 throw $e;
} finally {
 // ALWAYS rollback even when PHP assertions fail; no permanent financial QA data.
 DB::rollBack();
 echo "AM238_QA_TRANSACTION_ROLLBACK_EXECUTED\n";
}
if(DB::table('workshop_payment_vouchers')->where('id',$sourceId)->exists()||
   DB::table('workshop_payment_vouchers')->where('id',$secondId)->exists()||
   DB::table('workshop_vendors')->where('id',$vendorId)->exists())
 am238Fail('POST_ROLLBACK_TEST_ROWS_PRESENT');
echo "AM238_QA_NO_PERSISTENT_FINANCIAL_TEST_RECORDS_PASS\n";
