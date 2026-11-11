<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\RotAdminCorrection;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * V647: Historic ROT Start and End DateTime correction, fully audited.
 * NEVER restarts a ROT or alters productive seconds, bills, GST or raw events.
 */
final class AdminRotTimelineController extends Controller
{
    public function update(Request $request, string $session): RedirectResponse
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active
            && strtolower(trim((string)$actor->role))==='admin',403,
            'Admin permission required for ROT Start/End DateTime correction.');

        $data=$request->validate([
            'start_datetime'=>['required','string','max:30',
                'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/'],
            'end_datetime'=>['required','string','max:30',
                'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/'],
            'expected_start'=>['required','string','max:32'],
            'expected_end'=>['required','string','max:32'],
            'reason'=>['required','string','min:12','max:255'],
            'note'=>['nullable','string','max:1000'],
        ]);

        $start=self::parseLocal($data['start_datetime']);
        $end=self::parseLocal($data['end_datetime']);
        $reason=trim((string)$data['reason']);
        $note=trim((string)($data['note']??''));

        DB::transaction(function() use($session,$actor,$data,$start,$end,$reason,$note){
            $lookup=DB::table('rot_sessions')->where('id',$session)->first();
            abort_unless($lookup,404,'ROT session not found');
            // All identity/financial guards run under lock, before any edit.
            $card=DB::table('job_cards')->where('id',(int)$lookup->job_card_id)->lockForUpdate()->first();
            abort_unless($card,404,'Linked Job Card not found');
            $rot=DB::table('rot_sessions')->where('id',$session)->lockForUpdate()->first();
            abort_unless($rot && (int)$rot->job_card_id===(int)$card->id,409,
                'ROT changed. Refresh Job Card before correcting');

            $stage=strtolower(trim((string)$card->status));
            if(!in_array($stage,['wip','in progress','in-progress','work in progress',
                'completed','closed','invoiced'],true)){
                throw ValidationException::withMessages(['start_datetime'=>
                    'Historical ROT Start/End correction requires WIP, Work Complete, Closed, or Invoiced Job Card.']);
            }
            // Completed-only: modifying live/paused timestamps can silently corrupt
            // future Pause/Resume/Complete durations.
            if($rot->status!=='Completed' || !$rot->first_start_at || !$rot->end_time){
                throw ValidationException::withMessages(['start_datetime'=>
                    'Complete this ROT first. Start/End date correction is not allowed on Running, Paused or Assigned ROT.']);
            }
            if(!$rot->labour_line_id){
                throw ValidationException::withMessages(['start_datetime'=>'Linked Labour line missing. No values changed.']);
            }
            $line=DB::table('job_card_labour')
                ->where('id',$rot->labour_line_id)->where('job_card_id',$card->id)
                ->lockForUpdate()->first();
            if(!$line || (!empty($line->rot_session_id) && (string)$line->rot_session_id!==$session)){
                throw ValidationException::withMessages(['start_datetime'=>
                    'ROT and Job Card Labour line do not match. Refresh required.']);
            }
            $currentStart=CarbonImmutable::parse((string)$rot->first_start_at,'Asia/Kolkata')->format('Y-m-d H:i:s');
            $currentEnd=CarbonImmutable::parse((string)$rot->end_time,'Asia/Kolkata')->format('Y-m-d H:i:s');
            if(!hash_equals($currentStart,(string)$data['expected_start']) ||
                !hash_equals($currentEnd,(string)$data['expected_end'])){
                throw ValidationException::withMessages(['start_datetime'=>
                    'Another user changed ROT Start/End. Refresh the page before saving again.']);
            }
            $opened=$card->created_at
                ? CarbonImmutable::parse((string)$card->created_at,'Asia/Kolkata')
                : null;
            if(!$opened){
                throw ValidationException::withMessages(['start_datetime'=>
                    'Job Card opening timestamp is missing. Cannot verify ROT Start.']);
            }
            self::assertRange($opened,$start,$end);
            if($end->greaterThan(CarbonImmutable::now('Asia/Kolkata'))){
                throw ValidationException::withMessages(['end_datetime'=>
                    'ROT End cannot be a future date/time.']);
            }
            // Once an invoice has been issued, a historical ROT must not appear
            // to have ended later than the invoice issuance (no finance change).
            $invoice=DB::table('invoices')->where('job_card_id',$card->id)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")
                ->orderByDesc('id')->first();
            if($invoice && $invoice->created_at &&
                $end->greaterThan(CarbonImmutable::parse((string)$invoice->created_at,'Asia/Kolkata'))){
                throw ValidationException::withMessages(['end_datetime'=>
                    'ROT End cannot be after an issued invoice date/time. Correct invoice history through Finance if necessary.']);
            }
            $newStart=$start->format('Y-m-d H:i:s');
            $newEnd=$end->format('Y-m-d H:i:s');
            if($currentStart===$newStart && $currentEnd===$newEnd){
                throw ValidationException::withMessages(['start_datetime'=>
                    'Start and End are unchanged. Enter different corrected values.']);
            }
            $before=[
                'rot_start'=>$currentStart,'rot_end'=>$currentEnd,
                'completed_at'=>(string)($rot->completed_at??''),
                'labour_rot_start'=>(string)($line->rot_start??''),
                'labour_rot_end'=>(string)($line->rot_end??''),
                'elapsed_tat_seconds'=>CarbonImmutable::parse($currentStart,'Asia/Kolkata')
                    ->diffInSeconds(CarbonImmutable::parse($currentEnd,'Asia/Kolkata')),
                'productive_seconds'=>(int)$rot->total_seconds,
                'job_card_opened_at'=>$opened->format('Y-m-d H:i:s'),
                'invoice_unchanged'=>true,
            ];

            DB::table('rot_sessions')->where('id',$session)->update([
                'first_start_at'=>$newStart,'end_time'=>$newEnd,
                'completed_at'=>$newEnd,'updated_at'=>now(),
            ]);
            DB::table('job_card_labour')->where('id',$line->id)->update([
                'rot_start'=>$newStart,'rot_end'=>$newEnd,
            ]);
            // Original rot_session_events, rot_mechanic_segments, raw pause
            // history and total_seconds remain untouched, as do invoices.
            $after=[
                'rot_start'=>$newStart,'rot_end'=>$newEnd,'completed_at'=>$newEnd,
                'labour_rot_start'=>$newStart,'labour_rot_end'=>$newEnd,
                'elapsed_tat_seconds'=>$end->getTimestamp()-$start->getTimestamp(),
                'productive_seconds'=>(int)$rot->total_seconds,
                'job_card_opened_at'=>$opened->format('Y-m-d H:i:s'),
                'raw_events_unchanged'=>true,'billing_unchanged'=>true,
            ];
            RotAdminCorrection::query()->create([
                'job_card_id'=>(int)$card->id,
                'job_card_no'=>(string)$card->job_no,
                'labour_line_id'=>(int)$line->id,
                'rot_session_id'=>$session,
                'action'=>'CORRECT_START_END_DATETIME',
                'reason'=>$reason,
                'note'=>'ROT Start/End DateTime corrected by Admin. Productive seconds, raw events, pause history, invoices, rates and GST unchanged. '.$note,
                'old_json'=>json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
                'new_json'=>json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
                'admin_user'=>mb_substr(trim((string)($actor->email?:$actor->name?:'ADMIN')),0,100),
            ]);
        },2);

        return redirect()->route('erp.rot.history',$session)->with('success',
            'ROT Start/End dates corrected with Admin audit. Job Card opening validated. Productive Time and invoice unchanged.');
    }

    public static function parseLocal(string $value): CarbonImmutable
    {
        $candidate=preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$value)
            ? $value.':00' : $value;
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/',$candidate)){
            throw ValidationException::withMessages(['start_datetime'=>'Date/time format must be YYYY-MM-DD HH:MM:SS.']);
        }
        try{
            $date=CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s',$candidate,'Asia/Kolkata');
        }catch(\Throwable $error){
            throw ValidationException::withMessages(['start_datetime'=>'Invalid calendar date or time.']);
        }
        if(!$date || $date->format('Y-m-d\TH:i:s')!==$candidate){
            throw ValidationException::withMessages(['start_datetime'=>'Invalid calendar date or time.']);
        }
        return $date;
    }

    public static function assertRange(CarbonImmutable $jobOpened,
                                       CarbonImmutable $start, CarbonImmutable $end): void
    {
        if($start->lessThan($jobOpened)){
            throw ValidationException::withMessages(['start_datetime'=>
                'ROT Start cannot be earlier than Job Card opening date/time ('.$jobOpened->format('d-m-Y h:i:s A').').']);
        }
        if(!$end->greaterThan($start)){
            throw ValidationException::withMessages(['end_datetime'=>
                'ROT End date/time must be later than ROT Start date/time.']);
        }
    }
}
