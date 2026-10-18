<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\RotAdminCorrection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * V645 — Admin-only ROT time corrections.
 *
 * Standard Hours is an operational target and is NOT a customer price edit.
 * Corrected productive time is the effective displayed/stored work duration,
 * while original ROT/segment timestamps remain unchanged in history.
 * An immutable before/after correction audit records every accepted change.
 */
final class AdminRotTimeEditController extends Controller
{
    public function standard(Request $request, string $session): RedirectResponse
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'new_std_hours'=>['required','numeric','between:0.01,168','decimal:0,2'],
            'expected_std_hours'=>['required','numeric','min:0','max:168'],
            'reason'=>['required','string','min:12','max:255'],
            'note'=>['nullable','string','max:1000'],
        ]);
        $newHours=round((float)$data['new_std_hours'],2);
        $expected=round((float)$data['expected_std_hours'],2);
        $this->apply($session,$admin,'standard',$newHours,$expected,trim($data['reason']),trim($data['note']??''));
        return redirect()->route('erp.rot.history',$session)->with('success',
            'ROT Standard Hours updated with Admin audit. Billing Rate/Discount/GST/Total unchanged.');
    }

    public function actual(Request $request, string $session): RedirectResponse
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'new_actual_hms'=>['required','string','regex:/^\d{1,3}:[0-5]\d:[0-5]\d$/'],
            'expected_actual_seconds'=>['required','integer','between:0,604800'],
            'reason'=>['required','string','min:12','max:255'],
            'note'=>['nullable','string','max:1000'],
        ]);
        if(!preg_match('/^(\d{1,3}):([0-5]\d):([0-5]\d)$/',$data['new_actual_hms'],$m)){
            throw ValidationException::withMessages(['new_actual_hms'=>'Use HH:MM:SS, for example 00:45:30.']);
        }
        $seconds=(int)$m[1]*3600+(int)$m[2]*60+(int)$m[3];
        if($seconds>604800){
            throw ValidationException::withMessages(['new_actual_hms'=>'Corrected productive time cannot exceed 168 hours.']);
        }
        $this->apply($session,$admin,'actual',$seconds,(int)$data['expected_actual_seconds'],
            trim($data['reason']),trim($data['note']??''));
        return redirect()->route('erp.rot.history',$session)->with('success',
            'Audited Actual/Productive Time correction saved. Original start/pause/end events remain unchanged.');
    }

    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        // isAdmin() includes supervisors. Only real Admin role may override a ROT clock.
        abort_unless($user instanceof ErpAuthUser && $user->active &&
            strtolower(trim((string)$user->role))==='admin',403,
            'Admin permission required for ROT Time Correction.');
        return $user;
    }

    private function apply(string $session, ErpAuthUser $admin, string $type, float|int $new,
                           float|int $expected, string $reason, string $note): void
    {
        DB::transaction(function()use($session,$admin,$type,$new,$expected,$reason,$note){
            // Acquire card before ROT when available; all locks are inside one transaction.
            $lookup=DB::table('rot_sessions')->where('id',$session)->first();
            abort_unless($lookup,404,'ROT session not found.');
            $card=DB::table('job_cards')->where('id',(int)$lookup->job_card_id)->lockForUpdate()->first();
            abort_unless($card,404,'Linked Job Card not found.');
            $rot=DB::table('rot_sessions')->where('id',$session)->lockForUpdate()->first();
            abort_unless($rot && (int)$rot->job_card_id===(int)$card->id,409,'ROT changed; refresh the Job Card.');

            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['wip','in-progress','in progress','work in progress'],true)){
                throw ValidationException::withMessages(['reason'=>
                    'ROT Time Correction is allowed only while Job Card is WIP. Use authorized lifecycle reopening first.']);
            }
            if(!empty($card->invoice_id) || DB::table('invoices')->where('job_card_id',$card->id)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['reason'=>
                    'Invoice-linked Job Card is financially locked. Reverse and reconcile the invoice before returning to WIP.']);
            }
            if(!in_array((string)$rot->status,['Assigned','Running','Paused','Completed'],true)){
                throw ValidationException::withMessages(['reason'=>'Invalid ROT status for correction.']);
            }
            if(!$rot->labour_line_id){
                throw ValidationException::withMessages(['reason'=>'ROT has no linked Job Card Labour line. No changes saved.']);
            }
            $line=DB::table('job_card_labour')->where('id',$rot->labour_line_id)
                ->where('job_card_id',$card->id)->lockForUpdate()->first();
            if(!$line || (!empty($line->rot_session_id) && $line->rot_session_id!==$session)){
                throw ValidationException::withMessages(['reason'=>'Linked Labour line differs from ROT. Refresh before editing.']);
            }

            $existing=$type==='standard'?(float)$rot->std_hours:(int)$rot->total_seconds;
            $same=$type==='standard'
                ? abs($existing-(float)$expected)<0.005
                : $existing===(int)$expected;
            if(!$same){
                throw ValidationException::withMessages(['reason'=>
                    'ROT time was updated by another device or mechanic. Refresh to see the current value.']);
            }
            if($type==='actual'){
                // Never rewrite an actively ticking segment. Pause first.
                if(!in_array($rot->status,['Paused','Completed'],true) || !$rot->first_start_at){
                    throw ValidationException::withMessages(['reason'=>
                        'To correct Actual/Productive Time, first Pause a Running ROT or use a Completed ROT.']);
                }
            }
            if($type==='standard' && abs((float)$new-$existing)<0.005 ||
                $type==='actual' && (int)$new===(int)$existing){
                throw ValidationException::withMessages(['reason'=>'New ROT time is identical to the saved time.']);
            }
            $auditBefore=[
                'rot_session_id'=>$rot->id,'job_card_id'=>(int)$card->id,'status'=>$rot->status,
                'standard_hours'=>(float)$rot->std_hours,'total_seconds'=>(int)$rot->total_seconds,
                'labour_line_id'=>(int)$line->id,
                'labour_standard_hours'=>(float)$line->standard_hours,
                'labour_productive_seconds'=>(int)$line->productive_seconds,
                'labour_total_hours'=>(float)$line->total_hours,
                'labour_taxable_total'=>(float)$line->total,
                'first_start_at'=>$rot->first_start_at,
                'start_time'=>$rot->start_time,'end_time'=>$rot->end_time,
                'completed_at'=>$rot->completed_at,
            ];
            if($type==='standard'){
                DB::table('rot_sessions')->where('id',$session)->update([
                    'std_hours'=>round((float)$new,2),'updated_at'=>now(),
                ]);
                DB::table('job_card_labour')->where('id',$line->id)->update([
                    'standard_hours'=>round((float)$new,2),
                ]);
            }else{
                DB::table('rot_sessions')->where('id',$session)->update([
                    'total_seconds'=>(int)$new,'updated_at'=>now(),
                ]);
                DB::table('job_card_labour')->where('id',$line->id)->update([
                    'productive_seconds'=>(int)$new,
                    'total_hours'=>round((int)$new/3600,2),
                ]);
                // rot_mechanic_segments / rot_session_events / pause history are
                // deliberately NOT changed: they are the original raw time evidence.
            }

            $afterRot=DB::table('rot_sessions')->where('id',$session)->first();
            $afterLine=DB::table('job_card_labour')->where('id',$line->id)->first();
            $auditAfter=$auditBefore;
            $auditAfter['standard_hours']=(float)$afterRot->std_hours;
            $auditAfter['total_seconds']=(int)$afterRot->total_seconds;
            $auditAfter['labour_standard_hours']=(float)$afterLine->standard_hours;
            $auditAfter['labour_productive_seconds']=(int)$afterLine->productive_seconds;
            $auditAfter['labour_total_hours']=(float)$afterLine->total_hours;
            $auditAfter['original_events_preserved']=true;
            $auditAfter['billing_amount_unchanged']=true;

            $actor=mb_substr(trim((string)($admin->email?:$admin->name?:'ADMIN')),0,100);
            $auditAction=$type==='standard'?'EDIT_STANDARD_HOURS':'CORRECT_PRODUCTIVE_TIME';
            $summary=$type==='standard'
                ?sprintf('Standard %.2fh -> %.2fh; customer charge unchanged. ',$existing,(float)$new)
                :sprintf('Productive %d sec -> %d sec; original start/pause/end history unchanged. ',
                    (int)$existing,(int)$new);
            RotAdminCorrection::query()->create([
                'job_card_id'=>(int)$card->id,'job_card_no'=>$card->job_no,
                'labour_line_id'=>(int)$line->id,'rot_session_id'=>$session,
                'action'=>$auditAction,'reason'=>$reason,
                'note'=>$summary.$note,
                'old_json'=>json_encode($auditBefore,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
                'new_json'=>json_encode($auditAfter,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
                'admin_user'=>$actor,
            ]);
        },2);
    }
}
