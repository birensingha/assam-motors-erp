<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * P55: Completed ROT alteration with mandatory Admin reason and note.
 * Original events/history are immutable. Elapsed start/end window (TAT) can
 * be corrected, but recorded productive_seconds are never fabricated.
 * Financial editing requires verified taxable/GST basis; no issued invoice edit.
 */
final class JobCardRotAlterationController extends Controller
{
    private const REASONS=[
        'Wrong ROT selected',
        'Wrong mechanic selected',
        'Start / End time correction',
        'Standard Hours / Rate correction',
        'Wrong ROT + Timing correction',
        'Other',
    ];

    private function block(string $message,string $field='reason'): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    public function save(Request $request, int $job, int $labour): RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin permission required.');
        $data=$request->validate([
            'alteration_reason'=>['required','string','in:'.implode(',',self::REASONS)],
            'admin_note'=>['required','string','min:3','max:1000'],
            'mechanic_id'=>['nullable','integer','min:1'],
            'rot_code'=>['nullable','string','max:80'],
            'rot_start'=>['nullable','date_format:Y-m-d\TH:i'],
            'rot_end'=>['nullable','date_format:Y-m-d\TH:i'],
            'standard_hours'=>['required','numeric','gt:0','lte:1000'],
            'labour_rate'=>['required','numeric','min:0','lte:10000000'],
            'discount_percent'=>['required','numeric','between:0,100'],
            'expected_old_total'=>['required','numeric','min:0'],
            'expected_old_mechanic'=>['required','integer','min:0'],
        ]);
        DB::transaction(function() use($job,$labour,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            $status=strtolower(trim((string)$card->status));
            if(in_array($status,['closed','invoiced','cancelled'],true)||!empty($card->invoice_id)){
                $this->block('Closed/Invoiced/Cancelled Job Card cannot directly alter ROT. Use an approved accounting reversal.');
            }
            if(Schema::hasTable('invoices') && DB::table('invoices')->where('job_card_id',$job)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                $this->block('Issued Invoice exists. Old issued totals cannot be silently changed.');
            }
            if(!Schema::hasTable('job_card_change_audits')){
                $this->block('Audit history table missing. ROT edit stopped safely.');
            }
            $line=DB::table('job_card_labour')->where('id',$labour)
                ->where('job_card_id',$job)->lockForUpdate()->first();
            abort_unless($line,404);
            if(abs((float)$data['expected_old_total']-(float)$line->total)>0.015 ||
               (int)$data['expected_old_mechanic']!==(int)($line->mechanic_id??0)){
               $this->block('Labour row was changed by another session. Refresh the Job Card Edit page.');
            }

            $before=(array)$line;
            $oldHours=(float)($line->standard_hours??0);
            $oldRate=(float)($line->labour_rate??0);
            $oldDiscount=(float)($line->discount_percent??0);
            $hours=round((float)$data['standard_hours'],3);
            $rate=round((float)$data['labour_rate'],2);
            $discount=round((float)$data['discount_percent'],2);
            $priceChanged=abs($hours-$oldHours)>0.0005||abs($rate-$oldRate)>0.005||
                abs($discount-$oldDiscount)>0.005;
            $baseUpdate=[
                'standard_hours'=>$hours,
                'labour_rate'=>$rate,
                'discount_percent'=>$discount,
            ];

            $requestedCode=trim((string)($data['rot_code']??''));
            $rotChange=$requestedCode!=='' && strcasecmp($requestedCode,(string)($line->rot_code??''))!==0;
            if($rotChange){
                if(!Schema::hasTable('labour_master'))$this->block('ROT Master is not available for verified ROT selection.','rot_code');
                $master=DB::table('labour_master')->where('rot_code',$requestedCode)->first();
                if(!$master)$this->block('Choose an existing ROT code from the searchable ROT Master.','rot_code');
                $baseUpdate['rot_code']=(string)$master->rot_code;
                if(Schema::hasColumn('job_card_labour','rot_master_id'))$baseUpdate['rot_master_id']=$master->id;
                if(isset($master->rot_description))$baseUpdate['description']=$master->rot_description;
            }
            $staff=null;
            $mechanicChange=array_key_exists('mechanic_id',$data) && (int)$data['mechanic_id']>0
                && (int)$data['mechanic_id']!==(int)($line->mechanic_id??0);
            if($mechanicChange){
                $staff=DB::table('staff')->where('id',(int)$data['mechanic_id'])
                    ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->first();
                if(!$staff)$this->block('Choose an active mechanic.','mechanic_id');
                $baseUpdate['mechanic_id']=$staff->id;
                $baseUpdate['mechanic_name']=$staff->name;
            }

            $startRaw=trim((string)($data['rot_start']??''));
            $endRaw=trim((string)($data['rot_end']??''));
            $currentStart=$line->rot_start ? CarbonImmutable::parse($line->rot_start,'Asia/Kolkata')->format('Y-m-d H:i') : '';
            $currentEnd=$line->rot_end ? CarbonImmutable::parse($line->rot_end,'Asia/Kolkata')->format('Y-m-d H:i') : '';
            $newStart=$startRaw!==''?CarbonImmutable::createFromFormat('!Y-m-d\TH:i',$startRaw,'Asia/Kolkata'):null;
            $newEnd=$endRaw!==''?CarbonImmutable::createFromFormat('!Y-m-d\TH:i',$endRaw,'Asia/Kolkata'):null;
            if(($startRaw!==''&&!$newStart)||($endRaw!==''&&!$newEnd))$this->block('Invalid IST date/time; use the picker.','rot_start');
            $timeChanged=($startRaw!=='' && $newStart->format('Y-m-d H:i')!==$currentStart)
                 || ($endRaw!=='' && $newEnd->format('Y-m-d H:i')!==$currentEnd);
            if($timeChanged){
                if(!$newStart||!$newEnd)$this->block('Both ROT Start and ROT End are required to correct the time window.','rot_start');
                if($newEnd->lessThan($newStart))$this->block('ROT End cannot be before ROT Start.','rot_end');
                if($newEnd->greaterThan(CarbonImmutable::now('Asia/Kolkata')->addMinutes(10)))
                    $this->block('ROT End cannot be in the future.','rot_end');
                if(!empty($card->vehicle_inward_at) &&
                  $newStart->lessThan(CarbonImmutable::parse($card->vehicle_inward_at,'Asia/Kolkata')))
                    $this->block('ROT Start cannot precede vehicle inward timestamp.','rot_start');
                if(strtoupper(trim((string)($line->execution_status??'')))!=='COMPLETED')
                    $this->block('Only completed ROT can backdate its actual Start/End. Running/Paused ROT must use workflow actions.','rot_start');
                $baseUpdate['rot_start']=$newStart->toDateTimeString();
                $baseUpdate['rot_end']=$newEnd->toDateTimeString();
                // Calendar turnaround. Do NOT replace actual productive seconds.
                $baseUpdate['total_hours']=round($newStart->diffInMinutes($newEnd)/60,2);
            }

            $session=null;
            if(Schema::hasTable('rot_sessions')){
                $q=DB::table('rot_sessions')->where('job_card_id',$job);
                if(!empty($line->rot_session_id))$q->where('id',(string)$line->rot_session_id);
                else $q->where('labour_line_id',$labour);
                $session=$q->lockForUpdate()->first();
            }
            if($session && ($timeChanged||$mechanicChange||$rotChange)){
                if(strtoupper(trim((string)$session->status))!=='COMPLETED')
                    $this->block('A linked ROT session is not Completed. Use ROT Work actions for a running job.');
                if($mechanicChange){
                    $segments=Schema::hasTable('rot_mechanic_segments')?
                        DB::table('rot_mechanic_segments')->where('rot_session_id',$session->id)->count():0;
                    if($segments>1)$this->block('ROT has multiple mechanic work segments. Cannot rewrite historical mechanic attribution; use authorised segment correction.');
                }
            }

            // Only apply price changes where legacy tax basis is fully reconciled.
            $finance=null;
            if($priceChanged){
                $parts=DB::table('job_card_parts')->where('job_card_id',$job)->get();
                $labourRows=DB::table('job_card_labour')->where('job_card_id',$job)->get();
                $included=$labourRows->filter(fn($x)=>(int)($x->billing_included??1)===1);
                $oldParts=round((float)$parts->sum('total'),2);
                $oldLabour=round((float)$included->sum('total'),2);
                $oldGst=round((float)$parts->concat($included)->sum(
                    fn($x)=>round((float)$x->total*(float)($x->gst_percent??0)/100,2)),2);
                $oldPre=round($oldParts+$oldLabour+$oldGst,2);
                $oldLineCalc=round($oldHours*$oldRate*(1-$oldDiscount/100),2);
                if(abs($oldParts-(float)$card->parts_total)>0.05||
                   abs($oldLabour-(float)$card->labour_total)>0.05||
                   abs($oldGst-(float)($card->gst_total??0))>0.05||
                   abs($oldPre-(float)($card->pre_round_total??$oldPre))>0.05||
                   abs($oldLineCalc-(float)$line->total)>0.05){
                    $this->block('Historical GST or billing basis differs from current ROT master. Time/Mechanic corrections are permitted separately; rate/discount/Std Hours must use reconciled billing edit first.','labour_rate');
                }
                $oldDiscAmt=round($oldHours*$oldRate*$oldDiscount/100,2);
                $newDiscAmt=round($hours*$rate*$discount/100,2);
                $newTaxable=round($hours*$rate-$newDiscAmt,2);
                $baseUpdate['total']=$newTaxable;
                $baseUpdate['discount_amount']=$newDiscAmt;
                $delta=round($newTaxable-(float)$line->total,2);
                $newLabour=round($oldLabour+((int)($line->billing_included??1)===1?$delta:0),2);
                $newGst=round($oldGst+((int)($line->billing_included??1)===1?
                    round($newTaxable*(float)($line->gst_percent??0)/100,2)-
                    round((float)$line->total*(float)($line->gst_percent??0)/100,2):0),2);
                $pre=round($oldParts+$newLabour+$newGst,2);
                $grand=round($pre,0,PHP_ROUND_HALF_UP);
                $roundOff=round($grand-$pre,2);
                $finance=['parts_total'=>$oldParts,'labour_total'=>$newLabour,'gst_total'=>$newGst,
                    'pre_round_total'=>$pre,'round_off'=>$roundOff,'grand_total'=>$grand,
                    'updated_at'=>now()];
            }

            if(!$priceChanged && !$timeChanged && !$rotChange && !$mechanicChange)
                $this->block('No ROT changes found. Change a value before saving.');

            DB::table('job_card_labour')->where('id',$labour)->where('job_card_id',$job)->update($baseUpdate);
            $oldSession=$session?(array)$session:null;
            if($session){
                $sessionChanges=[];
                if($timeChanged){
                    $sessionChanges['first_start_at']=$newStart->toDateTimeString();
                    $sessionChanges['end_time']=$newEnd->toDateTimeString();
                    $sessionChanges['completed_at']=$newEnd->toDateTimeString();
                    // total_seconds and staff mechanic-segment productive_seconds remain
                    // unchanged: paid productivity != corrected wall-clock TAT.
                }
                if($mechanicChange){
                    $sessionChanges['staff_id']=$staff->id;
                    $sessionChanges['staff_name']=$staff->name;
                    $sessionChanges['staff_code']=$staff->staff_code;
                    if(Schema::hasTable('rot_mechanic_segments')){
                        DB::table('rot_mechanic_segments')->where('rot_session_id',$session->id)->update([
                            'staff_id'=>$staff->id,'staff_name'=>$staff->name,'staff_code'=>$staff->staff_code,
                            'change_reason'=>$data['alteration_reason'],'change_note'=>$data['admin_note'],
                            'changed_by'=>(string)($user->name?:$user->email)
                        ]);
                    }
                }
                if($rotChange){
                    $sessionChanges['rot_code']=$baseUpdate['rot_code'];
                    if(isset($baseUpdate['description']))$sessionChanges['rot_desc']=$baseUpdate['description'];
                }
                if($priceChanged){
                    $sessionChanges['std_hours']=$hours;
                    $sessionChanges['rate']=$rate;
                    $sessionChanges['amount']=$baseUpdate['total'];
                }
                if($sessionChanges){
                    $sessionChanges['updated_at']=now();
                    DB::table('rot_sessions')->where('id',$session->id)->update($sessionChanges);
                }
                if(Schema::hasTable('rot_admin_corrections')){
                    DB::table('rot_admin_corrections')->insert([
                        'job_card_id'=>$job,'job_card_no'=>$card->job_no,
                        'labour_line_id'=>$labour,'rot_session_id'=>$session->id,
                        'action'=>'COMPLETED_ROT_ALTERATION','reason'=>$data['alteration_reason'],
                        'note'=>$data['admin_note'],
                        'old_json'=>json_encode($oldSession,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        'new_json'=>json_encode((array)DB::table('rot_sessions')->where('id',$session->id)->first(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        'admin_user'=>(string)($user->name?:$user->email),'created_at'=>now(),
                    ]);
                }
            }
            if($finance)DB::table('job_cards')->where('id',$job)->update($finance);
            $after=(array)DB::table('job_card_labour')->where('id',$labour)->first();
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,'entity_type'=>'JOB_CARD_LABOUR',
                'entity_id'=>$labour,'action'=>'P55_COMPLETED_ROT_ALTERATION',
                'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'reason'=>$data['alteration_reason'].' — '.$data['admin_note'],
                'actor_id'=>$user->id,'actor_name'=>(string)($user->name?:$user->email),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.job-cards.edit',$job)
            ->with('success','Completed ROT alteration saved with Admin audit. Actual productive time/events preserved.');
    }
}
