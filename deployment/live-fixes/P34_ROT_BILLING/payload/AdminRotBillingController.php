<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\JobCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** P34_ROT_BILLING_CONTROL: admin-only Labour billing exclusion without changing ROT time/history. */
final class AdminRotBillingController extends Controller
{
    public function setBilling(Request $request,int $job,int $labour): RedirectResponse
    {
        $admin=$request->user();
        abort_unless($admin instanceof ErpAuthUser && $admin->active && $admin->isAdmin(),403,'Admin required.');
        $data=$request->validate([
          'billing_included'=>['required','in:0,1'],
          'reason'=>['required','string','min:8','max:1000'],
        ]);
        $included=(int)$data['billing_included']===1;
        $changed=DB::transaction(function()use($job,$labour,$included,$data,$admin){
            $card=JobCard::query()->whereKey($job)->lockForUpdate()->firstOrFail();
            if(in_array(strtolower(trim((string)$card->status)),['closed','invoiced','cancelled'],true)
             || (int)($card->invoice_id??0)>0
             || DB::table('invoices')->where('job_card_id',$job)->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['billing_included'=>'Invoice exists or Job Card locked. Use audited accounting reversal, not a billing toggle.']);
            }
            $line=DB::table('job_card_labour')->where('job_card_id',$job)->where('id',$labour)->lockForUpdate()->first();
            if(!$line)throw ValidationException::withMessages(['billing_included'=>'Labour line not found on Job Card.']);
            $originalIncluded=(int)($line->billing_included??1)===1;
            if($originalIncluded===$included)return false;
            $lineTotal=round((float)$line->total,2);
            if($lineTotal<0)throw ValidationException::withMessages(['billing_included'=>'Negative Labour line requires Admin accounting review.']);
            $taxRate=round((float)($line->gst_percent??0),2);
            if($taxRate<0||$taxRate>100)throw ValidationException::withMessages(['billing_included'=>'Invalid Labour GST rate.']);

            // The existing Job Card summary and Invoice must agree before modifying charge policy.
            // Never silently "fix" unrelated historic GST or round-off inconsistencies.
            $partTotal=round((float)($card->parts_total??0),2);
            $oldLabour=round((float)($card->labour_total??0),2);
            $oldGst=round((float)($card->gst_total??0),2);
            $oldRound=round((float)($card->round_off??0),2);
            $oldGrand=round((float)($card->grand_total??0),2);
            $oldPre=round((float)($card->pre_round_total??0),2);
            $sumPrior=round((float)DB::table('job_card_labour')
                  ->where('job_card_id',$job)->where('billing_included',1)->sum('total'),2);
            if(abs($sumPrior-$oldLabour)>0.02
               || abs($oldGrand-($partTotal+$oldLabour+$oldGst+$oldRound))>0.05
               || ($oldPre>0 && abs($oldPre-($partTotal+$oldLabour+$oldGst))>0.05)){
                throw ValidationException::withMessages(['billing_included'=>'Stored Job Card totals are inconsistent. Reconcile billing/GST first; NO CHARGE change not saved.']);
            }
            $factor=$included?1:-1;
            $delta=round($factor*$lineTotal,2);
            $gstDelta=round($factor*$lineTotal*$taxRate/100,2);
            $newLabour=round($oldLabour+$delta,2);
            $newGst=round($oldGst+$gstDelta,2);
            if($newLabour < -0.01 || $newGst < -0.01){
                throw ValidationException::withMessages(['billing_included'=>'GST/Labour totals cannot be negative. Reconcile existing estimate/tax setup first.']);
            }
            $newPre=round($partTotal+$newLabour+$newGst,2);
            $newGrand=round($newPre,0,PHP_ROUND_HALF_UP);
            $newRound=round($newGrand-$newPre,2);

            $before=(array)$line;
            DB::table('job_card_labour')->where('id',$labour)->where('job_card_id',$job)->update([
              'billing_included'=>$included?1:0,'billing_reason'=>trim($data['reason']),
            ]);
            DB::table('job_cards')->where('id',$job)->update([
              'labour_total'=>$newLabour,'gst_total'=>$newGst,
              'pre_round_total'=>$newPre,'round_off'=>$newRound,
              'grand_total'=>$newGrand,'updated_at'=>now(),
            ]);
            $after=(array)DB::table('job_card_labour')->where('id',$labour)->first();
            DB::table('job_card_change_audits')->insert([
              'job_card_id'=>$job,'entity_type'=>'JOB_CARD_LABOUR','entity_id'=>$labour,
              'action'=>$included?'ROT_BILLING_BILLABLE':'ROT_BILLING_NO_CHARGE',
              'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE),
              'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE),
              'reason'=>trim($data['reason']),'actor_id'=>$admin->id,
              'actor_name'=>(string)($admin->name??'Admin'),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return true;
        },3);
        return redirect()->route('erp.job-cards.show',$job)->with('success',
          !$changed?'No change: Labour billing mode already selected.':
          ($included?'Labour / ROT marked BILLABLE and customer totals recalculated.':
             'Labour / ROT marked NO CHARGE. Time and completion history are retained; customer billing totals recalculated.'));
    }
}
