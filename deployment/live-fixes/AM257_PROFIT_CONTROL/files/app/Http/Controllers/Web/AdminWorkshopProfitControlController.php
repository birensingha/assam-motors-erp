<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AM257 Workshop Profit & Cost Recovery Control, Phase 1.
 *
 * FACTS: source invoice net sales, separately tracked credit notes, current
 * linked JC parts/labour discount lines and recorded expense entries.
 *
 * FORECAST ONLY: monthly overhead budget, expected contribution margin and
 * target profit. No historical inventory COGS or exact OSL discount snapshot
 * is available from current invoice schema. Never label forecast as actual P&L.
 *
 * Dashboard is read-only except admin-approved, auditable planning settings.
 */
final class AdminWorkshopProfitControlController extends Controller
{
    private const SETTING_KEY = 'am257_profit_control_plan_v1';

    private function admin(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser &&
            $user->active && $user->isAdmin() &&
            in_array(strtolower(trim((string)$user->role)),['admin','owner'],true),
            403,'Owner/Admin permission required for Workshop Profit Control.');
        foreach (['admin_settings','invoices','job_card_parts','job_card_labour',
                   'invoice_credit_notes','workshop_expenses','direct_invoice_lines'] as $table) {
            abort_unless(Schema::hasTable($table),503,'Financial dashboard source table missing: '.$table);
        }
        return $user;
    }

    private function value(mixed $v): float
    {
        return round((float)($v??0),2);
    }

    private function plan(): array
    {
        $defaults=[
            'monthly_operating_cost'=>0.0,
            'desired_monthly_profit'=>0.0,
            'working_days'=>26,
            'planned_margin_percent'=>0.0,
        ];
        $row=DB::table('admin_settings')->where('setting_key',self::SETTING_KEY)->first();
        if(!$row)return ['saved'=>false,'values'=>$defaults,'updated_at'=>null];
        $json=json_decode((string)$row->setting_value,true);
        if(!is_array($json) || !isset($json['working_days'],$json['planned_margin_percent']))
            return ['saved'=>false,'values'=>$defaults,'updated_at'=>null];
        return ['saved'=>true,'values'=>[
            'monthly_operating_cost'=>max(0,(float)($json['monthly_operating_cost']??0)),
            'desired_monthly_profit'=>max(0,(float)($json['desired_monthly_profit']??0)),
            'working_days'=>max(1,min(31,(int)$json['working_days'])),
            'planned_margin_percent'=>max(0,min(100,(float)$json['planned_margin_percent'])),
        ],'updated_at'=>$row->updated_at??null];
    }

    private function sourceData(string $day): array
    {
        $date=Carbon::createFromFormat('Y-m-d',$day,'Asia/Kolkata')->startOfDay();
        $invoices=DB::table('invoices')->whereDate('invoice_date',$day)
            ->whereRaw("LOWER(COALESCE(status,'')) NOT IN ('reversed','void','cancelled')")
            ->orderBy('id')->get([
                'id','invoice_no','job_card_id','customer_name','vehicle_reg_no',
                'subtotal','grand_total','gst_total','tax_total','round_off','status'
            ]);

        $invoiceByJob=[];
        $invoiceById=[];
        $validInvoiceIds=[];
        $salesBeforeCredit=0.0;
        foreach($invoices as $inv){
            $invoiceById[(int)$inv->id]=$inv;
            $validInvoiceIds[]=(int)$inv->id;
            if($inv->job_card_id!==null)$invoiceByJob[(int)$inv->job_card_id]=$inv;
            $recordedSubtotal=$inv->subtotal!==null?(float)$inv->subtotal:0;
            if($recordedSubtotal<=0){
                $gst=$inv->gst_total!==null?(float)$inv->gst_total:(float)($inv->tax_total??0);
                $recordedSubtotal=(float)$inv->grand_total-$gst-(float)($inv->round_off??0);
            }
            $salesBeforeCredit+=max(0,round($recordedSubtotal,2));
        }

        // Credit notes on their posting dates are subtracted ONLY where
        // the source invoice remains active. Reversed invoices are excluded
        // above, so counting their credit note again would double subtract.
        $creditNotes=DB::table('invoice_credit_notes as n')
            ->join('invoices as i','i.id','=','n.invoice_id')
            ->whereDate('n.credit_date',$day)
            ->whereRaw("LOWER(COALESCE(n.status,''))='posted'")
            ->get(['n.credit_note_no','n.invoice_id','n.taxable_amount','i.status as invoice_status']);
        $postedCreditTotal=0.0;
        $eligibleCreditTotal=0.0;
        foreach($creditNotes as $credit){
            $amount=$this->value($credit->taxable_amount);
            $postedCreditTotal+=$amount;
            if(!in_array(strtolower((string)$credit->invoice_status),['reversed','void','cancelled'],true))
                $eligibleCreditTotal+=$amount;
        }

        $sales=round($salesBeforeCredit-$eligibleCreditTotal,2);
        $rows=[];$discountParts=0.0;$discountLabour=0.0;$discountDirect=0.0;

        $jobIds=array_keys($invoiceByJob);
        if($jobIds){
            $parts=DB::table('job_card_parts')
                ->whereIn('job_card_id',$jobIds)
                ->where('discount_amount','>',0)
                ->orderBy('id')->get([
                    'job_card_id','part_code','part_name','discount_percent','discount_amount'
                ]);
            foreach($parts as $p){
                $invoice=$invoiceByJob[(int)$p->job_card_id];
                $amount=$this->value($p->discount_amount);
                $discountParts+=$amount;
                $rows[]=[
                    'invoice_no'=>(string)$invoice->invoice_no,
                    'customer'=>(string)($invoice->customer_name??''),
                    'vehicle'=>(string)($invoice->vehicle_reg_no??''),
                    'category'=>'Parts',
                    'code'=>(string)($p->part_code??''),
                    'description'=>(string)($p->part_name??''),
                    'percent'=>(float)($p->discount_percent??0),
                    'amount'=>$amount,
                    'basis'=>'Current Job Card line (not frozen invoice snapshot)',
                ];
            }
            $labour=DB::table('job_card_labour')
                ->whereIn('job_card_id',$jobIds)
                ->where('discount_amount','>',0)
                ->where(function($q){$q->whereNull('billing_included')->orWhere('billing_included',1);})
                ->orderBy('id')->get([
                    'job_card_id','rot_code','description','discount_percent','discount_amount'
                ]);
            foreach($labour as $l){
                $invoice=$invoiceByJob[(int)$l->job_card_id];
                $amount=$this->value($l->discount_amount);
                $discountLabour+=$amount;
                $rows[]=[
                    'invoice_no'=>(string)$invoice->invoice_no,
                    'customer'=>(string)($invoice->customer_name??''),
                    'vehicle'=>(string)($invoice->vehicle_reg_no??''),
                    'category'=>'Labour / ROT',
                    'code'=>(string)($l->rot_code??''),
                    'description'=>(string)($l->description??''),
                    'percent'=>(float)($l->discount_percent??0),
                    'amount'=>$amount,
                    'basis'=>'Current Job Card line (not frozen invoice snapshot)',
                ];
            }
        }
        if($validInvoiceIds){
            $direct=DB::table('direct_invoice_lines')->whereIn('invoice_id',$validInvoiceIds)
                ->where('discount_amount','>',0)->orderBy('id')->get([
                    'invoice_id','part_code','part_name','discount_percent','discount_amount'
                ]);
            foreach($direct as $p){
                $invoice=$invoiceById[(int)$p->invoice_id];
                $amount=$this->value($p->discount_amount);
                $discountDirect+=$amount;
                $rows[]=[
                    'invoice_no'=>(string)$invoice->invoice_no,
                    'customer'=>(string)($invoice->customer_name??''),
                    'vehicle'=>(string)($invoice->vehicle_reg_no??''),
                    'category'=>'Parts (Direct Sale)',
                    'code'=>(string)($p->part_code??''),
                    'description'=>(string)($p->part_name??''),
                    'percent'=>(float)($p->discount_percent??0),
                    'amount'=>$amount,
                    'basis'=>'Direct invoice saved line',
                ];
            }
        }

        $expenses=DB::table('workshop_expenses')->whereDate('expense_date',$day)
            ->where('record_status','ACTIVE')
            ->selectRaw("SUM(CASE WHEN expense_against='JOB' THEN taxable_amount ELSE 0 END) as job_cost")
            ->selectRaw("SUM(CASE WHEN expense_against<>'JOB' OR expense_against IS NULL THEN taxable_amount ELSE 0 END) as other_cost")
            ->selectRaw('COUNT(*) as n')->first();

        usort($rows,static fn($a,$b)=>[$a['invoice_no'],$a['category'],$a['code']]
                <=>[$b['invoice_no'],$b['category'],$b['code']]);

        return [
            'date'=>$date->format('Y-m-d'),
            'sales_before_credit'=>round($salesBeforeCredit,2),
            'credit_notes_posted'=>round($postedCreditTotal,2),
            'credit_adjustment'=>round($eligibleCreditTotal,2),
            'net_sales'=>$sales,
            'invoice_count'=>count($validInvoiceIds),
            'credit_note_count'=>count($creditNotes),
            'discount_parts'=>round($discountParts+$discountDirect,2),
            'discount_labour'=>round($discountLabour,2),
            'known_discounts'=>round($discountParts+$discountDirect+$discountLabour,2),
            'discount_rows'=>$rows,
            'expenses_job'=>$this->value($expenses->job_cost??0),
            'expenses_other'=>$this->value($expenses->other_cost??0),
            'expense_entries'=>(int)($expenses->n??0),
            'osl_discount_available'=>false,
            'historical_parts_cogs_available'=>false,
        ];
    }

    private function calculate(array $data,array $plan): array
    {
        if(!$plan['saved'] || (float)$plan['values']['planned_margin_percent']<=0)
            return ['configured'=>false];

        $v=$plan['values'];
        $days=(int)$v['working_days'];
        $overhead=(float)$v['monthly_operating_cost'];
        $profit=(float)$v['desired_monthly_profit'];
        $margin=(float)$v['planned_margin_percent']/100;
        $dailyOverhead=$overhead/$days;
        $dailyContributionTarget=($overhead+$profit)/$days;
        $dailyBillingTarget=$dailyContributionTarget/$margin;
        $estimatedContribution=$data['net_sales']*$margin;
        return [
            'configured'=>true,
            'daily_overhead'=>round($dailyOverhead,2),
            'daily_contribution_target'=>round($dailyContributionTarget,2),
            'daily_billing_target'=>round($dailyBillingTarget,2),
            'daily_break_even_billing'=>round($dailyOverhead/$margin,2),
            'estimated_contribution'=>round($estimatedContribution,2),
            'estimated_operating_surplus'=>round($estimatedContribution-$dailyOverhead,2),
            'contribution_gap'=>round($estimatedContribution-$dailyContributionTarget,2),
            'achievement_percent'=>$dailyContributionTarget>0
                ?round($estimatedContribution/$dailyContributionTarget*100,1):0.0,
        ];
    }

    public function index(Request $request): View|StreamedResponse
    {
        $this->admin($request);
        $query=$request->validate([
            'date'=>['nullable','date_format:Y-m-d'],
            'export'=>['nullable','in:csv'],
        ]);
        $date=(string)($query['date']??now('Asia/Kolkata')->format('Y-m-d'));
        $data=$this->sourceData($date);
        $plan=$this->plan();
        $forecast=$this->calculate($data,$plan);
        if(($query['export']??'')==='csv'){
            return response()->streamDownload(function()use($data,$forecast,$plan){
                $out=fopen('php://output','w');
                fwrite($out,"\xEF\xBB\xBF");
                fputcsv($out,['ASSAM MOTORS','Workshop Profit Control','Date',$data['date']]);
                fputcsv($out,['Metric','Value','Basis']);
                foreach([
                    ['Valid invoice sales (ex-GST)',$data['sales_before_credit'],'Active source invoices'],
                    ['Eligible posted credit note adjustment',-$data['credit_adjustment'],'Excludes reversed source invoices'],
                    ['Net sales (ex-GST)',$data['net_sales'],'Actual saved invoices'],
                    ['Parts discount (known)',$data['discount_parts'],'Current linked JC / direct lines'],
                    ['Labour discount (known)',$data['discount_labour'],'Current linked JC lines'],
                    ['OSL discount','NOT AVAILABLE','No reliable frozen source found'],
                    ['Recorded job expenses (ex-GST)',$data['expenses_job'],'Expenses register; not full COGS'],
                    ['Recorded non-job expenses (ex-GST)',$data['expenses_other'],'Expenses register; incomplete overhead'],
                    ['Daily billing target',($forecast['configured']??false)?$forecast['daily_billing_target']:'NOT CONFIGURED','Planned contribution margin'],
                    ['Estimated contribution',($forecast['configured']??false)?$forecast['estimated_contribution']:'NOT CONFIGURED','Not actual job profit'],
                    ['Estimated operating surplus',($forecast['configured']??false)?$forecast['estimated_operating_surplus']:'NOT CONFIGURED','Not actual job profit'],
                ] as $line)fputcsv($out,$line);
                fputcsv($out,[]);
                fputcsv($out,['Invoice','Customer','Vehicle','Category','Code','Description','Discount %','Amount','Source quality']);
                foreach($data['discount_rows'] as $r){
                    fputcsv($out,[$r['invoice_no'],$r['customer'],$r['vehicle'],$r['category'],
                        $r['code'],$r['description'],$r['percent'],$r['amount'],$r['basis']]);
                }
                fclose($out);
            },'assam_motors_profit_control_'.$date.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
        }
        return view('profit-control.index',compact('data','plan','forecast','date'));
    }

    public function savePlan(Request $request): RedirectResponse
    {
        $user=$this->admin($request);
        $validated=$request->validate([
            'monthly_operating_cost'=>['required','numeric','min:0','max:1000000000'],
            'desired_monthly_profit'=>['required','numeric','min:0','max:1000000000'],
            'working_days'=>['required','integer','min:1','max:31'],
            'planned_margin_percent'=>['required','numeric','gt:0','max:100'],
            'date'=>['nullable','date_format:Y-m-d'],
        ]);
        $new=[
            'monthly_operating_cost'=>round((float)$validated['monthly_operating_cost'],2),
            'desired_monthly_profit'=>round((float)$validated['desired_monthly_profit'],2),
            'working_days'=>(int)$validated['working_days'],
            'planned_margin_percent'=>round((float)$validated['planned_margin_percent'],2),
        ];
        $before=DB::table('admin_settings')->where('setting_key',self::SETTING_KEY)->first();
        DB::transaction(static function()use($new,$before){
            $json=json_encode($new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            if($before){
                DB::table('admin_settings')->where('id',$before->id)
                    ->update(['setting_value'=>$json,'updated_at'=>now()]);
            }else{
                DB::table('admin_settings')->insert([
                    'setting_key'=>self::SETTING_KEY,'setting_value'=>$json,'updated_at'=>now()
                ]);
            }
        });
        Log::info('AM257_WORKSHOP_PROFIT_PLAN_CHANGED',[
            'actor_id'=>$user->id,
            'old'=>$before?json_decode((string)$before->setting_value,true):null,
            'new'=>$new,'changed_at'=>now('Asia/Kolkata')->toIso8601String(),
        ]);
        return redirect()->route('erp.profit-control.index',
            ['date'=>$validated['date']??now('Asia/Kolkata')->format('Y-m-d')])
            ->with('success','Workshop profit target settings saved. Historical invoices, stock and payments were not changed.');
    }
}
