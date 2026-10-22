<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\JobCardTaxTotals;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminJobCardOslLineController extends Controller {
    public function save(Request $request,int $job,?int $line=null): RedirectResponse {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && strtolower(trim((string)$actor->role))==='admin',403,'Admin permission required for Labour/OSL changes.');
        $d=$request->validate([
          'description'=>['required','string','max:255'],
          'quantity'=>['required','numeric','gt:0','lte:10000'],
          'rate'=>['required','numeric','min:0','lte:10000000'],
          'discount_percent'=>['required','numeric','between:0,100'],
          'gst_percent'=>['required','numeric','between:0,100'],
          'note'=>['nullable','string','max:1000'],
          'reason'=>[$line?'required':'nullable','string','min:5','max:1000'],
          'expected_old_total'=>[$line?'required':'nullable','numeric'],
        ]);
        DB::transaction(function()use($job,$line,$actor,$d) {
          $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
          abort_unless($card,404);
          if(in_array(strtolower(trim((string)$card->status)),['closed','invoiced','cancelled'],true)
             || $card->invoice_id || DB::table('invoices')->where('job_card_id',$job)
             ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists())
             throw ValidationException::withMessages(['osl'=>'Closed or invoiced Job Card is locked.']);
          $current=JobCardTaxTotals::forJob($job);
          foreach(['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'] as $k)
             if(abs((float)$card->$k-$current[$k])>0.021)
                 throw ValidationException::withMessages(['osl'=>'Stored JC/GST mismatch; reconcile before editing.']);
          $before=null;
          if($line){
            $old=DB::table('job_card_labour')->where('job_card_id',$job)->where('id',$line)->lockForUpdate()->first();
            abort_unless($old,404);
            if(strtoupper(trim((string)$old->mechanic_name))!=='OSL'
              || abs((float)$old->total-(float)$d['expected_old_total'])>0.009)
                throw ValidationException::withMessages(['osl'=>'OSL line changed or is not OSL. Refresh.']);
            $before=(array)$old;
          }
          $qty=round((float)$d['quantity'],2);
          $rate=round((float)$d['rate'],2);
          $discount=round((float)$d['discount_percent'],2);
          $base=round($qty*$rate,2);
          $off=round($base*$discount/100,2);
          $fields=[
            'description'=>trim($d['description']),'standard_hours'=>$qty,
            'labour_rate'=>$rate,'discount_percent'=>$discount,'discount_amount'=>$off,
            'gst_percent'=>round((float)$d['gst_percent'],2),'total'=>round($base-$off,2),
            'rot_note'=>trim((string)($d['note']??''))?:null,
          ];
          if($line) DB::table('job_card_labour')->where('id',$line)->update($fields);
          else {
            $fields+=['job_card_id'=>$job,'rot_code'=>'OSL','mechanic_id'=>0,
              'mechanic_name'=>'OSL','execution_status'=>'COMPLETED','billing_included'=>1,
              'rot_start'=>null,'rot_end'=>null,'total_hours'=>0,'productive_seconds'=>0];
            $line=(int)DB::table('job_card_labour')->insertGetId($fields);
          }
          $new=JobCardTaxTotals::forJob($job);
          DB::table('job_cards')->where('id',$job)->update([
             'parts_total'=>$new['parts_total'],'labour_total'=>$new['labour_total'],
             'gst_total'=>$new['gst_total'],'pre_round_total'=>$new['pre_round_total'],
             'round_off'=>$new['round_off'],'grand_total'=>$new['grand_total'],
             'updated_at'=>now(),
          ]);
          DB::table('job_card_change_audits')->insert([
             'job_card_id'=>$job,'entity_type'=>'JOB_CARD_LABOUR','entity_id'=>$line,
             'action'=>$before?'OSL_EDIT_FIX55':'OSL_ADD_FIX55',
             'before_json'=>$before?json_encode($before,JSON_INVALID_UTF8_SUBSTITUTE):null,
             'after_json'=>json_encode((array)DB::table('job_card_labour')->find($line),JSON_INVALID_UTF8_SUBSTITUTE),
             'reason'=>$d['reason']??'Admin manually added OSL billing line',
             'actor_id'=>$actor->id,'actor_name'=>(string)($actor->name??'Admin'),
             'created_at'=>now(),'updated_at'=>now(),
          ]);
        },3);
        return redirect()->route('erp.job-cards.show',$job)
            ->with('success','OSL billing line saved; GST and final total updated.')
            ->withFragment('am90-labour-billing');
    }

    /** FIX91: strict Admin-only deletion of DIRECT OSL billing lines.
     * Approved-estimate lines and purchase-linked costs are not silently deleted.
     */
    public function delete(Request $request,int $job,int $line): RedirectResponse {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active
            && strtolower(trim((string)$actor->role))==='admin',403,
            'Admin permission required to delete OSL billing.');
        $data=$request->validate(['reason'=>['required','string','min:5','max:1000']]);
        DB::transaction(function()use($job,$line,$actor,$data){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            $stage=strtolower(trim((string)($card->status??'')));
            if(!in_array($stage,['wip','in progress','in-progress','work in progress'],true)
                || !empty($card->invoice_id)
                || DB::table('invoices')->where('job_card_id',$job)
                    ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists())
                throw ValidationException::withMessages(['reason'=>'Invoiced/closed or non-WIP Job Card cannot delete OSL. Use authorised correction workflow.']);
            $old=DB::table('job_card_labour')->where('job_card_id',$job)
                ->where('id',$line)->lockForUpdate()->first();
            abort_unless($old,404);
            if(strtoupper(trim((string)($old->mechanic_name??'')))!=='OSL')
                throw ValidationException::withMessages(['reason'=>'Only direct OSL billing can be deleted here. ROT labour requires authorised reversal.']);
            if(!empty($old->source_estimate_item_id))
                throw ValidationException::withMessages(['reason'=>'OSL from approved estimate cannot be deleted directly. Reverse the estimate release first.']);
            if(\Illuminate\Support\Facades\Schema::hasTable('workshop_purchases')){
                $cols=\Illuminate\Support\Facades\Schema::getColumnListing('workshop_purchases');
                foreach(['customer_billing_line_id','job_card_labour_id','billing_line_id'] as $col){
                    if(in_array($col,$cols,true) &&
                        DB::table('workshop_purchases')->where('job_card_id',$job)->where($col,$line)->exists())
                        throw ValidationException::withMessages(['reason'=>'OSL billing linked to supplier purchase. Reverse its linked purchase/billing first.']);
                }
            }
            $totals=JobCardTaxTotals::forJob($job);
            foreach(['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'] as $key){
                if(abs((float)($card->$key??0)-(float)($totals[$key]??0))>0.021)
                    throw ValidationException::withMessages(['reason'=>'Existing billing totals mismatch. Reconcile before deleting.']);
            }
            DB::table('job_card_labour')->where('id',$line)->where('job_card_id',$job)->delete();
            $after=JobCardTaxTotals::forJob($job);
            DB::table('job_cards')->where('id',$job)->update([
              'parts_total'=>$after['parts_total'],'labour_total'=>$after['labour_total'],
              'gst_total'=>$after['gst_total'],'pre_round_total'=>$after['pre_round_total'],
              'round_off'=>$after['round_off'],'grand_total'=>$after['grand_total'],
              'updated_at'=>now(),
            ]);
            DB::table('job_card_change_audits')->insert([
               'job_card_id'=>$job,'entity_type'=>'JOB_CARD_LABOUR','entity_id'=>$line,
               'action'=>'OSL_DELETE_FIX91','before_json'=>json_encode((array)$old,JSON_INVALID_UTF8_SUBSTITUTE),
               'after_json'=>null,'reason'=>$data['reason'],'actor_id'=>$actor->id,
               'actor_name'=>(string)($actor->name??'Admin'),'created_at'=>now(),'updated_at'=>now()
            ]);
        },3);
        return redirect()->route('erp.job-cards.show',$job)
           ->with('success','Direct OSL billing removed with Admin audit.')
           ->withFragment('am90-labour-billing');
    }

}
