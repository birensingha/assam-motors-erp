<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * P58 Customer Money Receipt. Three types:
 * - AGAINST REFERENCE (invoice; post matching allocation, update outstanding)
 * - NEW REFERENCE (advance against named future Job Card/reference)
 * - ON ACCOUNT (unallocated general customer credit).
 *
 * One atomic transaction posts receipt + ledger CREDIT + optional invoice
 * allocation. Uses deterministic RCPT number, posted status and request_key.
 * Historical customer receipts are shown but never migrated/rewritten.
 */
final class AdminMoneyReceiptController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin permission required.');
        return $user;
    }

    private function tables(): void
    {
        foreach(['am_customer_master','customer_payments','customer_ledger','invoices','customer_payment_allocations'] as $table){
            abort_unless(Schema::hasTable($table),503,'Money Receipt accounting table unavailable: '.$table);
        }
    }

    private function customerList(): \Illuminate\Support\Collection
    {
        return DB::table('am_customer_master')
            ->select('id','customer_code','name','phone')
            ->orderBy('name')->get();
    }

    private function invoiceList(): \Illuminate\Support\Collection
    {
        // Keep invoice options small; a specific customer/vehicle is selected
        // in the form and server verifies the linked invoice.
        return DB::table('invoices')
            ->whereIn('status',['issued','part_paid'])
            ->whereNotNull('customer_id')
            ->where('grand_total','>',0)
            ->select('id','customer_id','job_card_id','vehicle_reg_no','invoice_no','grand_total','balance_due','balance_total','status')
            ->orderByDesc('id')->limit(500)->get();
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $this->tables();
        $filter=trim((string)$request->query('q',''));
        $query=DB::table('customer_payments as p')
            ->leftJoin('am_customer_master as c','c.id','=','p.customer_id')
            ->select('p.*','c.name as customer_name','c.phone as customer_phone');
        if($filter!=='')$query->where(function($q)use($filter){
            $like='%'.str_replace(['%','_'],['\%','\_'],$filter).'%';
            $q->where('p.receipt_no','like',$like)
              ->orWhere('p.reference_no','like',$like)
              ->orWhere('c.name','like',$like)
              ->orWhere('c.phone','like',$like);
        });
        $receipts=$query->orderByDesc('p.id')->paginate(30)->withQueryString();
        $total=DB::table('customer_payments')->where('payment_status','posted')
            ->whereNull('reversed_at')->sum('total_amount');
        return view('money-receipts.index',compact('receipts','total','filter'));
    }

    public function create(Request $request): View
    {
        $this->admin($request);
        $this->tables();
        $customers=$this->customerList();
        $invoices=$this->invoiceList();
        $selectedCustomer=(int)$request->query('customer',0);
        $selectedInvoice=(int)$request->query('invoice',0);
        $requestKey=(string)Str::uuid();
        return view('money-receipts.create',compact('customers','invoices','selectedCustomer','selectedInvoice','requestKey'));
    }

    private function amount(string|int|float $value): int
    {
        return (int)round((float)$value*100,0,PHP_ROUND_HALF_UP);
    }

    private function invalid(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    /** Verify existing receipts and invoice balances before posting another payment.
     *  Never repair legacy accounting values automatically on a save.
     */
    private function verifyInvoice(object $invoice): array
    {
        if(!in_array(strtolower((string)$invoice->status),['issued','part_paid'],true)){
            $this->invalid('invoice_id','Only ISSUED/PART-PAID non-reversed invoices accept a Money Receipt.');
        }
        $allocated=(float)DB::table('customer_payment_allocations')
            ->where('invoice_id',$invoice->id)->where('status','posted')
            ->whereNull('reversed_at')->sum('amount');
        $grand=$this->amount($invoice->grand_total);
        $used=$this->amount($allocated);
        $due=$grand-$used;
        $storedDue=$this->amount($invoice->balance_due??$invoice->balance_total??$invoice->grand_total);
        $storedDueAlt=$this->amount($invoice->balance_total??$invoice->balance_due??$invoice->grand_total);
        if($due<0||abs($due-$storedDue)>2||abs($due-$storedDueAlt)>2){
            $this->invalid('invoice_id','Invoice has a historical ledger/allocation balance mismatch. Reconcile accounts before receiving payment against this invoice.');
        }
        if($due<=0)$this->invalid('invoice_id','This invoice has no outstanding balance.');
        return [$due,$used,$grand];
    }

    public function store(Request $request): RedirectResponse
    {
        $user=$this->admin($request);
        $this->tables();
        $data=$request->validate([
            'request_key'=>['required','uuid'],
            'receipt_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'customer_id'=>['required','integer','min:1'],
            'reference_type'=>['required',Rule::in(['new_reference','against_reference','on_account'])],
            'invoice_id'=>['nullable','integer','min:1'],
            'reference_no'=>['nullable','string','max:100'],
            'job_card_id'=>['nullable','integer','min:1'],
            'payment_mode'=>['required',Rule::in(['Cash','UPI','Bank Transfer','Cheque','Card','Cash + Online'])],
            'cash_amount'=>['required','numeric','min:0','max:99999999'],
            'online_amount'=>['required','numeric','min:0','max:99999999'],
            'online_reference'=>['nullable','string','max:150'],
            'notes'=>['nullable','string','max:1500'],
        ]);
        $total=$this->amount($data['cash_amount'])+$this->amount($data['online_amount']);
        if($total<=0||$total>9999999999)$this->invalid('cash_amount','Enter a positive payment amount.');
        $cash=$this->amount($data['cash_amount']);
        $online=$this->amount($data['online_amount']);
        $mode=$data['payment_mode'];
        if($mode==='Cash' && $online!==0)$this->invalid('payment_mode','Cash receipts must have online amount zero.');
        if(in_array($mode,['UPI','Bank Transfer','Cheque','Card'],true)&&$cash!==0)
            $this->invalid('payment_mode','Digital/bank receipts must have cash amount zero.');
        if($mode==='Cash + Online'&&($cash<=0||$online<=0))
            $this->invalid('payment_mode','Cash + Online requires both positive cash and online amounts.');
        if($online>0&&trim((string)($data['online_reference']??''))===''){
            $this->invalid('online_reference','Bank/UPI/Cheque/Card transaction reference is required.');
        }
        if($data['reference_type']==='against_reference' && empty($data['invoice_id']))
            $this->invalid('invoice_id','Choose an Invoice for Against Reference.');
        if($data['reference_type']==='new_reference'&&trim((string)($data['reference_no']??''))==='')
            $this->invalid('reference_no','Enter a NEW reference number, advance or future job reference.');
        if($data['reference_type']==='on_account' && !empty($data['invoice_id']))
            $this->invalid('invoice_id','On Account must not allocate directly to an invoice.');
        if($data['reference_type']!=='against_reference' && !empty($data['invoice_id']))
            $this->invalid('invoice_id','Invoice allocation requires Against Reference.');
        if($data['reference_type']==='against_reference' && !empty($data['job_card_id'])){
            $this->invalid('job_card_id','Job Card is selected automatically from the chosen invoice.');
        }

        $receiptId=DB::transaction(function() use($data,$user,$total,$cash,$online,$mode){
            // Lock customer row serialises all Money Receipts for this customer,
            // including double-clicked forms with the same request_key.
            $customer=DB::table('am_customer_master')->where('id',$data['customer_id'])
                ->lockForUpdate()->first();
            if(!$customer)$this->invalid('customer_id','Choose an existing Customer Master record.');
            $existing=DB::table('customer_payments')->where('request_key',$data['request_key'])->first();
            if($existing){
                if((int)$existing->customer_id!==(int)$customer->id)$this->invalid('request_key','Receipt request already belongs to another customer.');
                return (int)$existing->id;
            }

            $invoice=null;
            $job=null;
            $invoiceId=null;
            $jobId=null;
            $vehicleId=null;
            $referenceKind='general';
            $referenceNo=trim((string)($data['reference_no']??''));
            $newDue=null;
            $newPaid=null;
            if($data['reference_type']==='against_reference'){
                $invoice=DB::table('invoices')->where('id',$data['invoice_id'])->lockForUpdate()->first();
                if(!$invoice||(int)$invoice->customer_id!==(int)$customer->id){
                    $this->invalid('invoice_id','Invoice must belong to the selected customer.');
                }
                [$due,$used]=$this->verifyInvoice($invoice);
                if($total>$due)$this->invalid('online_amount',
                   'Amount exceeds invoice outstanding. Save invoice amount first; record the excess separately as On Account.');
                $newPaid=$used+$total;
                $newDue=$due-$total;
                $invoiceId=(int)$invoice->id;
                $jobId=$invoice->job_card_id?:null;
                $vehicleId=$invoice->vehicle_id?:null;
                $referenceKind='invoice';
                $referenceNo=(string)$invoice->invoice_no;
            } elseif($data['reference_type']==='new_reference'){
                $referenceKind='new_reference';
                $referenceNo=trim((string)$data['reference_no']);
                if(!empty($data['job_card_id'])){
                    $job=DB::table('job_cards')->where('id',$data['job_card_id'])
                        ->where('customer_id',$customer->id)->first();
                    if(!$job)$this->invalid('job_card_id','Selected Job Card does not belong to this customer.');
                    $jobId=(int)$job->id;
                    $vehicleId=$job->vehicle_id?:null;
                }
            }else{
                $referenceKind='on_account';
                $referenceNo='ON ACCOUNT';
            }

            $receiptDate=$data['receipt_date'];
            $record=[
                'receipt_no'=>'TMP-RCPT-'.strtoupper(bin2hex(random_bytes(6))),
                'request_key'=>$data['request_key'],
                'customer_id'=>(int)$customer->id,
                'vehicle_id'=>$vehicleId,
                'job_card_id'=>$jobId,
                'invoice_id'=>$invoiceId,
                'customer_email'=>$customer->email?:null,
                'reference_type'=>$data['reference_type'],
                'reference_kind'=>$referenceKind,
                'reference_id'=>$invoiceId,
                'reference_no'=>$referenceNo,
                'cash_amount'=>$cash/100,
                'online_amount'=>$online/100,
                'amount'=>$total/100,
                'total_amount'=>$total/100,
                'payment_mode'=>$mode,
                'online_reference'=>trim((string)($data['online_reference']??''))?:null,
                'notes'=>trim((string)($data['notes']??''))?:null,
                'allocated_amount'=>$invoiceId?$total/100:0,
                'payment_status'=>'posted',
                'received_by'=mb_substr((string)($user->email?:$user->name),0,180),
                'receipt_date'=>$receiptDate,
                'created_at'=>now('Asia/Kolkata'),
            ];
            $receiptId=(int)DB::table('customer_payments')->insertGetId($record);
            $receiptNo='RCPT-'.substr($receiptDate,0,4).'-'.str_pad((string)$receiptId,5,'0',STR_PAD_LEFT);
            DB::table('customer_payments')->where('id',$receiptId)->update(['receipt_no'=>$receiptNo]);

            if($invoice){
                DB::table('customer_payment_allocations')->insert([
                    'payment_id'=>$receiptId,'invoice_id'=>$invoiceId,
                    'amount'=>$total/100,'status'=>'posted','created_at'=>now('Asia/Kolkata'),
                ]);
                DB::table('invoices')->where('id',$invoiceId)->update([
                    'paid_total'=>$newPaid/100,
                    'paid_amount'=>$newPaid/100,
                    'balance_total'=>$newDue/100,
                    'balance_due'=>$newDue/100,
                    'status'=>$newDue===0?'paid':'part_paid',
                ]);
            }

            DB::table('customer_ledger')->insert([
                'customer_id'=>(int)$customer->id,
                'invoice_id'=>$invoiceId,
                'entry_type'=>'CREDIT',
                'amount'=>$total/100,
                'reference_no'=>$receiptNo,
                'note'=>'Money Receipt · '.$data['reference_type'].' · '.$referenceNo,
                'created_at'=>now('Asia/Kolkata'),
                'customer_email'=>$customer->email?:null,
                'customer_name'=>$customer->name?:null,
                'payment_id'=>$receiptId,
                'job_card_id'=>$jobId,
                'vehicle_id'=>$vehicleId,
                'reference_type'=>'PAYMENT',
                'reference_id'=>$receiptId,
                'debit'=>0,
                'credit'=>$total/100,
                'narration'=>'Money Receipt '.$receiptNo.' / '.$referenceNo,
                'entry_date'=>$receiptDate,
            ]);
            return $receiptId;
        },3);

        return redirect()->route('erp.money-receipts.show',$receiptId)
            ->with('success','Money Receipt posted with Customer Ledger credit'.($data['reference_type']==='against_reference'?' and Invoice allocation':'').'.');
    }

    private function receipt(int $receiptId): array
    {
        $receipt=DB::table('customer_payments as p')
            ->leftJoin('am_customer_master as c','c.id','=','p.customer_id')
            ->select('p.*','c.name as customer_name','c.phone as customer_phone',
                'c.address as customer_address','c.customer_code')
            ->where('p.id',$receiptId)->first();
        abort_unless($receipt,404);
        $invoice=$receipt->invoice_id?DB::table('invoices')->where('id',$receipt->invoice_id)->first():null;
        $job=$receipt->job_card_id?DB::table('job_cards')->where('id',$receipt->job_card_id)->first():null;
        $allocation=DB::table('customer_payment_allocations')
            ->where('payment_id',$receiptId)->where('status','posted')
            ->whereNull('reversed_at')->sum('amount');
        $ledger=DB::table('customer_ledger')->where('payment_id',$receiptId)
            ->where('reference_type','PAYMENT')->first();
        $receiptAmount=(float)($receipt->total_amount??$receipt->amount??0);
        $words=$this->amountInWords($receiptAmount);
        return compact('receipt','invoice','job','allocation','ledger','receiptAmount','words');
    }

    public function show(Request $request,int $receipt): View
    {
        $this->admin($request);$this->tables();
        return view('money-receipts.show',$this->receipt($receipt));
    }

    public function print(Request $request,int $receipt): View
    {
        $this->admin($request);$this->tables();
        return view('money-receipts.print',$this->receipt($receipt));
    }

    private function amountInWords(float $amount): string
    {
        $rupees=(int)floor($amount+0.000001);
        $paise=(int)round(($amount-$rupees)*100);
        if($paise===100){$rupees++;$paise=0;}
        $spell=function(int $n) use (&$spell):string{
            $ones=['Zero','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                'Seventeen','Eighteen','Nineteen'];
            $tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
            if($n<20)return $ones[$n];
            if($n<100)return $tens[intdiv($n,10)].($n%10?' '.$spell($n%10):'');
            if($n<1000)return $spell(intdiv($n,100)).' Hundred'.($n%100?' '.$spell($n%100):'');
            foreach([10000000=>'Crore',100000=>'Lakh',1000=>'Thousand'] as $base=>$label)
                if($n>=$base)return $spell(intdiv($n,$base)).' '.$label.($n%$base?' '.$spell($n%$base):'');
            return '';
        };
        return 'Indian Rupees '.$spell($rupees).($paise?' and '.$spell($paise).' Paise':'').' Only';
    }
}
