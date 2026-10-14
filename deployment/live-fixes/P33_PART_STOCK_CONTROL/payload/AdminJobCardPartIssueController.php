<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\JobCard;
use App\Models\JobCardPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P33_JOB_PART_REQUISITION_ISSUE_FITTED
 * Requisition never touches stock; Issue deducts stock exactly once; Fitted only
 * confirms work. All actions are admin-only with mandatory audit reasons.
 */
final class AdminJobCardPartIssueController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $u=$request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403,'Active Admin login required.');
        return $u;
    }

    private function card(int $job): JobCard
    {
        $card=JobCard::query()->whereKey($job)->lockForUpdate()->firstOrFail();
        if(in_array(strtolower(trim((string)$card->status)),['cancelled','closed','invoiced'],true) || !empty($card->invoice_id)){
            throw ValidationException::withMessages(['part'=>'Job Card locked. Stock Issue / Fitted unavailable after cancellation, closure or invoicing.']);
        }
        return $card;
    }

    private function part(int $job,int $part): JobCardPart
    {
        return JobCardPart::query()->where('job_card_id',$job)->whereKey($part)->lockForUpdate()->firstOrFail();
    }

    private function audit(int $job,int $part,string $action,array $before,array $after,string $reason,ErpAuthUser $actor): void
    {
        DB::table('job_card_change_audits')->insert([
          'job_card_id'=>$job,
          'entity_type'=>'JOB_CARD_PART',
          'entity_id'=>$part,
          'action'=>$action,
          'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
          'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
          'reason'=>$reason,
          'actor_id'=>$actor->id,
          'actor_name'=>(string)($actor->name??'Admin'),
          'created_at'=>now(),
          'updated_at'=>now(),
        ]);
    }

    public function requisition(Request $request,int $job,int $part): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','min:8','max:1000']]);
        DB::transaction(function()use($job,$part,$data,$actor){
            $card=$this->card($job);
            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['open','in-progress','in progress','wip','work in progress'],true)){
                throw ValidationException::withMessages(['part'=>'Requisition only allowed for OPEN or WIP Job Card.']);
            }
            $line=$this->part($job,$part);
            $state=strtolower(trim((string)($line->issue_status??'planned')));
            if(!in_array($state,['','planned','pending','not issued','not_issued'],true)){
                throw ValidationException::withMessages(['part'=>'Part is already requisitioned, issued or fitted. Refresh Job Card.']);
            }
            $before=$line->toArray();
            $line->issue_status='REQUISITIONED';
            $line->save();
            $this->audit($job,$part,'PART_REQUISITIONED',$before,$line->fresh()->toArray(),$data['reason'],$actor);
        });
        return redirect()->route('erp.job-cards.show',$job)->with('success','Part requisition recorded for Store. No stock has been deducted yet.');
    }

    public function issue(Request $request,int $job,int $part): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','min:8','max:1000']]);
        DB::transaction(function()use($job,$part,$data,$actor){
            $card=$this->card($job);
            if(!in_array(strtolower(trim((string)$card->status)),['in-progress','in progress','wip','work in progress'],true)){
                throw ValidationException::withMessages(['part'=>'Stock Issue requires a WIP Job Card.']);
            }
            $line=$this->part($job,$part);
            if(strtoupper(trim((string)$line->issue_status))!=='REQUISITIONED'){
                throw ValidationException::withMessages(['part'=>'Requisition must be recorded before Stock Issue; duplicate issue refused.']);
            }
            $masterId=(int)($line->part_master_id??0);
            if($masterId<=0){
                throw ValidationException::withMessages(['part'=>'Part has no Parts Master link. Stock issue refused.']);
            }
            $master=DB::table('parts_master')->where('id',$masterId)->lockForUpdate()->first();
            if(!$master)throw ValidationException::withMessages(['part'=>'Parts Master item not found.']);
            $qty=round((float)$line->qty,3);
            $stock=round((float)$master->stock_qty,3);
            if($qty<=0 || $stock<0 || $stock+0.00001<$qty){
                throw ValidationException::withMessages(['part'=>
                   'Insufficient stock for '.($line->part_name?:'Part').'. Available '.$stock.', requested '.$qty.
                   '. Reconcile purchase/stock first. NO stock issue posted.']);
            }
            $ref='JC-PART-'.$part.'-ISSUE';
            $existing=DB::table('inventory_movements')
                ->where('job_card_id',$job)->where('part_master_id',$masterId)
                ->where('reference_no',$ref)->lockForUpdate()->first();
            if($existing){
                throw ValidationException::withMessages(['part'=>'Stock issue already recorded for this Job Card Part. Duplicate issue blocked.']);
            }
            $before=$line->toArray();
            $afterStock=round($stock-$qty,3);
            DB::table('parts_master')->where('id',$masterId)->update(['stock_qty'=>$afterStock]);
            DB::table('inventory_movements')->insert([
              'part_master_id'=>$masterId,
              'job_card_id'=>$job,
              'movement_type'=>'JOB_ISSUE',
              'qty_in'=>0,'qty_out'=>$qty,'balance_after'=>$afterStock,
              'reference_no'=>$ref,
              'note'=>'Authorized Job Card Part Stock Issue · '.$data['reason'],
              'created_at'=>now(),
            ]);
            $line->issue_status='ISSUED';
            $line->save();
            $this->audit($job,$part,'PART_ISSUED',$before,$line->fresh()->toArray(),$data['reason'],$actor);
        });
        return redirect()->route('erp.job-cards.show',$job)->with('success','Part issued. Stock deducted exactly once and linked to Job Card. Mark Fitted after installation verification.');
    }

    public function fitted(Request $request,int $job,int $part): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','min:8','max:1000']]);
        DB::transaction(function()use($job,$part,$data,$actor){
            $card=$this->card($job);
            if(!in_array(strtolower(trim((string)$card->status)),['in-progress','in progress','wip','work in progress'],true)){
                throw ValidationException::withMessages(['part'=>'Fitted confirmation is available in WIP stage only.']);
            }
            $line=$this->part($job,$part);
            if(strtoupper(trim((string)$line->issue_status))!=='ISSUED'){
                throw ValidationException::withMessages(['part'=>'Part must have a recorded Stock Issue before Fitted confirmation.']);
            }
            $ref='JC-PART-'.$part.'-ISSUE';
            $out=DB::table('inventory_movements')
                ->where('job_card_id',$job)->where('part_master_id',(int)$line->part_master_id)
                ->where('movement_type','JOB_ISSUE')->where('reference_no',$ref)
                ->where('qty_out','>',0)->lockForUpdate()->first();
            if(!$out || abs((float)$out->qty_out-(float)$line->qty)>0.0001){
                throw ValidationException::withMessages(['part'=>'Matching exact quantity Stock Issue movement not found. Cannot mark fitted.']);
            }
            $before=$line->toArray();
            $line->issue_status='FITTED';
            $line->save();
            $this->audit($job,$part,'PART_FITTED',$before,$line->fresh()->toArray(),$data['reason'],$actor);
        });
        return redirect()->route('erp.job-cards.show',$job)->with('success','Part FITTED recorded. No additional stock was deducted.');
    }
}
