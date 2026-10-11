<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class OwnerWorkshopController extends Controller
{
    public function floor(Request $request): JsonResponse
    {
        $this->admin($request);
        $today = CarbonImmutable::now('Asia/Kolkata')->toDateString();

        $staff = Schema::hasTable('staff')
            ? DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")->orderBy('name')->get()
            : collect();

        $attendance = Schema::hasTable('attendance')
            ? DB::table('attendance')->whereDate('date', $today)->orderByDesc('id')->get()->unique('staff_id')->keyBy('staff_id')
            : collect();

        $rots = Schema::hasTable('rot_sessions')
            ? DB::table('rot_sessions')->whereIn('status', ['Running','Paused','Assigned'])
                ->orderByRaw("FIELD(status,'Running','Paused','Assigned')")
                ->orderBy('created_at')->get()->groupBy('staff_id')
            : collect();

        $rows = $staff->map(function ($s) use ($attendance, $rots) {
            $a = $attendance->get($s->id);
            $list = $rots->get($s->id, collect());
            $current = $list->firstWhere('status', 'Running')
                ?: $list->firstWhere('status', 'Paused')
                ?: $list->firstWhere('status', 'Assigned');
            $next = $list->first(function ($r) use ($current) {
                return $r->status === 'Assigned' && (!$current || $r->id !== $current->id);
            });

            return [
                'staff_id' => (int)$s->id,
                'staff_code' => $s->staff_code ?? null,
                'name' => $s->name ?? 'Staff',
                'department' => $s->department ?? null,
                'role' => $s->role ?? null,
                'checked_in' => (bool)($a && $a->check_in && !$a->check_out),
                'check_in' => $a->check_in ?? null,
                'check_out' => $a->check_out ?? null,
                'current_rot' => $current ? (array)$current : null,
                'next_rot' => $next ? (array)$next : null,
                'queue_count' => $list->count(),
            ];
        })->values();

        return response()->json(['ok' => true, 'date' => $today, 'floor' => $rows]);
    }

    public function jobControl(Request $request, int $job): JsonResponse
    {
        $this->admin($request);
        $card = $this->job($job);

        $parts = Schema::hasTable('job_card_parts')
            ? DB::table('job_card_parts')->where('job_card_id', $job)->orderBy('id')->get()
            : collect();

        $labour = Schema::hasTable('job_card_labour')
            ? DB::table('job_card_labour')->where('job_card_id', $job)->orderBy('id')->get()
            : collect();

        $rots = Schema::hasTable('rot_sessions')
            ? DB::table('rot_sessions')->where('job_card_id', $job)->orderBy('created_at')->get()
            : collect();

        $inspection = Schema::hasTable('job_card_inspection')
            ? DB::table('job_card_inspection')->where('job_card_id', $job)->orderBy('id')->get()
            : collect();

        $osl = Schema::hasTable('workshop_purchases')
            ? DB::table('workshop_purchases')->where('job_card_id', $job)
                ->whereRaw("UPPER(COALESCE(purchase_type,''))='OSL'")
                ->whereNull('deleted_at')->orderByDesc('id')->get()
            : collect();

        $partRequests = Schema::hasTable('workshop_parts_requests')
            ? DB::table('workshop_parts_requests')->where('job_card_id', $job)->orderByDesc('id')->get()
            : collect();

        $qc = Schema::hasTable('workshop_qc_checks')
            ? DB::table('workshop_qc_checks')->where('job_card_id', $job)->orderByDesc('id')->get()
            : collect();

        $road = Schema::hasTable('workshop_road_tests')
            ? DB::table('workshop_road_tests')->where('job_card_id', $job)->orderByDesc('id')->get()
            : collect();

        $keys = Schema::hasTable('workshop_key_custody')
            ? DB::table('workshop_key_custody')->where('job_card_id', $job)->orderByDesc('id')->limit(30)->get()
            : collect();

        $reworks = Schema::hasTable('workshop_reworks')
            ? DB::table('workshop_reworks')->where('job_card_id', $job)->orWhere('original_job_card_id', $job)->orderByDesc('id')->get()
            : collect();

        $staff = Schema::hasTable('staff')
            ? DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")
                ->select('id','staff_code','name','role','department')->orderBy('name')->get()
            : collect();

        return response()->json([
            'ok' => true,
            'job' => $card,
            'lifecycle' => $this->lifecycle($card),
            'parts' => $parts,
            'labour' => $labour,
            'rot_sessions' => $rots,
            'inspection' => $inspection,
            'osl' => $osl,
            'parts_requests' => $partRequests,
            'qc_checks' => $qc,
            'road_tests' => $road,
            'key_custody' => $keys,
            'reworks' => $reworks,
            'staff' => $staff,
        ]);
    }

    public function createJob(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'customer_id' => ['nullable','integer','min:1'],
            'customer_name' => ['required','string','max:150'],
            'customer_phone' => ['nullable','string','max:30'],
            'customer_email' => ['nullable','email','max:150'],
            'customer_address' => ['nullable','string','max:4000'],
            'vehicle_id' => ['nullable','integer','min:1'],
            'vehicle_reg_no' => ['nullable','string','max:40'],
            'vehicle_make' => ['nullable','string','max:80'],
            'vehicle_model' => ['nullable','string','max:80'],
            'vehicle_year' => ['nullable','string','max:10'],
            'vehicle_variant' => ['nullable','string','max:80'],
            'fuel_type' => ['nullable','string','max:30'],
            'odometer_km' => ['nullable','string','max:20'],
            'vehicle_chassis_no' => ['nullable','string','max:100'],
            'vehicle_engine_no' => ['nullable','string','max:100'],
            'job_date' => ['required','date'],
            'vehicle_inward_at' => ['nullable','date'],
            'expected_date' => ['nullable','date'],
            'service_type' => ['nullable','string','max:80'],
            'technician_id' => ['nullable','integer','min:1'],
            'customer_complaint' => ['nullable','string','max:6000'],
            'customer_voice' => ['nullable','string','max:6000'],
            'priority' => ['nullable','in:LOW,NORMAL,HIGH,URGENT'],
        ]);

        if ((string)$data['job_date'] > now('Asia/Kolkata')->toDateString()) {
            throw ValidationException::withMessages(['job_date' => 'Job Card date future date nahi ho sakta.']);
        }

        $created = DB::transaction(function () use ($data, $user) {
            $customer = null;
            $vehicle = null;
            $customerId = (int)($data['customer_id'] ?? 0);
            $vehicleId = (int)($data['vehicle_id'] ?? 0);

            if ($customerId > 0 && Schema::hasTable('am_customer_master')) {
                $customer = DB::table('am_customer_master')->where('id', $customerId)->first();
                if (!$customer) throw ValidationException::withMessages(['customer_id' => 'Selected Customer not found.']);
            }
            if ($vehicleId > 0 && Schema::hasTable('am_vehicle_master')) {
                $vehicle = DB::table('am_vehicle_master')->where('id', $vehicleId)->first();
                if (!$vehicle) throw ValidationException::withMessages(['vehicle_id' => 'Selected Vehicle not found.']);
                if ($customerId > 0 && (int)$vehicle->customer_id !== $customerId) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Selected Vehicle does not belong to selected Customer.']);
                }
                if ($customerId <= 0) {
                    $customerId = (int)$vehicle->customer_id;
                    $customer = Schema::hasTable('am_customer_master')
                        ? DB::table('am_customer_master')->where('id', $customerId)->first()
                        : null;
                }
            }

            $technician = $this->activeStaff((int)($data['technician_id'] ?? 0), true);

            $row = [
                'job_no' => 'TMP-'.strtoupper(bin2hex(random_bytes(8))),
                'job_date' => $data['job_date'],
                'customer_id' => $customerId ?: null,
                'customer_name' => trim((string)($customer->name ?? $data['customer_name'])),
                'customer_phone' => trim((string)($customer->phone ?? ($data['customer_phone'] ?? ''))) ?: null,
                'customer_email' => trim((string)($customer->email ?? ($data['customer_email'] ?? ''))) ?: null,
                'customer_address' => trim((string)($customer->address ?? ($data['customer_address'] ?? ''))) ?: null,
                'vehicle_id' => $vehicleId ?: null,
                'vehicle_reg_no' => strtoupper(trim((string)($vehicle->registration_no ?? ($data['vehicle_reg_no'] ?? '')))) ?: null,
                'vehicle_make' => trim((string)($vehicle->make_name ?? ($data['vehicle_make'] ?? ''))) ?: null,
                'vehicle_model' => trim((string)($vehicle->model_name ?? ($data['vehicle_model'] ?? ''))) ?: null,
                'vehicle_year' => trim((string)($vehicle->model_year ?? ($data['vehicle_year'] ?? ''))) ?: null,
                'vehicle_variant' => trim((string)($vehicle->variant_name ?? ($data['vehicle_variant'] ?? ''))) ?: null,
                'fuel_type' => trim((string)($vehicle->fuel_type ?? ($data['fuel_type'] ?? ''))) ?: null,
                'odometer_km' => trim((string)($vehicle->odometer_km ?? ($data['odometer_km'] ?? ''))) ?: null,
                'vehicle_chassis_no' => strtoupper(trim((string)($vehicle->chassis_no ?? ($data['vehicle_chassis_no'] ?? '')))) ?: null,
                'vehicle_engine_no' => strtoupper(trim((string)($vehicle->engine_no ?? ($data['vehicle_engine_no'] ?? '')))) ?: null,
                'vehicle_inward_at' => $data['vehicle_inward_at'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'service_type' => trim((string)($data['service_type'] ?? '')) ?: null,
                'customer_complaint' => trim((string)($data['customer_complaint'] ?? '')) ?: null,
                'customer_voice' => trim((string)($data['customer_voice'] ?? '')) ?: null,
                'technician_id' => $technician?->id,
                'technician' => $technician?->name,
                'status' => 'open',
                'inspection_status' => 'pending',
                'diagnosis_status' => 'pending',
                'decision_status' => 'pending',
                'estimate_allowed' => 0,
                'customer_public_status' => 'Vehicle Received',
                'parts_total' => 0,
                'labour_total' => 0,
                'gst_total' => 0,
                'pre_round_total' => 0,
                'round_off' => 0,
                'grand_total' => 0,
                'priority' => $data['priority'] ?? 'NORMAL',
            ];
            $row = $this->existingColumns('job_cards', $row);
            $id = (int)DB::table('job_cards')->insertGetId($row);
            $jobNo = 'JC-'.now('Asia/Kolkata')->format('Ymd').'-'.str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            DB::table('job_cards')->where('id', $id)->update(['job_no' => $jobNo]);

            $after = (array)DB::table('job_cards')->where('id', $id)->first();
            $this->audit($user, 'JOB_CARD_CREATE', 'JOB_CARD', (string)$id, $id, null, 'Initial Job Card creation', null, $after);
            return $after;
        });

        return response()->json(['ok' => true, 'job' => $created], 201);
    }

    public function editJob(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'customer_name' => ['required','string','max:180'],
            'customer_phone' => ['nullable','string','max:40'],
            'vehicle_reg_no' => ['required','string','max:60'],
            'vehicle_make' => ['nullable','string','max:100'],
            'vehicle_model' => ['nullable','string','max:100'],
            'vehicle_variant' => ['nullable','string','max:100'],
            'customer_complaint' => ['nullable','string','max:4000'],
            'service_type' => ['nullable','string','max:80'],
            'expected_date' => ['nullable','date'],
            'odometer_km' => ['nullable','string','max:20'],
            'reason' => ['required','string','max:1000'],
        ]);

        DB::transaction(function () use ($job, $data, $user) {
            $card = $this->job($job, true);
            if (in_array(strtolower((string)$card->status), ['closed','invoiced','cancelled'], true)) {
                throw ValidationException::withMessages(['job_card' => 'Closed / Invoiced / Cancelled Job Card cannot be directly edited. Reopen first.']);
            }
            $before = (array)$card;
            $update = [
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => trim((string)($data['customer_phone'] ?? '')) ?: null,
                'vehicle_reg_no' => strtoupper(trim($data['vehicle_reg_no'])),
                'vehicle_make' => trim((string)($data['vehicle_make'] ?? '')) ?: null,
                'vehicle_model' => trim((string)($data['vehicle_model'] ?? '')) ?: null,
                'vehicle_variant' => trim((string)($data['vehicle_variant'] ?? '')) ?: null,
                'customer_complaint' => trim((string)($data['customer_complaint'] ?? '')) ?: null,
                'service_type' => trim((string)($data['service_type'] ?? '')) ?: null,
                'expected_date' => $data['expected_date'] ?? null,
                'odometer_km' => trim((string)($data['odometer_km'] ?? '')) ?: null,
                'updated_at' => now(),
            ];
            DB::table('job_cards')->where('id', $job)->update($this->existingColumns('job_cards', $update));
            $after = (array)DB::table('job_cards')->where('id', $job)->first();
            $this->audit($user, 'JOB_CARD_EDIT', 'JOB_CARD', (string)$job, $job, null, $data['reason'], $before, $after);
        });

        return response()->json(['ok' => true, 'job' => $this->job($job)]);
    }

    public function assignTechnician(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'staff_id' => ['required','integer','min:1'],
            'reason' => ['required','string','max:500'],
        ]);
        $staff = $this->activeStaff((int)$data['staff_id']);
        $before = (array)$this->job($job);

        DB::table('job_cards')->where('id', $job)->update([
            'technician_id' => $staff->id,
            'technician' => $staff->name,
        ]);

        $after = (array)$this->job($job);
        $this->audit($user, 'JOB_CARD_TECHNICIAN_ASSIGN', 'JOB_CARD', (string)$job, $job, (int)$staff->id, $data['reason'], $before, $after);
        return response()->json(['ok' => true, 'job' => $after]);
    }

    public function priority(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'priority' => ['required','in:LOW,NORMAL,HIGH,URGENT'],
            'reason' => ['required','string','max:500'],
        ]);
        $before = (array)$this->job($job);
        DB::table('job_cards')->where('id', $job)->update(['priority' => $data['priority']]);
        $after = (array)$this->job($job);
        $this->audit($user, 'JOB_CARD_PRIORITY', 'JOB_CARD', (string)$job, $job, null, $data['reason'], $before, $after);
        return response()->json(['ok' => true, 'job' => $after]);
    }

    public function transition(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'action' => ['required','in:OPEN,WIP,WORK_COMPLETE,QC_PENDING,QC_PASSED,READY_FOR_DELIVERY,CLOSED,CANCELLED,REOPEN'],
            'reason' => ['required','string','max:1000'],
            'override' => ['nullable','boolean'],
        ]);
        $override = (bool)($data['override'] ?? false);

        $after = DB::transaction(function () use ($user, $job, $data, $override) {
            $card = $this->job($job, true);
            $before = (array)$card;
            if (strtolower((string)$card->status) === 'invoiced' || !empty($card->invoice_id)) {
                throw ValidationException::withMessages(['job_card' => 'Invoiced Job Card lifecycle mobile se change nahi hoga.']);
            }

            $activeRot = Schema::hasTable('rot_sessions')
                ? DB::table('rot_sessions')->where('job_card_id', $job)->whereIn('status', ['Assigned','Running','Paused'])->count()
                : 0;
            $latestQc = Schema::hasTable('workshop_qc_checks')
                ? DB::table('workshop_qc_checks')->where('job_card_id', $job)->orderByDesc('id')->first()
                : null;
            $qcPassed = $latestQc && strtoupper((string)$latestQc->result) === 'PASS';

            if (!$override && in_array($data['action'], ['WORK_COMPLETE','QC_PENDING','READY_FOR_DELIVERY'], true) && $activeRot > 0) {
                throw ValidationException::withMessages(['job_card' => 'Pending/Running/Paused ROT complete kijiye.']);
            }
            if (!$override && in_array($data['action'], ['QC_PASSED','READY_FOR_DELIVERY'], true) && !$qcPassed) {
                throw ValidationException::withMessages(['job_card' => 'QC PASS record required.']);
            }
            if (!$override && $data['action'] === 'CLOSED' && stripos((string)$card->customer_public_status, 'Ready for Delivery') === false) {
                throw ValidationException::withMessages(['job_card' => 'Job Card Close se pehle Ready for Delivery required hai.']);
            }

            $now = now('Asia/Kolkata');
            $update = [];
            switch ($data['action']) {
                case 'OPEN':
                    $update = ['status'=>'open','customer_public_status'=>'Vehicle Received'];
                    break;
                case 'WIP':
                case 'REOPEN':
                    $update = ['status'=>'in-progress','customer_public_status'=>'Repair In Progress','closed_at'=>null,'cancelled_at'=>null,'work_started_at'=>$card->work_started_at ?: $now];
                    break;
                case 'WORK_COMPLETE':
                    $update = ['status'=>'completed','customer_public_status'=>'Work Complete / Awaiting QC','completed_at'=>$now];
                    break;
                case 'QC_PENDING':
                    $update = ['status'=>'completed','customer_public_status'=>'Quality Check'];
                    break;
                case 'QC_PASSED':
                    $update = ['status'=>'completed','customer_public_status'=>'Quality Check Passed'];
                    break;
                case 'READY_FOR_DELIVERY':
                    $update = ['status'=>'completed','customer_public_status'=>'Ready for Delivery'];
                    break;
                case 'CLOSED':
                    $update = ['status'=>'closed','customer_public_status'=>'Ready for Delivery','closed_at'=>$now];
                    break;
                case 'CANCELLED':
                    $update = ['status'=>'cancelled','customer_public_status'=>'Cancelled','cancelled_at'=>$now];
                    break;
            }
            DB::table('job_cards')->where('id', $job)->update($this->existingColumns('job_cards', $update));
            $after = (array)$this->job($job);
            $action = $override ? 'EMERGENCY_OVERRIDE_'.$data['action'] : 'JOB_CARD_'.$data['action'];
            $this->audit($user, $action, 'JOB_CARD', (string)$job, $job, null, $data['reason'], $before, $after, ['override'=>$override]);
            return $after;
        });

        return response()->json(['ok' => true, 'job' => $after, 'lifecycle' => $this->lifecycle((object)$after)]);
    }

    public function assignInspection(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'staff_id' => ['required','integer','min:1'],
            'reason' => ['required','string','max:500'],
        ]);
        $staff = $this->activeStaff((int)$data['staff_id']);
        $before = (array)$this->job($job);
        DB::table('job_cards')->where('id', $job)->update($this->existingColumns('job_cards', [
            'technician_id' => $staff->id,
            'technician' => $staff->name,
            'inspection_status' => 'pending',
            'customer_public_status' => 'Vehicle Inspection Pending',
        ]));
        $after = (array)$this->job($job);
        $this->audit($user, 'INSPECTION_TECHNICIAN_ASSIGN', 'JOB_CARD', (string)$job, $job, (int)$staff->id, $data['reason'], $before, $after);
        return response()->json(['ok'=>true,'job'=>$after]);
    }

    public function diagnosis(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'action' => ['required','in:COMPLETE,REOPEN'],
            'final_diagnosis' => ['nullable','string','max:6000'],
            'decision' => ['nullable','string','max:50'],
            'note' => ['nullable','string','max:4000'],
            'reason' => ['required','string','max:1000'],
        ]);

        $before = (array)$this->job($job);
        if ($data['action'] === 'COMPLETE' && trim((string)($data['final_diagnosis'] ?? '')) === '') {
            throw ValidationException::withMessages(['final_diagnosis'=>'Final diagnosis required.']);
        }

        $update = $data['action'] === 'COMPLETE'
            ? [
                'final_diagnosis'=>trim((string)$data['final_diagnosis']),
                'diagnosis_status'=>'completed',
                'diagnosis_completed_at'=>now('Asia/Kolkata'),
                'technician_decision'=>trim((string)($data['decision'] ?? '')) ?: null,
                'technician_decision_note'=>trim((string)($data['note'] ?? '')) ?: null,
                'decision_status'=>'completed',
                'decision_completed_at'=>now('Asia/Kolkata'),
                'estimate_allowed'=>1,
                'customer_public_status'=>'Estimate Preparation',
            ]
            : [
                'diagnosis_status'=>'pending',
                'diagnosis_completed_at'=>null,
                'decision_status'=>'pending',
                'decision_completed_at'=>null,
                'estimate_allowed'=>0,
                'customer_public_status'=>'Diagnosis / Technician Decision Pending',
            ];

        DB::transaction(function () use ($job, $user, $data, $before, $update) {
            DB::table('job_cards')->where('id', $job)->update($this->existingColumns('job_cards', $update));
            if (Schema::hasTable('job_card_diagnosis_events')) {
                $event = [
                    'job_card_id'=>$job,
                    'event_type'=>$data['action']==='COMPLETE'?'ADMIN_DIAGNOSIS_COMPLETE':'ADMIN_DIAGNOSIS_REOPEN',
                    'diagnosis_status'=>$update['diagnosis_status'],
                    'final_diagnosis'=>$update['final_diagnosis'] ?? ($before['final_diagnosis'] ?? null),
                    'technician_decision_note'=>$data['note'] ?? null,
                    'reason'=>$data['reason'],
                    'created_at'=>now(),
                ];
                DB::table('job_card_diagnosis_events')->insert($this->existingColumns('job_card_diagnosis_events', $event));
            }
            $after = (array)$this->job($job);
            $this->audit($user, 'DIAGNOSIS_'.$data['action'], 'JOB_CARD', (string)$job, $job, null, $data['reason'], $before, $after);
        });

        return response()->json(['ok'=>true,'job'=>$this->job($job)]);
    }

    public function requestPart(Request $request, int $job): JsonResponse
    {
        $user = $this->admin($request);
        $this->job($job);
        $data = $request->validate([
            'part_master_id' => ['nullable','integer','min:1'],
            'part_code' => ['nullable','string','max:60'],
            'part_name' => ['required','string','max:255'],
            'qty' => ['required','numeric','gt:0'],
            'note' => ['nullable','string','max:1000'],
        ]);
        $id = DB::table('workshop_parts_requests')->insertGetId([
            'job_card_id'=>$job,
            'part_master_id'=>$data['part_master_id'] ?? null,
            'part_code'=>trim((string)($data['part_code'] ?? '')) ?: null,
            'part_name'=>trim($data['part_name']),
            'qty'=>$data['qty'],
            'status'=>'PENDING',
            'requested_by_user_id'=>$user->id,
            'note'=>trim((string)($data['note'] ?? '')) ?: null,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        $after=(array)DB::table('workshop_parts_requests')->where('id',$id)->first();
        $this->audit($user,'PARTS_REQUEST','PARTS_REQUEST',(string)$id,$job,null,$data['note'] ?? 'Parts requested',null,$after);
        return response()->json(['ok'=>true,'request'=>$after],201);
    }

    public function partStatus(Request $request, int $job, int $part): JsonResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'status'=>['required','in:PENDING,PLANNED,ISSUED,FITTED,RETURNED'],
            'reason'=>['required','string','max:1000'],
        ]);
        $row=DB::table('job_card_parts')->where('job_card_id',$job)->where('id',$part)->first();
        abort_unless($row,404,'Job Card Part not found.');
        $before=(array)$row;
        DB::table('job_card_parts')->where('id',$part)->update(['issue_status'=>$data['status']]);
        $after=(array)DB::table('job_card_parts')->where('id',$part)->first();
        $this->audit($user,'PART_ISSUE_STATUS','JOB_CARD_PART',(string)$part,$job,null,$data['reason'],$before,$after);
        return response()->json(['ok'=>true,'part'=>$after]);
    }

    public function partsRequestStatus(Request $request, int $requestId): JsonResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'status'=>['required','in:PENDING,APPROVED,ISSUED,RETURNED,REJECTED'],
            'reason'=>['required','string','max:1000'],
        ]);
        $row=DB::table('workshop_parts_requests')->where('id',$requestId)->first();
        abort_unless($row,404,'Parts request not found.');
        $before=(array)$row;
        DB::table('workshop_parts_requests')->where('id',$requestId)->update([
            'status'=>$data['status'],
            'processed_by_user_id'=>$user->id,
            'processed_at'=>now(),
            'updated_at'=>now(),
        ]);
        $after=(array)DB::table('workshop_parts_requests')->where('id',$requestId)->first();
        $this->audit($user,'PARTS_REQUEST_STATUS','PARTS_REQUEST',(string)$requestId,(int)$row->job_card_id,null,$data['reason'],$before,$after);
        return response()->json(['ok'=>true,'request'=>$after]);
    }

    public function osl(Request $request, ?int $job = null): JsonResponse
    {
        $this->admin($request);
        if (!Schema::hasTable('workshop_purchases')) return response()->json(['ok'=>true,'osl'=>[]]);
        $q=DB::table('workshop_purchases')->whereRaw("UPPER(COALESCE(purchase_type,''))='OSL'")->whereNull('deleted_at')->orderByDesc('id');
        if ($job) $q->where('job_card_id',$job);
        return response()->json(['ok'=>true,'osl'=>$q->limit(250)->get()]);
    }

    public function qc(Request $request, int $job): JsonResponse
    {
        $user=$this->admin($request);
        $this->job($job);
        $data=$request->validate([
            'result'=>['required','in:PENDING,PASS,FAIL'],
            'assigned_staff_id'=>['nullable','integer','min:1'],
            'notes'=>['nullable','string','max:4000'],
            'failed_items'=>['nullable','array','max:100'],
            'reason'=>['required','string','max:1000'],
        ]);

        $id=DB::table('workshop_qc_checks')->insertGetId([
            'job_card_id'=>$job,
            'assigned_staff_id'=>$data['assigned_staff_id'] ?? null,
            'result'=>$data['result'],
            'notes'=>trim((string)($data['notes'] ?? '')) ?: null,
            'failed_items_json'=>json_encode($data['failed_items'] ?? [],JSON_UNESCAPED_UNICODE),
            'checked_by_admin_user_id'=>$user->id,
            'checked_at'=>now('Asia/Kolkata'),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        if ($data['result']==='PASS') {
            DB::table('job_cards')->where('id',$job)->update(['customer_public_status'=>'Quality Check Passed']);
        } elseif ($data['result']==='FAIL') {
            DB::table('job_cards')->where('id',$job)->update([
                'status'=>'in-progress',
                'customer_public_status'=>'Rework / QC Failed',
                'completed_at'=>null,
            ]);
        } else {
            DB::table('job_cards')->where('id',$job)->update(['customer_public_status'=>'Quality Check']);
        }

        $after=(array)DB::table('workshop_qc_checks')->where('id',$id)->first();
        $this->audit($user,'QC_'.$data['result'],'QC_CHECK',(string)$id,$job,$data['assigned_staff_id'] ?? null,$data['reason'],null,$after);
        return response()->json(['ok'=>true,'qc'=>$after,'job'=>$this->job($job)],201);
    }

    public function roadTest(Request $request, int $job): JsonResponse
    {
        $user=$this->admin($request);
        $this->job($job);
        $data=$request->validate([
            'action'=>['required','in:START,COMPLETE,CANCEL'],
            'road_test_id'=>['nullable','integer','min:1'],
            'staff_id'=>['nullable','integer','min:1'],
            'driver_name'=>['nullable','string','max:150'],
            'odometer'=>['nullable','numeric','min:0'],
            'notes'=>['nullable','string','max:2000'],
            'reason'=>['required','string','max:1000'],
        ]);

        if ($data['action']==='START') {
            $id=DB::table('workshop_road_tests')->insertGetId([
                'job_card_id'=>$job,
                'staff_id'=>$data['staff_id'] ?? null,
                'driver_name'=>trim((string)($data['driver_name'] ?? '')) ?: null,
                'odometer_start'=>$data['odometer'] ?? null,
                'status'=>'RUNNING',
                'started_at'=>now('Asia/Kolkata'),
                'notes'=>trim((string)($data['notes'] ?? '')) ?: null,
                'created_by_user_id'=>$user->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $before=null;
        } else {
            $q=DB::table('workshop_road_tests')->where('job_card_id',$job);
            if (!empty($data['road_test_id'])) $q->where('id',(int)$data['road_test_id']);
            else $q->where('status','RUNNING')->orderByDesc('id');
            $row=$q->first();
            abort_unless($row,404,'Running Road Test not found.');
            $id=(int)$row->id;
            $before=(array)$row;
            $update=$data['action']==='COMPLETE'
                ? ['status'=>'COMPLETED','odometer_end'=>$data['odometer'] ?? null,'ended_at'=>now('Asia/Kolkata'),'updated_at'=>now()]
                : ['status'=>'CANCELLED','ended_at'=>now('Asia/Kolkata'),'updated_at'=>now()];
            if (array_key_exists('notes',$data)) $update['notes']=trim((string)$data['notes']) ?: $row->notes;
            DB::table('workshop_road_tests')->where('id',$id)->update($update);
        }
        $after=(array)DB::table('workshop_road_tests')->where('id',$id)->first();
        $this->audit($user,'ROAD_TEST_'.$data['action'],'ROAD_TEST',(string)$id,$job,$data['staff_id'] ?? null,$data['reason'],$before,$after);
        return response()->json(['ok'=>true,'road_test'=>$after]);
    }

    public function keyCustody(Request $request, int $job): JsonResponse
    {
        $user=$this->admin($request);
        $this->job($job);
        $data=$request->validate([
            'action'=>['required','in:HANDOVER,RETURN,KEY_BOARD,CUSTOMER'],
            'staff_id'=>['nullable','integer','min:1'],
            'holder_name'=>['nullable','string','max:150'],
            'notes'=>['nullable','string','max:1000'],
            'reason'=>['required','string','max:1000'],
        ]);
        $id=DB::table('workshop_key_custody')->insertGetId([
            'job_card_id'=>$job,
            'staff_id'=>$data['staff_id'] ?? null,
            'holder_name'=>trim((string)($data['holder_name'] ?? '')) ?: null,
            'action'=>$data['action'],
            'notes'=>trim((string)($data['notes'] ?? '')) ?: null,
            'admin_user_id'=>$user->id,
            'created_at'=>now('Asia/Kolkata'),
        ]);
        $after=(array)DB::table('workshop_key_custody')->where('id',$id)->first();
        $this->audit($user,'KEY_'.$data['action'],'KEY_CUSTODY',(string)$id,$job,$data['staff_id'] ?? null,$data['reason'],null,$after);
        return response()->json(['ok'=>true,'key_custody'=>$after],201);
    }

    public function rework(Request $request, int $job): JsonResponse
    {
        $user=$this->admin($request);
        $this->job($job);
        $data=$request->validate([
            'original_job_card_id'=>['nullable','integer','min:1'],
            'reason'=>['required','string','max:1000'],
            'complaint'=>['nullable','string','max:4000'],
        ]);
        if (!empty($data['original_job_card_id'])) $this->job((int)$data['original_job_card_id']);
        $id=DB::table('workshop_reworks')->insertGetId([
            'job_card_id'=>$job,
            'original_job_card_id'=>$data['original_job_card_id'] ?? null,
            'reason'=>$data['reason'],
            'complaint'=>trim((string)($data['complaint'] ?? '')) ?: null,
            'status'=>'OPEN',
            'opened_by_user_id'=>$user->id,
            'opened_at'=>now('Asia/Kolkata'),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('job_cards')->where('id',$job)->update([
            'status'=>'in-progress',
            'customer_public_status'=>'Rework / Comeback',
            'closed_at'=>null,
            'cancelled_at'=>null,
        ]);
        $after=(array)DB::table('workshop_reworks')->where('id',$id)->first();
        $this->audit($user,'REWORK_OPEN','REWORK',(string)$id,$job,null,$data['reason'],null,$after);
        return response()->json(['ok'=>true,'rework'=>$after,'job'=>$this->job($job)],201);
    }

    public function history(Request $request, int $job): JsonResponse
    {
        $this->admin($request);
        $card=$this->job($job);
        $q=DB::table('job_cards')->orderByDesc('id');
        $q->where(function($w) use($card){
            if (!empty($card->customer_id)) $w->orWhere('customer_id',$card->customer_id);
            if (!empty($card->vehicle_id)) $w->orWhere('vehicle_id',$card->vehicle_id);
            if (!empty($card->vehicle_reg_no)) $w->orWhere('vehicle_reg_no',$card->vehicle_reg_no);
            if (!empty($card->customer_phone)) $w->orWhere('customer_phone',$card->customer_phone);
        });
        $jobs=$q->limit(100)->get();

        $vehicle=null;
        if (!empty($card->vehicle_id) && Schema::hasTable('am_vehicle_master')) {
            $vehicle=DB::table('am_vehicle_master')->where('id',$card->vehicle_id)->first();
        }
        $customer=null;
        if (!empty($card->customer_id) && Schema::hasTable('am_customer_master')) {
            $customer=DB::table('am_customer_master')->where('id',$card->customer_id)->first();
        }

        return response()->json(['ok'=>true,'customer'=>$customer,'vehicle'=>$vehicle,'job_history'=>$jobs]);
    }

    public function correctAttendance(Request $request, int $attendance): JsonResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'check_in'=>['nullable','date'],
            'check_out'=>['nullable','date'],
            'status'=>['nullable','string','max:20'],
            'notes'=>['nullable','string','max:2000'],
            'reason'=>['required','string','max:1000'],
        ]);
        $row=DB::table('attendance')->where('id',$attendance)->first();
        abort_unless($row,404,'Attendance record not found.');
        $before=(array)$row;
        $update=[];
        foreach(['check_in','check_out','status','notes'] as $k) if(array_key_exists($k,$data)) $update[$k]=$data[$k];
        if (!$update) throw ValidationException::withMessages(['attendance'=>'No correction field supplied.']);

        DB::table('attendance')->where('id',$attendance)->update($update);
        $after=(array)DB::table('attendance')->where('id',$attendance)->first();
        $this->audit($user,'ATTENDANCE_CORRECTION','ATTENDANCE',(string)$attendance,null,(int)$row->staff_id,$data['reason'],$before,$after);
        return response()->json(['ok'=>true,'attendance'=>$after]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->admin($request);
        if (!Schema::hasTable('staff_reminder_state')) return response()->json(['ok'=>true,'alerts'=>[]]);

        $q=DB::table('staff_reminder_state as r')
            ->leftJoin('staff as s','s.id','=','r.staff_id')
            ->select('r.*','s.staff_code','s.name as staff_name','s.department')
            ->whereNull('r.resolved_at')
            ->orderByRaw('CASE WHEN r.escalated_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('r.first_due_at')
            ->limit(300);

        if($request->filled('staff_id')) $q->where('r.staff_id',(int)$request->query('staff_id'));
        return response()->json(['ok'=>true,'alerts'=>$q->get()]);
    }

    public function auditIndex(Request $request): JsonResponse
    {
        $this->admin($request);
        if (!Schema::hasTable('workshop_owner_audit_logs')) return response()->json(['ok'=>true,'audit'=>[]]);
        $q=DB::table('workshop_owner_audit_logs')->orderByDesc('id');
        if($request->filled('job_card_id')) $q->where('job_card_id',(int)$request->query('job_card_id'));
        if($request->filled('staff_id')) $q->where('staff_id',(int)$request->query('staff_id'));
        return response()->json(['ok'=>true,'audit'=>$q->limit(300)->get()]);
    }

    private function lifecycle(object $job): string
    {
        $status=strtolower(trim((string)($job->status ?? 'open')));
        $public=strtolower(trim((string)($job->customer_public_status ?? '')));
        if ($status==='invoiced' || !empty($job->invoice_id)) return 'INVOICED';
        if ($status==='cancelled' || !empty($job->cancelled_at)) return 'CANCELLED';
        if ($status==='closed' || !empty($job->closed_at)) return 'CLOSED';
        if (str_contains($public,'ready for delivery')) return 'READY_FOR_DELIVERY';
        if (str_contains($public,'quality check passed')) return 'QC_PASSED';
        if (str_contains($public,'quality check')) return 'QC_PENDING';
        if (str_contains($public,'work complete') || $status==='completed') return 'WORK_COMPLETE';
        if ($status==='in-progress' || str_contains($public,'repair in progress') || str_contains($public,'rework')) return 'WIP';
        return 'OPEN';
    }

    private function activeStaff(int $staffId, bool $nullable=false): ?object
    {
        if ($staffId<=0) {
            if ($nullable) return null;
            throw ValidationException::withMessages(['staff_id'=>'Active Staff required.']);
        }
        $staff=DB::table('staff')->where('id',$staffId)->whereRaw("LOWER(COALESCE(status,'active'))='active'")->first();
        if(!$staff) throw ValidationException::withMessages(['staff_id'=>'Selected active Staff not found.']);
        return $staff;
    }

    private function job(int $job, bool $lock=false): object
    {
        $q=DB::table('job_cards')->where('id',$job);
        if($lock) $q->lockForUpdate();
        $row=$q->first();
        abort_unless($row,404,'Job Card not found.');
        return $row;
    }

    private function existingColumns(string $table, array $data): array
    {
        return array_filter($data, fn($v,$k)=>Schema::hasColumn($table,$k), ARRAY_FILTER_USE_BOTH);
    }

    private function audit(
        ErpAuthUser $user,
        string $action,
        string $entityType,
        string $entityId,
        ?int $jobCardId,
        ?int $staffId,
        string $reason,
        ?array $before=null,
        ?array $after=null,
        array $meta=[]
    ): void {
        $now=now('Asia/Kolkata');
        if (Schema::hasTable('workshop_owner_audit_logs')) {
            DB::table('workshop_owner_audit_logs')->insert([
                'admin_user_id'=>$user->id,
                'admin_name'=>$user->name ?: $user->email,
                'action'=>$action,
                'entity_type'=>$entityType,
                'entity_id'=>$entityId,
                'job_card_id'=>$jobCardId,
                'staff_id'=>$staffId,
                'reason'=>$reason,
                'before_json'=>$before ? json_encode($before,JSON_UNESCAPED_UNICODE) : null,
                'after_json'=>$after ? json_encode($after,JSON_UNESCAPED_UNICODE) : null,
                'meta_json'=>$meta ? json_encode($meta,JSON_UNESCAPED_UNICODE) : null,
                'created_at'=>$now,
            ]);
        }

        if ($jobCardId && Schema::hasTable('job_card_change_audits')) {
            $payload=[
                'job_card_id'=>$jobCardId,
                'entity_type'=>$entityType,
                'entity_id'=>$entityId,
                'action'=>$action,
                'reason'=>$reason,
                'before_json'=>$before ? json_encode($before,JSON_UNESCAPED_UNICODE) : null,
                'after_json'=>$after ? json_encode($after,JSON_UNESCAPED_UNICODE) : null,
                'actor'=>$user->email ?: $user->name,
                'created_at'=>$now,
                'updated_at'=>$now,
            ];
            DB::table('job_card_change_audits')->insert($this->existingColumns('job_card_change_audits',$payload));
        }
    }

    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Owner/Admin permission required.');
        return $user;
    }
}
