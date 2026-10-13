<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminJobCardDeletePageController extends Controller
{
    /**
     * JC_DELETE_GUARD_P10 — only truly empty Draft/Open/Cancelled cards.
     * Any work, inspection, stock, estimate, ROT, payment or invoice blocks removal.
     * Never silently cascade operational records.
     */
    public function destroy(Request $request, int $job): RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin permission required.');
        $data=$request->validate([
            'confirm_job_no'=>['required','string','max:80'],
            'delete_reason'=>['required','string','min:12','max:500'],
        ]);
        $jobNo=DB::transaction(function () use($job,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            if(!$card)abort(404,'Job Card not found.');
            if(trim((string)$card->job_no)!==trim($data['confirm_job_no'])) {
                throw ValidationException::withMessages(['confirm_job_no'=>'Job Card number does not match. Nothing was deleted.']);
            }
            if(!in_array(strtolower(trim((string)$card->status)),['open','cancelled'],true) ||
                !empty($card->invoice_id) || !empty($card->current_estimate_id) ||
                !empty($card->work_started_at) || !empty($card->closed_at) ||
                !empty($card->rot_start_at) || !empty($card->completed_at) ||
                abs((float)$card->grand_total)>0.009 ||
                abs((float)$card->parts_total)>0.009 ||
                abs((float)$card->labour_total)>0.009 ||
                !in_array(strtolower(trim((string)$card->diagnosis_status)),['','pending','not_started'],true) ||
                !in_array(strtolower(trim((string)$card->inspection_status)),['','pending','not_started'],true)
            ){
                throw ValidationException::withMessages(['delete_reason'=>'Deletion blocked: this Job Card is no longer empty/unprocessed. Close or handle its linked workflow without deleting financial/stock/work records.']);
            }
            // Inspect every existing table with a job_card_id or job_id FK-like column.
            // Unknown future modules therefore block deletion by default.
            $refs=DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('job_card_id','job_id')");
            foreach($refs as $ref){
                $table=(string)$ref->TABLE_NAME;
                if(in_array($table,['job_cards','job_card_change_audits','job_card_delete_audit'],true))continue;
                // Identifier comes only from system schema, not user-supplied input.
                if(DB::table($table)->where((string)$ref->COLUMN_NAME,$job)->exists()){
                    throw ValidationException::withMessages(['delete_reason'=>'Deletion blocked: '.$table.' has linked records. No data was deleted.']);
                }
            }
            if(!\Illuminate\Support\Facades\Schema::hasTable('job_card_delete_audit')){
                throw ValidationException::withMessages(['delete_reason'=>'Deletion audit storage not available. Nothing deleted.']);
            }
            $history=DB::table('job_card_change_audits')->where('job_card_id',$job)->orderBy('id')->get()->map(fn($a)=>(array)$a)->all();
            $snapshot=json_encode(['job_card'=>(array)$card,'change_history'=>$history],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
            if($snapshot===false)throw new \RuntimeException('Cannot archive delete snapshot.');
            DB::table('job_card_delete_audit')->insert([
                'job_card_id'=>$job,
                'job_no'=>(string)$card->job_no,
                'customer_name'=>(string)$card->customer_name,
                'vehicle_reg_no'=>(string)($card->vehicle_reg_no??''),
                'job_status'=>(string)$card->status,
                'delete_reason'=>trim($data['delete_reason']),
                'snapshot_json'=>$snapshot,
                'deleted_by'=>mb_substr((string)($user->email ?: $user->name ?: 'ADMIN'),0,120),
                'deleted_at'=>now('Asia/Kolkata')
            ]);
            $removed=DB::table('job_cards')->where('id',$job)->delete();
            if($removed!==1)throw new \RuntimeException('Concurrent Job Card change; deletion rolled back.');
            return (string)$card->job_no;
        },3);
        return redirect()->route('erp.job-cards.index')->with('success','Empty Job Card '.$jobNo.' deleted with reason and immutable snapshot audit.');
    }
}
