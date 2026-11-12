<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\RotAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM251: explicit Admin-only no-charge corrective ROT creation.
 * Reuses the existing RotAssignmentService (attendance, ROT segment and audit).
 * One DB transaction: create labour row -> assign native ROT -> link complaint.
 * No customer billing header, original ROT or payroll records modified.
 */
final class ComplaintReworkRotSetupController extends Controller
{
    public function create(Request $request, int $complaint, RotAssignmentService $service): RedirectResponse
    {
        $admin = $request->user();
        abort_unless(
            $admin instanceof ErpAuthUser && $admin->active && $admin->isAdmin(),
            403, 'Active ERP Owner/Admin login required.'
        );

        $data = $request->validate([
            'labour_master_id' => ['required','integer','min:1'],
            'reason' => ['required','string','min:12','max:1000'],
            'confirm_internal_no_charge' => ['required','accepted'],
        ]);

        foreach ([
            'job_cards','invoices','staff','labour_master','rot_sessions',
            'job_card_labour','job_card_change_audits','am_rot_work_complaints',
            'am_complaint_rework_links','am_complaint_rework_audits'
        ] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Missing protected ERP table: '.$table);
        }
        foreach (['billing_included','billing_reason','rot_note','rot_session_id'] as $column) {
            abort_unless(Schema::hasColumn('job_card_labour',$column),
                503, 'No-charge Labour control unavailable: '.$column);
        }

        DB::transaction(function () use ($admin,$complaint,$service,$data) {
            $case = DB::table('am_rot_work_complaints')
                ->where('id',$complaint)->lockForUpdate()->first();
            abort_unless($case,404,'Complaint not found.');

            $link = DB::table('am_complaint_rework_links')
                ->where('complaint_id',$complaint)->lockForUpdate()->first();
            if (!$link) {
                throw ValidationException::withMessages([
                    'rework'=>'Assign a different receiving mechanic before creating a rework ROT.'
                ]);
            }
            if (!empty($link->rework_rot_session_id)) {
                throw ValidationException::withMessages([
                    'rework'=>'A separate rework ROT is already linked. Duplicate creation blocked.'
                ]);
            }
            $jobId=(int)$case->job_card_id;
            if ((int)$link->job_card_id!==$jobId
                || (string)$link->original_rot_session_id!==(string)$case->rot_session_id
                || (int)$link->original_staff_id!==(int)$case->staff_id
                || (int)$link->assigned_staff_id===(int)$case->staff_id) {
                throw ValidationException::withMessages([
                    'rework'=>'Complaint assignment differs from original evidence. Admin review required.'
                ]);
            }

            $card=DB::table('job_cards')->where('id',$jobId)->lockForUpdate()->first();
            abort_unless($card,404,'Job Card not found.');
            if (!in_array(strtolower(trim((string)$card->status)),
                    ['wip','in progress','in-progress','work in progress'],true)) {
                throw ValidationException::withMessages([
                    'job'=>'Create corrective ROT only while Job Card is WIP; closed/QC/invoiced work requires authorised reopening.'
                ]);
            }
            if (!empty($card->invoice_id) ||
                DB::table('invoices')->where('job_card_id',$jobId)
                    ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()) {
                throw ValidationException::withMessages([
                    'job'=>'Active invoice found. Accounting reversal is required before new rework.'
                ]);
            }

            // Never overwrite/reopen the original Kapil Completed ROT.
            $original=DB::table('rot_sessions')
                ->where('id',(string)$case->rot_session_id)
                ->where('job_card_id',$jobId)->first();
            if (!$original || strtolower(trim((string)$original->status))!=='completed'
                || (int)$original->staff_id!==(int)$case->staff_id) {
                throw ValidationException::withMessages([
                    'rot'=>'Original Completed ROT/Staff evidence differs. No new ROT created.'
                ]);
            }

            $staffId=(int)$link->assigned_staff_id;
            $staff=DB::table('staff')->where('id',$staffId)
                ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->first();
            if (!$staff) {
                throw ValidationException::withMessages([
                    'staff'=>'Receiving mechanic is not active. Rework assignment requires review.'
                ]);
            }
            // Existing ASSIGNED ROT should be linked, never duplicated.
            $existing=DB::table('rot_sessions')
                ->where('job_card_id',$jobId)->where('staff_id',$staffId)
                ->whereRaw("LOWER(TRIM(COALESCE(status,'')))='assigned'")
                ->where('id','!=',(string)$case->rot_session_id)->exists();
            if ($existing) {
                throw ValidationException::withMessages([
                    'rot'=>'An ASSIGNED ROT already exists for this mechanic on this Job Card. Link the existing ROT using the dropdown; duplicate creation blocked.'
                ]);
            }

            $master=DB::table('labour_master')->where('id',(int)$data['labour_master_id'])->first();
            if (!$master || !in_array(strtolower(trim((string)($master->active??'Yes'))),
                    ['yes','active','1'],true)) {
                throw ValidationException::withMessages([
                    'labour_master_id'=>'Choose an active Labour/ROT from the master.'
                ]);
            }
            $std=round((float)($master->standard_hours??0),2);
            if ($std<=0 || $std>1000) {
                throw ValidationException::withMessages([
                    'labour_master_id'=>'Chosen ROT must have a valid positive standard time.'
                ]);
            }

            $financialKeys=['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'];
            $headerBefore=[];
            foreach ($financialKeys as $key) $headerBefore[$key]=$card->{$key}??null;
            $reason='Complaint #'.$complaint.' · internal no-charge rework: '.trim($data['reason']);
            $newLine=[
                'job_card_id'=>$jobId,
                'rot_master_id'=>(int)$master->id,
                'rot_code'=>(string)$master->rot_code,
                'description'=>(string)$master->rot_description,
                'standard_hours'=>$std,
                // Dual protection: zero monetary value AND explicitly nonbillable.
                'labour_rate'=>0,
                'discount_percent'=>0,
                'discount_amount'=>0,
                'gst_percent'=>0,
                'total'=>0,
                'billing_included'=>0,
                'billing_reason'=>$reason,
                'mechanic_id'=>0,
                'mechanic_name'=>'',
                'rot_start'=>null,
                'rot_end'=>null,
                'total_hours'=>0,
                'productive_seconds'=>0,
                'rot_note'=>$reason,
                'source_estimate_item_id'=>null,
                'execution_status'=>'PLANNED',
                'created_at'=>now('Asia/Kolkata'),
            ];
            $lineId=(int)DB::table('job_card_labour')->insertGetId($newLine);
            // Existing native service creates ID, mechanic segment, attendance
            // guard and event audit. It requires today's actual Staff Check-In.
            $rot=$service->assign($lineId,$staffId,
                'AM251 Complaint #'.$complaint.' Admin #'.(int)$admin->id);
            $rotId=(string)$rot->id;
            if (!$rotId || (int)$rot->staff_id!==$staffId
                || (int)$rot->job_card_id!==$jobId
                || strtolower(trim((string)$rot->status))!=='assigned'
                || $rotId===(string)$case->rot_session_id) {
                throw new \RuntimeException('AM251_NATIVE_ROT_ASSIGNMENT_VALIDATION_FAILED');
            }

            $afterLine=DB::table('job_card_labour')->where('id',$lineId)->first();
            if (!$afterLine || (int)$afterLine->mechanic_id!==$staffId ||
                (string)$afterLine->rot_session_id!==$rotId ||
                (int)$afterLine->billing_included!==0 ||
                abs((float)$afterLine->total)>0.001) {
                throw new \RuntimeException('AM251_NO_CHARGE_ROW_GUARD_FAILED');
            }

            $affected=DB::table('am_complaint_rework_links')->where('id',$link->id)
                ->whereNull('rework_rot_session_id')->update([
                    'rework_rot_session_id'=>$rotId,
                    'status'=>'ROT_LINKED',
                    'updated_at'=>now()
                ]);
            if ($affected!==1) throw new \RuntimeException('AM251_LINK_RACE_DETECTED');

            $headerAfter=DB::table('job_cards')->where('id',$jobId)->first();
            foreach ($financialKeys as $key) {
                if (($headerAfter->{$key}??null)!==$headerBefore[$key]) {
                    throw new \RuntimeException('AM251_FINANCIAL_HEADER_CHANGED_ROLLBACK');
                }
            }

            $event=[
                'complaint_id'=>$complaint,'job_card_id'=>$jobId,
                'labour_line_id'=>$lineId,'rot_session_id'=>$rotId,
                'receiving_staff_id'=>$staffId,
                'master_rot_id'=>(int)$master->id,
                'standard_hours'=>$std,'customer_charge'=>0,
                'billing_included'=>0,'original_rot_unchanged'=>(string)$case->rot_session_id,
            ];
            DB::table('am_complaint_rework_audits')->insert([
                'complaint_id'=>$complaint,'rework_link_id'=>$link->id,
                'actor_admin_id'=>(int)$admin->id,
                'action'=>'NO_CHARGE_REWORK_ROT_CREATED',
                'event_json'=>json_encode($event,JSON_THROW_ON_ERROR),
                'created_at'=>now('Asia/Kolkata'),
            ]);
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$jobId,
                'entity_type'=>'JOB_CARD_LABOUR',
                'entity_id'=>$lineId,
                'action'=>'COMPLAINT_REWORK_NO_CHARGE',
                'before_json'=>null,
                'after_json'=>json_encode((array)$afterLine,JSON_THROW_ON_ERROR),
                'reason'=>$reason,
                'actor_id'=>(int)$admin->id,
                'actor_name'=>(string)($admin->name??'Admin'),
                'created_at'=>now('Asia/Kolkata'),
                'updated_at'=>now('Asia/Kolkata'),
            ]);
        },3);

        return redirect()
            ->route('erp.rot-work-complaints.rework.show',$complaint)
            ->with('success',
                'No-charge corrective ROT created and linked to complaint. Native staff check-in/timer controls track the new mechanic; original ROT and customer totals unchanged.');
    }
}
