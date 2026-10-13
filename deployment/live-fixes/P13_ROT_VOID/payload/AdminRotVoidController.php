<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * P13_ROT_VOID_AUDIT: cancel mistaken/test completed ROT for HR production analytics
 * without overwriting work history or touching finance, labour billing or stock.
 */
final class AdminRotVoidController extends Controller
{
    public function voidCompleted(Request $request,int $job,string $rot):RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin permission required.');
        $data=$request->validate([
            'confirm_rot_id'=>['required','string','max:40'],
            'reason'=>['required','string','min:12','max:500'],
            'acknowledge_productivity'=>['accepted'],
        ]);
        if(!hash_equals($rot,trim((string)$data['confirm_rot_id']))) {
            throw ValidationException::withMessages(['confirm_rot_id'=>'ROT identifier mismatch. No record changed.']);
        }
        DB::transaction(function()use($job,$rot,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            if(!$card)abort(404);
            if(strtolower(trim((string)$card->status))!=='cancelled')
                throw ValidationException::withMessages(['reason'=>'ROT production void is only enabled for CANCELLED Job Cards.']);
            if(!empty($card->invoice_id) ||
                (Schema::hasTable('invoices') && DB::table('invoices')->where('job_card_id',$job)->exists()))
                throw ValidationException::withMessages(['reason'=>'Invoice-linked Job Card requires separate Finance approval; ROT not voided.']);
            if(!Schema::hasTable('job_card_rot_voids'))abort(503,'ROT void audit storage not installed.');
            $session=DB::table('rot_sessions')->where('id',$rot)->where('job_card_id',$job)->lockForUpdate()->first();
            if(!$session)abort(404);
            if(strtolower(trim((string)$session->status))!=='completed')
                throw ValidationException::withMessages(['reason'=>'Only already completed ROT sessions can be marked void for reporting.']);
            if(DB::table('job_card_rot_voids')->where('rot_session_id',$rot)->exists())
                throw ValidationException::withMessages(['reason'=>'This ROT was previously voided. No duplicate adjustment made.']);
            $snapshot=json_encode((array)$session,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
            DB::table('job_card_rot_voids')->insert([
                'rot_session_id'=>$rot,
                'job_card_id'=>$job,
                'reason'=>trim((string)$data['reason']),
                'original_snapshot'=>$snapshot,
                'actor_id'=>(int)$user->id,
                'created_at'=>now(),
            ]);
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$job,
                'entity_type'=>'ROT_SESSION',
                'entity_id'=>null,
                'action'=>'ROT_VOIDED_FOR_REPORTING',
                'before_json'=>$snapshot,
                'after_json'=>json_encode([
                    'rot_session_id'=>$rot,
                    'reporting_status'=>'VOIDED',
                    'preserve_original_seconds'=>(int)$session->total_seconds,
                    'job_card_remains_cancelled'=>true
                ],JSON_THROW_ON_ERROR),
                'reason'=>trim((string)$data['reason']),
                'actor_id'=>(int)$user->id,
                'actor_name'=>mb_substr((string)($user->email ?: $user->name ?: 'ADMIN'),0,180),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        },3);
        return redirect()->route('erp.job-cards.reversal',$job)
            ->with('success','ROT marked VOID for HR productivity/TAT reporting. Original work, billing, stock and audit records remain unchanged.');
    }
}
