<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * P31_COUNTER_PARTS_INVOICE: Invoice without Job Card.
 * Customer ledger debit and stock issue are atomic with invoice + immutable items.
 * No automatic payment/receipt is created; use existing invoice-linked receipt flow.
 */
final class CounterPartsInvoiceController extends Controller
{
    private function admin(Request $req): ErpAuthUser
    {
        $u=$req->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403,'Owner/Admin permission required.');
        return $u;
    }

    public function index(Request $req): View
    {
        $this->admin($req);
        $rows=DB::table('direct_invoice_requests as dr')
            ->join('invoices as i','i.id','=','dr.invoice_id')
            ->select('i.id','i.invoice_no','i.invoice_date','i.customer_name','i.grand_total','i.paid_total',
                     'i.balance_due','i.status','dr.source_kind')
            ->orderByDesc('dr.id')->limit(150)->get();
        return view('counter-sales.index',compact('rows'));
    }

    public function create(Request $req): View
    {
        $this->admin($req);
        $customers=DB::table('am_customer_master')
            ->orderBy('name')->limit(3000)->get(['id','customer_code','name','phone','status']);
        $parts=DB::table('parts_master')
            ->whereRaw("LOWER(TRIM(COALESCE(active,''))) IN ('yes','active','1')")
            ->orderBy('part_name')->limit(5000)
            ->get(['id','part_code','part_name','hsn_code','selling_price','gst_percent','unit','stock_qty']);
        $requestKey=(string)Str::uuid();
        return view('counter-sales.create',compact('customers','parts','requestKey'));
    }

    public function store(Request $req): RedirectResponse
    {
        $user=$this->admin($req);
        $data=$req->validate([
           'request_key'=>['required','uuid'],
           'customer_id'=>['required','integer','min:1'],
           'invoice_date'=>['required','date'],
           'narration'=>['nullable','string','max:500'],
           'items'=>['required','array','min:1','max:40'],
           'items.*.part_master_id'=>['required','integer','min:1','distinct'],
           'items.*.qty'=>['required','numeric','gt:0','max:10000'],
           'items.*.rate'=>['required','numeric','gt:0','max:10000000'],
           'items.*.discount_percent'=>['required','numeric','min:0','max:100'],
           'items.*.note'=>['nullable','string','max:500'],
        ]);
        $today=now('Asia/Kolkata')->toDateString();
        if($data['invoice_date']>$today){
            throw ValidationException::withMessages(['invoice_date'=>'Future invoice dates are not permitted.']);
        }
        $invoiceId=DB::transaction(function()use($data,$user){
            $now=now('Asia/Kolkata')->format('Y-m-d H:i:s');
            $key=$data['request_key'];
            // Unique idempotency lock prevents double sales if Save is clicked twice.
            DB::table('direct_invoice_requests')->insertOrIgnore([
              'request_key'=>$key,'created_by_user_id'=>$user->id,
              'source_kind'=>'COUNTER_PARTS','narration'=>trim((string)($data['narration']??'')),
              'created_at'=>$now,
            ]);
            $requestRow=DB::table('direct_invoice_requests')->where('request_key',$key)->lockForUpdate()->first();
            if(!$requestRow)throw new \RuntimeException('Unable to lock invoice submission.');
            if($requestRow->invoice_id)return (int)$requestRow->invoice_id;

            $customer=DB::table('am_customer_master')->where('id',(int)$data['customer_id'])->first();
            if(!$customer || strtolower(trim((string)($customer->status??'active')))==='inactive'){
                throw ValidationException::withMessages(['customer_id'=>'Select an active Customer Master record.']);
            }
            $items=[];
            $subtotal=0.0;$taxTotal=0.0;
            foreach($data['items'] as $line){
                $part=DB::table('parts_master')->where('id',(int)$line['part_master_id'])->lockForUpdate()->first();
                if(!$part || !in_array(strtolower(trim((string)$part->active)),['yes','active','1'],true)){
                    throw ValidationException::withMessages(['items'=>'One or more selected Parts Master items are inactive.']);
                }
                $qty=round((float)$line['qty'],3);
                $stock=(float)($part->stock_qty??0);
                if($part->stock_qty===null || $qty>$stock+0.0001){
                    throw ValidationException::withMessages(['items'=>$part->part_name.' has insufficient stock. Available: '.number_format($stock,3).'.']);
                }
                $rate=round((float)$line['rate'],2);
                $discountPercent=round((float)$line['discount_percent'],2);
                $gstPercent=round((float)($part->gst_percent??0),2);
                if($gstPercent<0||$gstPercent>100){
                    throw ValidationException::withMessages(['items'=>'Check GST in Parts Master for '.$part->part_name]);
                }
                $gross=round($qty*$rate,2);
                $discount=round($gross*$discountPercent/100,2);
                $taxable=round($gross-$discount,2);
                $gst=round($taxable*$gstPercent/100,2);
                $total=round($taxable+$gst,2);
                if($taxable<=0 && $total<=0){
                    throw ValidationException::withMessages(['items'=>'Invoice lines must have a positive selling amount.']);
                }
                $after=round($stock-$qty,3);
                $items[]=[
                  'part'=>$part,'qty'=>$qty,'stock_before'=>$stock,'stock_after'=>$after,
                  'rate'=>$rate,'discount_percent'=>$discountPercent,'gross_amount'=>$gross,
                  'discount_amount'=>$discount,'taxable_amount'=>$taxable,'gst_percent'=>$gstPercent,
                  'gst_amount'=>$gst,'total_amount'=>$total,'note'=>trim((string)($line['note']??'')),
                ];
                $subtotal=round($subtotal+$taxable,2);
                $taxTotal=round($taxTotal+$gst,2);
            }
            $preRound=round($subtotal+$taxTotal,2);
            $grand=(float)round($preRound,0,PHP_ROUND_HALF_UP);
            $roundOff=round($grand-$preRound,2);
            $stamp=\Carbon\Carbon::parse($data['invoice_date'],'Asia/Kolkata')->format('Y-m-d').' '.now('Asia/Kolkata')->format('H:i:s');
            $temporary='PENDING-'.strtoupper(bin2hex(random_bytes(7)));
            $invoiceId=(int)DB::table('invoices')->insertGetId([
               'invoice_no'=>$temporary,'job_card_id'=>null,'original_job_card_id'=>null,
               'customer_id'=>(int)$customer->id,'vehicle_id'=>null,
               'invoice_date'=>$stamp,'parts_total'=>$subtotal,'labour_total'=>0,
               'subtotal'=>$subtotal,'tax_total'=>$taxTotal,'gst_total'=>$taxTotal,
               'pre_round_total'=>$preRound,'round_off'=>$roundOff,'grand_total'=>$grand,
               'paid_total'=>0,'paid_amount'=>0,'balance_total'=>$grand,'balance_due'=>$grand,
               'status'=>'issued','customer_name'=>$customer->name,
               'customer_phone'=>$customer->phone??null,'customer_email'=>$customer->email??null,
               'vehicle_reg_no'=>null,'created_at'=>$now,
            ]);
            $invoiceNo='INV-'.substr($data['invoice_date'],0,4).'-'.str_pad((string)$invoiceId,5,'0',STR_PAD_LEFT);
            DB::table('invoices')->where('id',$invoiceId)->update(['invoice_no'=>$invoiceNo]);
            foreach($items as $entry){
                $part=$entry['part'];$pid=(int)$part->id;
                $qty=$entry['qty'];
                DB::table('parts_master')->where('id',$pid)->update(['stock_qty'=>$entry['stock_after']]);
                DB::table('inventory_movements')->insert([
                   'part_master_id'=>$pid,'job_card_id'=>null,'movement_type'=>'COUNTER_SALE_OUT',
                   'qty_in'=>0,'qty_out'=>$qty,'balance_after'=>$entry['stock_after'],
                   'reference_no'=>$invoiceNo,'note'=>'Parts counter invoice #'.$invoiceId,
                   'created_at'=>$now,
                ]);
                DB::table('direct_invoice_lines')->insert([
                  'invoice_id'=>$invoiceId,'part_master_id'=>$pid,
                  'part_code'=>$part->part_code,'part_name'=>$part->part_name,
                  'hsn_code'=>$part->hsn_code,'unit'=>$part->unit ?: 'PCS',
                  'qty'=>$qty,'rate'=>$entry['rate'],
                  'discount_percent'=>$entry['discount_percent'],
                  'gross_amount'=>$entry['gross_amount'],'discount_amount'=>$entry['discount_amount'],
                  'taxable_amount'=>$entry['taxable_amount'],'gst_percent'=>$entry['gst_percent'],
                  'gst_amount'=>$entry['gst_amount'],'total_amount'=>$entry['total_amount'],
                  'stock_before'=>$entry['stock_before'],'stock_after'=>$entry['stock_after'],
                  'note'=>$entry['note']?:null,'created_at'=>$now,
                ]);
            }
            DB::table('customer_ledger')->insert([
              'customer_id'=>(int)$customer->id,'invoice_id'=>$invoiceId,
              'entry_type'=>'DEBIT','amount'=>$grand,
              'reference_no'=>$invoiceNo,'note'=>'Counter Parts Invoice',
              'created_at'=>$now,'customer_email'=>$customer->email??null,
              'customer_name'=>$customer->name,'payment_id'=>null,'job_card_id'=>null,
              'vehicle_id'=>null,'reference_type'=>'INVOICE','reference_id'=>$invoiceId,
              'debit'=>$grand,'credit'=>0,'narration'=>'Counter Parts Sale '.$invoiceNo,
              'entry_date'=>$data['invoice_date'],
            ]);
            DB::table('direct_invoice_requests')->where('id',$requestRow->id)->update(['invoice_id'=>$invoiceId]);
            return $invoiceId;
        },3);
        return redirect()->route('erp.invoices.show',$invoiceId)
           ->with('success','Direct Parts Invoice created, stock issued and Customer Ledger DEBIT posted.');
    }

    public function show(Request $req,int $invoice): View
    {
        $this->admin($req);
        return view('invoices.direct-show',$this->data($invoice));
    }

    public function print(Request $req,int $invoice): View
    {
        $this->admin($req);
        return view('invoices.direct-print',$this->data($invoice));
    }

    private function data(int $invoice): array
    {
        $inv=DB::table('invoices')->where('id',$invoice)->first();
        abort_unless($inv && !$inv->job_card_id && DB::table('direct_invoice_requests')->where('invoice_id',$invoice)->exists(),404,'Direct Invoice not found.');
        $lines=DB::table('direct_invoice_lines')->where('invoice_id',$invoice)->orderBy('id')->get();
        $ledger=DB::table('customer_ledger')->where('invoice_id',$invoice)->orderBy('id')->get();
        return compact('inv','lines','ledger');
    }
}
