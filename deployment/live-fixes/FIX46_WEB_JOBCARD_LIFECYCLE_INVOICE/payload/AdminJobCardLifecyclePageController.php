<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\JobCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminJobCardLifecyclePageController extends Controller
{
    public function markWip(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','max:1000']]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $this->assertNotLocked($card);
            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['open','completed'],true)){
                throw ValidationException::withMessages(['status'=>'Only OPEN / rework-ready Job Card can move to Work In Progress.']);
            }
            if(!in_array(strtolower((string)$card->inspection_status),['completed','skipped'],true)){
                throw ValidationException::withMessages(['status'=>'Vehicle Inspection complete/skip hone ke baad hi Work In Progress start hoga.']);
            }
            if(strtolower((string)$card->diagnosis_status)!=='completed'
                || strtolower((string)($card->decision_status ?? 'pending'))!=='completed'){
                throw ValidationException::withMessages(['status'=>'Diagnosis + Technician Decision complete kijiye before WIP.']);
            }

            $before=$card->toArray();
            $card->status='in-progress';
            if(Schema::hasColumn('job_cards','work_started_at') && !$card->work_started_at) $card->work_started_at=now('Asia/Kolkata');
            if(Schema::hasColumn('job_cards','completed_at')) $card->completed_at=null;
            if(Schema::hasColumn('job_cards','closed_at')) $card->closed_at=null;
            $card->customer_public_status='Repair In Progress';
            $card->save();

            $this->audit($job,'JOB_CARD',$job,'WIP_START',$before,$card->fresh()->toArray(),$data['reason'],$user);
        });

        return redirect()->route('erp.job-cards.show',$job)->with('success','Job Card moved to WORK IN PROGRESS.');
    }

    public function markWorkComplete(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','max:1000']]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $this->assertNotLocked($card);
            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['in progress','in-progress','work in progress','wip'],true)){
                throw ValidationException::withMessages(['status'=>'Only Work In Progress Job Card can be marked Work Complete.']);
            }

            $guards=$this->unfinishedWork($job);
            if($guards){
                throw ValidationException::withMessages(['status'=>'Work Complete blocked: '.implode(' | ',$guards)]);
            }

            $before=$card->toArray();
            $card->status='completed';
            if(Schema::hasColumn('job_cards','completed_at')) $card->completed_at=now('Asia/Kolkata');
            $card->customer_public_status='Work Complete / Awaiting QC';
            $card->save();

            $this->audit($job,'JOB_CARD',$job,'WORK_COMPLETE',$before,$card->fresh()->toArray(),$data['reason'],$user);
        });

        return redirect()->route('erp.job-cards.show',$job)->with('success','WORK COMPLETE recorded. Job Card is now awaiting Quality Check.');
    }

    public function qcPending(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'assigned_staff_id'=>['nullable','integer','min:1'],
            'reason'=>['required','string','max:1000'],
            'notes'=>['nullable','string','max:3000'],
        ]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $this->assertNotLocked($card);
            if(strtolower((string)$card->status)!=='completed'){
                throw ValidationException::withMessages(['status'=>'Work Complete hone ke baad hi QC start hoga.']);
            }
            if(!str_contains(strtolower((string)$card->customer_public_status),'work complete')
                && !str_contains(strtolower((string)$card->customer_public_status),'quality check')){
                throw ValidationException::withMessages(['status'=>'QC start se pehle Work Complete stage required hai.']);
            }

            if(!empty($data['assigned_staff_id'])){
                $this->activeStaff((int)$data['assigned_staff_id']);
            }

            $before=$card->toArray();
            $card->customer_public_status='Quality Check';
            $card->save();

            DB::table('workshop_qc_checks')->insert([
                'job_card_id'=>$job,
                'assigned_staff_id'=>$data['assigned_staff_id'] ?? null,
                'result'=>'PENDING',
                'notes'=>trim((string)($data['notes'] ?? '')) ?: null,
                'failed_items_json'=>json_encode([],JSON_UNESCAPED_UNICODE),
                'checked_by_admin_user_id'=>$user->id,
                'checked_at'=>now('Asia/Kolkata'),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            $this->audit($job,'JOB_CARD',$job,'QC_PENDING',$before,$card->fresh()->toArray(),$data['reason'],$user);
        });

        return redirect()->route('erp.job-cards.show',$job)->with('success','QUALITY CHECK started.');
    }

    public function qcResult(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'result'=>['required','in:PASS,FAIL'],
            'assigned_staff_id'=>['nullable','integer','min:1'],
            'failed_item'=>['nullable','string','max:2000'],
            'notes'=>['nullable','string','max:3000'],
            'reason'=>['required','string','max:1000'],
        ]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $this->assertNotLocked($card);
            if(strtolower((string)$card->status)!=='completed'
                || !str_contains(strtolower((string)$card->customer_public_status),'quality check')){
                throw ValidationException::withMessages(['status'=>'QC result sirf QUALITY CHECK stage me save hoga.']);
            }
            if(!empty($data['assigned_staff_id'])) $this->activeStaff((int)$data['assigned_staff_id']);

            $before=$card->toArray();
            $failed=[];
            if($data['result']==='FAIL' && trim((string)($data['failed_item'] ?? ''))!==''){
                $failed[]=[
                    'item'=>trim($data['failed_item']),
                    'staff_id'=>$data['assigned_staff_id'] ?? null,
                ];
            }

            DB::table('workshop_qc_checks')->insert([
                'job_card_id'=>$job,
                'assigned_staff_id'=>$data['assigned_staff_id'] ?? null,
                'result'=>$data['result'],
                'notes'=>trim((string)($data['notes'] ?? '')) ?: null,
                'failed_items_json'=>json_encode($failed,JSON_UNESCAPED_UNICODE),
                'checked_by_admin_user_id'=>$user->id,
                'checked_at'=>now('Asia/Kolkata'),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            if($data['result']==='PASS'){
                $card->status='completed';
                $card->customer_public_status='Quality Check Passed';
                if(Schema::hasColumn('job_cards','completed_at') && !$card->completed_at) $card->completed_at=now('Asia/Kolkata');
            }else{
                $card->status='in-progress';
                $card->customer_public_status='Rework / QC Failed';
                if(Schema::hasColumn('job_cards','completed_at')) $card->completed_at=null;
            }
            $card->save();

            $this->audit(
                $job,'JOB_CARD',$job,'QC_'.$data['result'],
                $before,$card->fresh()->toArray(),$data['reason'],$user
            );
        });

        return redirect()->route('erp.job-cards.show',$job)->with(
            'success',
            $data['result']==='PASS' ? 'QC PASSED. Ready for Delivery can now be marked.' : 'QC FAILED. Job Card returned to WIP / Rework.'
        );
    }

    public function readyForDelivery(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','max:1000']]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $this->assertNotLocked($card);
            if(strtolower((string)$card->status)!=='completed'
                || !str_contains(strtolower((string)$card->customer_public_status),'quality check passed')){
                throw ValidationException::withMessages(['status'=>'QC PASS ke baad hi Ready for Delivery mark hoga.']);
            }
            $latest=DB::table('workshop_qc_checks')->where('job_card_id',$job)->orderByDesc('id')->first();
            if(!$latest || strtoupper((string)$latest->result)!=='PASS'){
                throw ValidationException::withMessages(['status'=>'Latest QC record PASS nahi hai.']);
            }

            $guards=$this->unfinishedWork($job);
            if($guards){
                throw ValidationException::withMessages(['status'=>'Ready for Delivery blocked: '.implode(' | ',$guards)]);
            }

            $before=$card->toArray();
            $card->customer_public_status='Ready for Delivery';
            $card->save();
            $this->audit($job,'JOB_CARD',$job,'READY_FOR_DELIVERY',$before,$card->fresh()->toArray(),$data['reason'],$user);
        });

        return redirect()->route('erp.job-cards.show',$job)->with('success','Vehicle marked READY FOR DELIVERY.');
    }

    public function closeJob(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','max:1000']]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $this->assertNotLocked($card);
            if(!str_contains(strtolower((string)$card->customer_public_status),'ready for delivery')){
                throw ValidationException::withMessages(['status'=>'Ready for Delivery stage ke baad hi Job Card Close hoga.']);
            }

            $before=$card->toArray();
            $card->status='closed';
            if(Schema::hasColumn('job_cards','closed_at')) $card->closed_at=now('Asia/Kolkata');
            $card->customer_public_status='Ready for Delivery';
            $card->save();
            $this->audit($job,'JOB_CARD',$job,'CLOSED',$before,$card->fresh()->toArray(),$data['reason'],$user);
        });

        return redirect()->route('erp.job-cards.show',$job)->with('success','Job Card CLOSED. Invoice conversion is now available.');
    }

    public function reopenClosed(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','max:1000']]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            if(strtolower((string)$card->status)!=='closed'){
                throw ValidationException::withMessages(['status'=>'Only CLOSED Job Card can be reopened here.']);
            }
            if(!empty($card->invoice_id) || DB::table('invoices')->where('job_card_id',$job)->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['status'=>'Invoice already exists. Invoiced Job Card cannot be reopened directly.']);
            }

            $before=$card->toArray();
            $card->status='in-progress';
            if(Schema::hasColumn('job_cards','closed_at')) $card->closed_at=null;
            if(Schema::hasColumn('job_cards','completed_at')) $card->completed_at=null;
            $card->customer_public_status='Rework / Reopened';
            $card->save();
            $this->audit($job,'JOB_CARD',$job,'REOPEN_CLOSED',$before,$card->fresh()->toArray(),$data['reason'],$user);
        });

        return redirect()->route('erp.job-cards.show',$job)->with('success','Closed Job Card reopened to WIP with audit reason.');
    }

    public function createInvoice(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'invoice_date'=>['required','date'],
            'reason'=>['required','string','max:1000'],
        ]);
        if((string)$data['invoice_date'] > now('Asia/Kolkata')->toDateString()){
            throw ValidationException::withMessages(['invoice_date'=>'Invoice date future date nahi ho sakta.']);
        }

        $invoiceId=DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            if(strtolower((string)$card->status)!=='closed'){
                throw ValidationException::withMessages(['status'=>'Invoice conversion ke liye Job Card CLOSED hona chahiye.']);
            }
            if(empty($card->customer_id)){
                throw ValidationException::withMessages(['status'=>'Invoice se pehle Job Card ko Customer Master se link kijiye.']);
            }
            if(!empty($card->invoice_id)){
                return (int)$card->invoice_id;
            }
            $existing=DB::table('invoices')->where('job_card_id',$job)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")
                ->orderByDesc('id')->first();
            if($existing){
                $card->invoice_id=(int)$existing->id;
                $card->status='invoiced';
                $card->customer_public_status='Invoiced / Billing';
                $card->save();
                return (int)$existing->id;
            }

            $partsTotal=round((float)($card->parts_total ?? 0),2);
            $labourTotal=round((float)($card->labour_total ?? 0),2);
            if($partsTotal<=0 && Schema::hasTable('job_card_parts')){
                $partsTotal=round((float)DB::table('job_card_parts')->where('job_card_id',$job)->sum('total'),2);
            }
            if($labourTotal<=0 && Schema::hasTable('job_card_labour')){
                $labourTotal=round((float)DB::table('job_card_labour')->where('job_card_id',$job)->sum('total'),2);
            }
            $subtotal=round($partsTotal+$labourTotal,2);
            $gstTotal=round((float)($card->gst_total ?? 0),2);
            // Prevent issuing a potentially incorrect GST invoice. The Job Card tax
            // summary must be reconciled against its taxable line items first.
            $hasTaxableLines=false;
            foreach (['job_card_parts','job_card_labour'] as $lineTable) {
                if (Schema::hasTable($lineTable) && Schema::hasColumn($lineTable,'gst_percent')) {
                    $hasTaxableLines = $hasTaxableLines || DB::table($lineTable)
                        ->where('job_card_id',$job)->where('gst_percent','>',0)->exists();
                }
            }
            if ($hasTaxableLines && $gstTotal <= 0) {
                throw ValidationException::withMessages([
                    'status'=>'Invoice blocked: taxable Parts/Labour lines have GST rates, but Job Card GST total is zero. Recalculate and reconcile GST before issuing an invoice.'
                ]);
            }
            $preRound=round((float)($card->pre_round_total ?? 0),2);
            if($preRound<=0) $preRound=round($subtotal+$gstTotal,2);
            $roundOff=round((float)($card->round_off ?? 0),2);
            $grand=round((float)($card->grand_total ?? 0),2);
            if($grand<=0) $grand=round($preRound+$roundOff,2);
            if(abs($grand-($preRound+$roundOff))>0.01) $roundOff=round($grand-$preRound,2);

            // Customer master is authoritative if the Job Card snapshot is empty.
            $customerMaster=Schema::hasTable('customers')
                ? DB::table('customers')->where('id',(int)$card->customer_id)->first()
                : null;
            $masterName=$customerMaster->name ?? $customerMaster->customer_name ?? null;
            $masterPhone=$customerMaster->phone ?? $customerMaster->mobile ?? null;
            $masterEmail=$customerMaster->email ?? null;

            $tmp='TMP-INV-'.strtoupper(bin2hex(random_bytes(6)));
            $row=[
                'invoice_no'=>$tmp,
                'job_card_id'=>$job,
                'customer_id'=>(int)$card->customer_id,
                'vehicle_id'=>$card->vehicle_id ?: null,
                'invoice_date'=>$data['invoice_date'].' '.now('Asia/Kolkata')->format('H:i:s'),
                'parts_total'=>$partsTotal,
                'labour_total'=>$labourTotal,
                'tax_total'=>$gstTotal,
                'grand_total'=>$grand,
                'paid_total'=>0,
                'balance_total'=>$grand,
                'status'=>'issued',
                'created_at'=>now('Asia/Kolkata'),
                'customer_email'=>$card->customer_email ?: $masterEmail,
                'customer_name'=>$card->customer_name ?: $masterName,
                'customer_phone'=>$card->customer_phone ?: $masterPhone,
                'vehicle_reg_no'=>$card->vehicle_reg_no ?: null,
                'subtotal'=>$subtotal,
                'gst_total'=>$gstTotal,
                'pre_round_total'=>$preRound,
                'round_off'=>$roundOff,
                'paid_amount'=>0,
                'balance_due'=>$grand,
                'original_job_card_id'=>$job,
            ];
            $row=$this->existingColumns('invoices',$row);
            $invoiceId=(int)DB::table('invoices')->insertGetId($row);
            $invoiceNo='INV-'.date('Y',strtotime($data['invoice_date'])).'-'.str_pad((string)$invoiceId,5,'0',STR_PAD_LEFT);
            DB::table('invoices')->where('id',$invoiceId)->update(['invoice_no'=>$invoiceNo]);

            $ledger=[
                'customer_id'=>(int)$card->customer_id,
                'invoice_id'=>$invoiceId,
                'entry_type'=>'DEBIT',
                'amount'=>$grand,
                'reference_no'=>$invoiceNo,
                'note'=>'Posted from Job Card '.$card->job_no,
                'created_at'=>now('Asia/Kolkata'),
                'customer_email'=>$card->customer_email ?: null,
                'customer_name'=>$card->customer_name ?: null,
                'payment_id'=>null,
                'job_card_id'=>$job,
                'vehicle_id'=>$card->vehicle_id ?: null,
                'reference_type'=>'INVOICE',
                'reference_id'=>$invoiceId,
                'debit'=>$grand,
                'credit'=>0,
                'narration'=>'Invoice '.$invoiceNo,
                'entry_date'=>$data['invoice_date'],
            ];
            DB::table('customer_ledger')->insert($this->existingColumns('customer_ledger',$ledger));

            $before=$card->toArray();
            $card->invoice_id=$invoiceId;
            $card->status='invoiced';
            $card->customer_public_status='Invoiced / Billing';
            $card->save();

            $this->audit(
                $job,'JOB_CARD',$job,'INVOICE_CREATE',
                $before,$card->fresh()->toArray(),
                $data['reason'].' · '.$invoiceNo,$user
            );
            return $invoiceId;
        });

        return redirect()->route('erp.invoices.show',$invoiceId)->with('success','Invoice created and Customer Ledger DEBIT posted.');
    }

    public function showInvoice(Request $request, int $invoice): View
    {
        $this->admin($request);
        return view('invoices.show',$this->invoiceData($invoice));
    }

    public function printInvoice(Request $request, int $invoice): View
    {
        $this->admin($request);
        return view('invoices.print',array_merge($this->invoiceData($invoice),['costSummary'=>$request->query('format')==='summary']));
    }

    private function invoiceData(int $invoice): array
    {
        $inv=DB::table('invoices')->where('id',$invoice)->first();
        abort_unless($inv,404);
        $job=DB::table('job_cards')->where('id',$inv->job_card_id)->first();
        abort_unless($job,404);
        $parts=Schema::hasTable('job_card_parts')
            ? DB::table('job_card_parts')->where('job_card_id',$job->id)->orderBy('id')->get()
            : collect();
        $labour=Schema::hasTable('job_card_labour')
            ? DB::table('job_card_labour')->where('job_card_id',$job->id)->orderBy('id')->get()
            : collect();
        $customer=Schema::hasTable('customers') && !empty($inv->customer_id)
            ? DB::table('customers')->where('id',(int)$inv->customer_id)->first()
            : null;
        $ledger=Schema::hasTable('customer_ledger')
            ? DB::table('customer_ledger')->where('invoice_id',$invoice)->orderBy('id')->get()
            : collect();
        return compact('inv','job','customer','parts','labour','ledger');
    }

    private function unfinishedWork(int $job): array
    {
        $out=[];
        if(Schema::hasTable('rot_sessions')){
            $n=DB::table('rot_sessions')->where('job_card_id',$job)
                ->whereRaw("LOWER(COALESCE(status,'')) IN ('assigned','running','paused')")->count();
            if($n>0) $out[]=$n.' ROT pending/running/paused';
        }
        if(Schema::hasTable('job_card_labour') && Schema::hasColumn('job_card_labour','execution_status')){
            $n=DB::table('job_card_labour')->where('job_card_id',$job)
                ->whereRaw("UPPER(COALESCE(mechanic_name,'')) <> 'OSL'")
                ->whereRaw("LOWER(COALESCE(execution_status,'')) IN ('','planned','pending','assigned','running','paused')")
                ->count();
            if($n>0) $out[]=$n.' Labour/ROT line unfinished';
        }
        if(Schema::hasTable('job_card_parts') && Schema::hasColumn('job_card_parts','issue_status')){
            $n=DB::table('job_card_parts')->where('job_card_id',$job)
                ->whereRaw("LOWER(TRIM(COALESCE(issue_status,''))) IN ('','planned','pending','not issued','not_issued','issued')")
                ->count();
            if($n>0) $out[]=$n.' Part line not fitted/returned';
        }
        if(Schema::hasTable('workshop_osl_requests')){
            $n=DB::table('workshop_osl_requests')->where('job_card_id',$job)
                ->whereRaw("UPPER(COALESCE(status,'')) IN ('REQUESTED','APPROVED','IN_PROGRESS')")->count();
            if($n>0) $out[]=$n.' OSL request unfinished';
        }
        return $out;
    }

    private function assertNotLocked(JobCard $card): void
    {
        if(in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true) || !empty($card->invoice_id)){
            throw ValidationException::withMessages(['status'=>'Closed / Invoiced / Cancelled Job Card is locked.']);
        }
    }

    private function activeStaff(int $id): object
    {
        $row=DB::table('staff')->where('id',$id)->whereRaw("LOWER(COALESCE(status,'active'))='active'")->first();
        if(!$row) throw ValidationException::withMessages(['assigned_staff_id'=>'Selected active Staff not found.']);
        return $row;
    }

    private function audit(
        int $jobId,string $entityType,int $entityId,string $action,
        ?array $before,?array $after,string $reason,ErpAuthUser $user
    ): void {
        if(!Schema::hasTable('job_card_change_audits')) return;
        $row=[
            'job_card_id'=>$jobId,
            'entity_type'=>$entityType,
            'entity_id'=>$entityId,
            'action'=>$action,
            'before_json'=>$before ? json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            'after_json'=>$after ? json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            'reason'=>trim($reason),
            'actor'=>$user->email ?: $user->name ?: 'ADMIN',
            'created_at'=>now(),
            'updated_at'=>now(),
        ];
        DB::table('job_card_change_audits')->insert($this->existingColumns('job_card_change_audits',$row));
    }

    private function existingColumns(string $table,array $data): array
    {
        return array_filter($data,fn($v,$k)=>Schema::hasColumn($table,$k),ARRAY_FILTER_USE_BOTH);
    }

    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin/Supervisor permission required.');
        return $user;
    }
}
