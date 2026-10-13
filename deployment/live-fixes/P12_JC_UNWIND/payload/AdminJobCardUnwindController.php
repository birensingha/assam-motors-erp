<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * JC_UNWIND_P12: Cancel/Archive without destroying financial or operational history.
 * Reviewed deletion is separate; invoiced/paid cards never cancel through this endpoint.
 */
final class AdminJobCardUnwindController extends Controller
{
    private function admin(Request $request):ErpAuthUser {
        $u=$request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403,'Admin/Supervisor permission required.');
        return $u;
    }
    private function writeAudit(int $id, ErpAuthUser $user, string $action, string $reason, ?array $before=null, ?array $after=null):void {
        DB::table('job_card_change_audits')->insert([
            'job_card_id'=>$id,'entity_type'=>'JOB_CARD','entity_id'=>$id,
            'action'=>$action,'before_json'=>$before?json_encode($before,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE):null,
            'after_json'=>$after?json_encode($after,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE):null,
            'reason'=>$reason,'actor_id'=>$user->id,'actor_name'=>mb_substr((string)($user->email?:$user->name?:'ADMIN'),0,180),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    private function details(int $id):array {
        $counts=[];
        foreach(['job_card_parts','job_card_labour','rot_sessions','job_estimates','inventory_movements','workshop_purchases',
                  'invoices','customer_ledger','customer_payments','workshop_payment_vouchers','workshop_osl_requests'] as $table){
            $counts[$table]=Schema::hasTable($table)&&Schema::hasColumn($table,'job_card_id')
                ?DB::table($table)->where('job_card_id',$id)->count():0;
        }
        return $counts;
    }
    public function show(Request $request,int $job):View {
        $this->admin($request);
        $card=DB::table('job_cards')->where('id',$job)->first();
        if(!$card)abort(404);
        $counts=$this->details($job);
        $parts=DB::table('job_card_parts')->where('job_card_id',$job)->orderBy('id')->get();
        $labour=DB::table('job_card_labour')->where('job_card_id',$job)->orderBy('id')->get();
        $estimates=DB::table('job_estimates')->where('job_card_id',$job)->orderByDesc('id')->get();
        $requested=DB::table('job_card_change_audits')->where('job_card_id',$job)->where('action','CANCEL_REVIEW_REQUESTED')->count();
        return view('job-cards.unwind',compact('card','counts','parts','labour','estimates','requested'));
    }
    public function removeLabour(Request $request,int $job,int $labour):RedirectResponse {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','min:12','max:500']]);
        DB::transaction(function()use($job,$labour,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            if(!$card)abort(404);
            if(in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true)||!empty($card->invoice_id))
                throw ValidationException::withMessages(['reason'=>'Locked Job Card: Labour cannot be deleted.']);
            $line=DB::table('job_card_labour')->where('job_card_id',$job)->where('id',$labour)->lockForUpdate()->first();
            if(!$line)abort(404);
            if(DB::table('rot_sessions')->where('job_card_id',$job)->where(function($q)use($labour,$line){
                $q->where('labour_line_id',$labour);
                if(!empty($line->rot_session_id))$q->orWhere('id',$line->rot_session_id);
            })->exists()
                || !empty($line->rot_start)||!empty($line->rot_end)||(int)($line->productive_seconds??0)>0
                || !empty($line->source_estimate_item_id)
                || !in_array(strtolower(trim((string)($line->execution_status??'planned'))),['','planned','pending','not_started','not started'],true)
                || DB::table('workshop_purchases')->where('labour_line_id',$labour)->exists()
            )throw ValidationException::withMessages(['reason'=>'Labour already assigned/executed or released from Estimate/Purchase. Use audited cancellation/reversal review; completed ROT records cannot be erased.']);
            $this->writeAudit($job,$user,'UNSTARTED_LABOUR_REMOVED',$data['reason'],(array)$line,null);
            DB::table('job_card_labour')->where('id',$labour)->where('job_card_id',$job)->delete();
            $parts=round((float)DB::table('job_card_parts')->where('job_card_id',$job)->sum('total'),2);
            $lab=round((float)DB::table('job_card_labour')->where('job_card_id',$job)->sum('total'),2);
            DB::table('job_cards')->where('id',$job)->update(['parts_total'=>$parts,'labour_total'=>$lab,'grand_total'=>round($parts+$lab,2),'updated_at'=>now()]);
        },3);
        return redirect()->route('erp.job-cards.reversal',$job)->with('success','Unstarted Labour removed with audit; Job Card totals recalculated.');
    }
    public function requestReview(Request $request,int $job):RedirectResponse {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','min:12','max:500'],'scope'=>['required','in:CANCEL,ESTIMATE,ROT,PARTS,FINANCE']]);
        DB::transaction(function()use($job,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();if(!$card)abort(404);
            $this->writeAudit($job,$user,$data['scope']==='CANCEL'?'CANCEL_REVIEW_REQUESTED':'REVERSAL_REVIEW_'.$data['scope'],$data['reason'],
                ['status'=>(string)$card->status,'invoice_id'=>$card->invoice_id],['scope'=>$data['scope'],'review_status'=>'PENDING']);
        });
        return redirect()->route('erp.job-cards.reversal',$job)->with('success','Audited reversal request recorded. Work/inventory/accounts remain unchanged until reviewed.');
    }
    public function cancel(Request $request,int $job):RedirectResponse {
        $user=$this->admin($request);
        $data=$request->validate([
            'confirm_job_no'=>['required','string','max:80'],'reason'=>['required','string','min:12','max:500'],
            'acknowledge_purchase'=>['accepted'],
        ]);
        DB::transaction(function()use($job,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();if(!$card)abort(404);
            if(trim((string)$card->job_no)!==trim((string)$data['confirm_job_no']))
                throw ValidationException::withMessages(['confirm_job_no'=>'Exact Job Card number mismatch: no cancellation.']);
            $status=strtolower(trim((string)$card->status));
            if(in_array($status,['cancelled','invoiced'],true)||!empty($card->invoice_id))
                throw ValidationException::withMessages(['reason'=>'Already Cancelled or Invoiced. Reverse financial records through authorized accounting first.']);
            foreach(['invoices','customer_payments','customer_ledger','workshop_payment_vouchers'] as $table)
                if(Schema::hasTable($table)&&Schema::hasColumn($table,'job_card_id')&&DB::table($table)->where('job_card_id',$job)->exists())
                    throw ValidationException::withMessages(['reason'=>"Cannot cancel: linked $table entries exist. Use financial reversal first."]);
            $active=DB::table('rot_sessions')->where('job_card_id',$job)->whereRaw("LOWER(TRIM(COALESCE(status,''))) IN ('running','paused','in_progress','in-progress')")->exists();
            if($active)throw ValidationException::withMessages(['reason'=>'Running/paused ROT must be formally stopped or resolved before cancellation.']);
            // Stock issue must already have a non-duplicated return. Do NOT generate new stock movements here.
            $movements=DB::table('inventory_movements')->where('job_card_id',$job)->lockForUpdate()->get();
            $issued=[];$reversed=[];
            foreach($movements as $m){
                $type=strtoupper(trim((string)$m->movement_type));
                $pid=(int)$m->part_master_id;
                if($type==='JOB_ISSUE')$issued[$pid]=($issued[$pid]??0)+(float)$m->qty_out;
                elseif($type==='JOB_PART_DELETE_REVERSAL')$reversed[$pid]=($reversed[$pid]??0)+(float)$m->qty_in;
                elseif($type==='PURCHASE_IN')continue; // Purchase Stock-In stays in store, with vendor liability unchanged.
                else throw ValidationException::withMessages(['reason'=>'Other stock movement requires manual reconciliation: '.$type]);
            }
            foreach($issued as $pid=>$qty){
                if(abs($qty-($reversed[$pid]??0))>0.0001)
                    throw ValidationException::withMessages(['reason'=>'Parts stock-out remains unreversed. Return actual issued stock before cancellation.']);
            }
            foreach($reversed as $pid=>$qty){
                if(abs($qty-($issued[$pid]??0))>0.0001)
                    throw ValidationException::withMessages(['reason'=>'Excess stock reversal detected. Cancellation blocked.']);
            }
            $after=['status'=>'cancelled','cancelled_at'=>now()->toDateTimeString()];
            $this->writeAudit($job,$user,'JOB_CARD_CANCELLED_NO_DATA_DELETION',$data['reason'],
                ['status'=>(string)$card->status,'cancelled_at'=>$card->cancelled_at],
                $after+['retained_purchase_rows'=>DB::table('workshop_purchases')->where('job_card_id',$job)->count(),'retained_completed_rot'=>DB::table('rot_sessions')->where('job_card_id',$job)->count()]);
            DB::table('job_cards')->where('id',$job)->update([
                'status'=>'cancelled','cancelled_at'=>now(),'customer_public_status'=>'Cancelled','updated_at'=>now()
            ]);
        },3);
        return redirect()->route('erp.job-cards.show',$job)->with('success','Job Card CANCELLED (archived). No Purchase, Inspection, ROT, Stock, Estimate, Invoice or Ledger records deleted.');
    }
}
