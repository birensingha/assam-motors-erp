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
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),403);
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
            ->withFragment('jc55-osl');
    }
}
