<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\JobCardTaxTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AdminLabourLineAuditController extends Controller
{
    private function strictAdmin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && (bool)$user->active
            && strtolower(trim((string)$user->role))==='admin',403,
            'Admin permission required to edit/delete Labour.');
        return $user;
    }

    private function invoiceLocked(int $job,object $card): bool
    {
        if(!empty($card->invoice_id) ||
            in_array(strtolower(trim((string)($card->status??''))),
              ['closed','invoiced','cancelled'],true))return true;
        return Schema::hasTable('invoices')
            && DB::table('invoices')->where('job_card_id',$job)
              ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists();
    }

    /** Delete only UNUSED, unassigned, direct JC Labour. Existing ROT timing/history
     * remains immutable and instead goes to reviewed reversal workflow.
     */
    public function delete(Request $request,int $job,int $line): RedirectResponse
    {
        $actor=$this->strictAdmin($request);
        $data=$request->validate([
            'reason'=>['required','string','min:12','max:500'],
            'expected_old_total'=>['required','numeric','min:0'],
        ]);
        DB::transaction(function()use($job,$line,$actor,$data){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            $status=strtolower(trim((string)($card->status??'')));
            if(!in_array($status,['wip','in progress','in-progress','work in progress'],true)
                || $this->invoiceLocked($job,$card))
                throw ValidationException::withMessages(['reason'=>
                    'Locked or invoice-linked Job Card. Use authorised financial reversal.']);
            $old=DB::table('job_card_labour')->where('job_card_id',$job)
                ->where('id',$line)->lockForUpdate()->first();
            abort_unless($old,404);
            if(strtoupper(trim((string)($old->mechanic_name??'')))==='OSL'
                || strtoupper(trim((string)($old->rot_code??'')))==='OSL')
                throw ValidationException::withMessages(['reason'=>
                    'This is an OSL item; use its separate audited OSL Delete control.']);
            if(abs((float)$data['expected_old_total']-(float)$old->total)>0.009)
                throw ValidationException::withMessages(['reason'=>
                    'Labour was updated elsewhere. Refresh before deleting.']);
            $linkedSession=Schema::hasTable('rot_sessions')
                && DB::table('rot_sessions')->where('job_card_id',$job)
                    ->where(function($q)use($line,$old){
                        $q->where('labour_line_id',$line);
                        if(!empty($old->rot_session_id))
                            $q->orWhere('id',(string)$old->rot_session_id);
                    })->lockForUpdate()->exists();
            $linkedSegments=Schema::hasTable('rot_mechanic_segments')
                && DB::table('rot_mechanic_segments')->where('job_card_id',$job)
                   ->where('labour_line_id',$line)->exists();
            $purchaseLinked=Schema::hasTable('workshop_purchases')
                && Schema::hasColumn('workshop_purchases','labour_line_id')
                && DB::table('workshop_purchases')->where('labour_line_id',$line)->exists();
            $execution=strtolower(trim((string)($old->execution_status??'planned')));
            if($linkedSession||$linkedSegments||$purchaseLinked
                || !empty($old->rot_session_id)||!empty($old->source_estimate_item_id)
                || !empty($old->rot_start)||!empty($old->rot_end)
                || (int)($old->productive_seconds??0)>0
                || (int)($old->mechanic_id??0)>0
                || !in_array($execution,['','planned','pending','not_started','not started'],true))
                throw ValidationException::withMessages(['reason'=>
                    'ROT assigned/executed or from approved Estimate/Purchase. Cannot erase mechanic/timing/estimate history. Request an audited ROT/Estimate reversal instead.']);
            if(!Schema::hasTable('job_card_change_audits'))
                throw ValidationException::withMessages(['reason'=>'Audit table missing; deletion stopped.']);
            $totals=JobCardTaxTotals::forJob($job);
            foreach(['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'] as $key){
                if(abs((float)($card->$key??0)-(float)($totals[$key]??0))>0.021)
                    throw ValidationException::withMessages(['reason'=>
                      'Stored JC totals disagree with taxable/GST values. Reconcile before deleting.']);
            }
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,'entity_type'=>'JOB_CARD_LABOUR','entity_id'=>$line,
                'action'=>'UNSTARTED_LABOUR_DELETE_FIX92',
                'before_json'=>json_encode((array)$old,JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json'=>null,'reason'=>$data['reason'],
                'actor_id'=>$actor->id,
                'actor_name'=>mb_substr((string)($actor->email?:$actor->name?:'Admin'),0,180),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('job_card_labour')->where('job_card_id',$job)->where('id',$line)->delete();
            $after=JobCardTaxTotals::forJob($job);
            DB::table('job_cards')->where('id',$job)->update([
                'parts_total'=>$after['parts_total'],
                'labour_total'=>$after['labour_total'],
                'gst_total'=>$after['gst_total'],
                'pre_round_total'=>$after['pre_round_total'],
                'round_off'=>$after['round_off'],
                'grand_total'=>$after['grand_total'],
                'updated_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.job-cards.show',$job)
          ->with('success','Unstarted unassigned Labour deleted with Admin audit.')
          ->withFragment('am90-labour-billing');
    }

    /** Non-destructive ROT reversal request, for already assigned/worked sessions. */
    public function requestReview(Request $request,int $job,int $line): RedirectResponse
    {
        $actor=$this->strictAdmin($request);
        $data=$request->validate(['reason'=>['required','string','min:12','max:500']]);
        DB::transaction(function()use($job,$line,$actor,$data){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            if($this->invoiceLocked($job,$card))
                throw ValidationException::withMessages(['reason'=>
                   'Invoice linked. Use financial credit-note/reopen review before ROT reversal.']);
            $old=DB::table('job_card_labour')->where('job_card_id',$job)
                ->where('id',$line)->lockForUpdate()->first();
            abort_unless($old,404);
            if(!Schema::hasTable('job_card_change_audits'))
                throw ValidationException::withMessages(['reason'=>'Audit history unavailable.']);
            $action=!empty($old->source_estimate_item_id)
                ?'REVERSAL_REVIEW_ESTIMATE':'REVERSAL_REVIEW_ROT';
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,'entity_type'=>'JOB_CARD','entity_id'=>$job,
                'action'=>$action,
                'before_json'=>json_encode([
                    'labour_line_id'=>$line,'rot_code'=>(string)($old->rot_code??''),
                    'execution_status'=>(string)($old->execution_status??''),
                    'source_estimate_item_id'=>$old->source_estimate_item_id??null,
                    'mechanic_id'=>$old->mechanic_id??null,
                ],JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json'=>json_encode(['review_status'=>'PENDING','requested_labour_line_id'=>$line]),
                'reason'=>'Labour line #'.$line.' | '.$data['reason'],
                'actor_id'=>$actor->id,
                'actor_name'=>mb_substr((string)($actor->email?:$actor->name?:'Admin'),0,180),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.job-cards.show',$job)
            ->with('success','Admin reversal review requested. Labour, ROT time and invoices remain unchanged.')
            ->withFragment('am90-labour-billing');
    }
}
