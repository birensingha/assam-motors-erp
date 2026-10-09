<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX36B.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');

function rw(string $path,string $old,string $new,string $label): void {
    $c=file_get_contents($path);
    if (str_contains($c,$new)) return;
    $n=substr_count($c,$old);
    if ($n!==1) throw new RuntimeException($label." marker count=".$n);
    file_put_contents($path,str_replace($old,$new,$c));
}

/* 1) Routes: Admin inspection assignment + Staff web inspection work/submit. */
$p="$root/routes/web.php";
rw(
    $p,
    "    Route::get('/job-cards/{job}/inspection-report', [AdminJobCardPageController::class,'inspectionReport'])->whereNumber('job')->name('erp.job-cards.inspection-report');",
    "    Route::get('/job-cards/{job}/inspection-report', [AdminJobCardPageController::class,'inspectionReport'])->whereNumber('job')->name('erp.job-cards.inspection-report');\n".
    "    Route::post('/job-cards/{job}/inspection/assign-technician', [AdminJobCardPageController::class,'assignInspectionTechnician'])->whereNumber('job')->name('erp.job-cards.inspection.assign-technician');",
    'admin inspection assignment route'
);
rw(
    $p,
    "    Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');",
    "    Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');\n".
    "    Route::get('/inspections/{job}/work', [StaffPortalController::class,'inspectionWork'])->whereNumber('job')->name('staff.inspection-work');\n".
    "    Route::post('/inspections/{job}/work', [StaffPortalController::class,'saveInspectionWork'])->whereNumber('job')->name('staff.inspection-submit');",
    'staff inspection work routes'
);

/* 2) Admin Job Card controller: preserve submitted inspector + assignment action. */
$p="$root/app/Http/Controllers/Web/AdminJobCardPageController.php";
rw(
    $p,
    "        \$data=\$service->detail(\$job);\n        \$data['staff']=Staff::query()->whereRaw(\"LOWER(status)='active'\")->orderBy('name')->get(['id','staff_code','name','role','department']);\n        return view('job-cards.show',\$data);",
    "        \$data=\$service->detail(\$job);\n".
    "        \$data['staff']=Staff::query()->whereRaw(\"LOWER(status)='active'\")->orderBy('name')->get(['id','staff_code','name','role','department']);\n".
    "        \$data['inspectionSubmittedBy']=null;\n".
    "        \$card=\$data['job'] ?? null;\n".
    "        if (\$card && !empty(\$card->inspection_submitted_by)) {\n".
    "            \$data['inspectionSubmittedBy']=Staff::query()->whereKey((int)\$card->inspection_submitted_by)->first(['id','staff_code','name']);\n".
    "        }\n".
    "        return view('job-cards.show',\$data);",
    'show submitted inspector'
);

$assignMethod=<<<'PHP'
    public function assignInspectionTechnician(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'technician_id'=>['required','integer','min:1'],
        ]);

        DB::transaction(function() use($job,$data,$user){
            /** @var JobCard $card */
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            $status=strtolower(trim((string)$card->status));

            if (in_array($status,['invoiced','closed','cancelled'],true)) {
                throw ValidationException::withMessages(['technician_id'=>'Locked Job Card cannot change Vehicle Inspection assignment.']);
            }
            if (in_array(strtolower((string)$card->inspection_status),['completed','skipped'],true)) {
                throw ValidationException::withMessages(['technician_id'=>'Vehicle Inspection is already completed. Submitted inspector is preserved in the Inspection Report.']);
            }

            $staff=Staff::query()->whereKey((int)$data['technician_id'])->first();
            if (!$staff || strtolower((string)$staff->status)!=='active') {
                throw ValidationException::withMessages(['technician_id'=>'Selected Inspection Technician is not active.']);
            }

            $before=$card->toArray();
            $card->technician_id=(int)$staff->id;
            $card->technician=(string)$staff->name;
            $card->inspection_status='pending';
            $card->inspection_submitted_at=null;
            $card->inspection_submitted_by=null;
            $card->diagnosis_status='pending';
            $card->decision_status='pending';
            $card->estimate_allowed=0;
            $card->estimate_allowed_at=null;
            $card->estimate_allowed_by=null;
            $card->customer_public_status='Vehicle Inspection Assigned';
            $card->save();

            $this->auditChange(
                $job,
                'JOB_CARD',
                $job,
                'INSPECTION_TECHNICIAN_ASSIGNED',
                $before,
                $card->fresh()->toArray(),
                'Vehicle Inspection assigned to '.$staff->name.' ('.$staff->staff_code.').',
                $user
            );
        });

        return redirect()->route('erp.job-cards.show',$job)
            ->with('success','Vehicle Inspection assigned. It will now appear in the selected Staff Portal / Android app.');
    }

PHP;
rw(
    $p,
    "    public function assignDecidingTechnician(Request \$request, int \$job): RedirectResponse",
    $assignMethod."    public function assignDecidingTechnician(Request \$request, int \$job): RedirectResponse",
    'assign inspection method'
);

/* 3) Staff dashboard: pending inspection queue. */
$p="$root/app/Services/StaffDashboardService.php";
$pending=<<<'PHP'
        $inspectionPending = JobCard::query()
            ->where('technician_id', $staffId)
            ->where(function ($q) {
                $q->whereNull('inspection_status')
                  ->orWhereRaw("LOWER(COALESCE(inspection_status,'pending')) NOT IN ('completed','skipped')");
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id','job_no','customer_name','customer_phone','vehicle_reg_no','vehicle_make','vehicle_model',
                'vehicle_year','service_type','customer_complaint','inspection_status','vehicle_inward_at'
            ]);

PHP;
rw($p,"        \$diagnosisReviews = JobCard::query()",$pending."        \$diagnosisReviews = JobCard::query()",'pending inspection query');
rw(
    $p,
    "                'pending_notifications' => \$pending->count(),",
    "                'pending_notifications' => \$pending->count(),\n                'pending_inspections' => \$inspectionPending->count(),",
    'pending inspection summary'
);
$pendingMap=<<<'PHP'
            'rots' => $rots->map(fn ($r) => $this->rotPublic($r))->values(),
            'inspection_pending' => $inspectionPending->map(fn ($j) => [
                'id' => $j->id,
                'job_no' => $j->job_no,
                'vehicle_reg_no' => $j->vehicle_reg_no,
                'vehicle' => trim(($j->vehicle_make ?? '').' '.($j->vehicle_model ?? '')),
                'vehicle_year' => $j->vehicle_year,
                'customer_name' => $j->customer_name,
                'customer_complaint' => $j->customer_complaint,
                'service_type' => $j->service_type,
                'inspection_status' => $j->inspection_status ?: 'pending',
                'vehicle_inward_at' => $j->vehicle_inward_at,
            ])->values(),
PHP;
rw(
    $p,
    "            'rots' => \$rots->map(fn (\$r) => \$this->rotPublic(\$r))->values(),",
    $pendingMap,
    'pending inspection map'
);

/* 4) Staff web controller: pending work + submit, and submitted inspector access. */
$p="$root/app/Http/Controllers/Web/StaffPortalController.php";
rw(
    $p,
    "\$card=JobCard::query()->whereKey(\$job)->where('technician_id',\$staffId)->first();",
    "\$card=JobCard::query()->whereKey(\$job)->where(function(\$q) use(\$staffId){\n".
    "            \$q->where('technician_id',\$staffId)->orWhere('inspection_submitted_by',\$staffId);\n".
    "        })->first();",
    'inspection report access'
);
$portalMethods=<<<'PHP'
    public function inspectionWork(Request $request, int $job): View
    {
        $user=$this->staff($request);
        $staffId=(int)$user->staff_id;

        /** @var JobCard $card */
        $card=JobCard::query()->whereKey($job)->where('technician_id',$staffId)->first();
        abort_unless($card,403,'This Vehicle Inspection is not assigned to you.');
        abort_if(in_array(strtolower((string)$card->inspection_status),['completed','skipped'],true),422,'Vehicle Inspection is already completed.');

        $items=Schema::hasTable('job_card_inspection')
            ? DB::table('job_card_inspection')->where('job_card_id',$job)->orderBy('id')->get()
            : collect();

        return view('staff.inspection-work',[
            'job'=>$card,
            'items'=>$items,
        ]);
    }

    public function saveInspectionWork(Request $request, int $job): RedirectResponse
    {
        $user=$this->staff($request);
        $staffId=(int)$user->staff_id;
        $data=$request->validate([
            'items'=>['required','array','min:1'],
            'items.*.item_name'=>['required','string','max:100'],
            'items.*.status'=>['required','in:good,fair,poor'],
            'items.*.remarks'=>['nullable','string','max:1000'],
        ]);

        DB::transaction(function() use($job,$staffId,$data){
            /** @var JobCard $card */
            $card=JobCard::query()->whereKey($job)->where('technician_id',$staffId)->lockForUpdate()->first();
            abort_unless($card,403,'This Vehicle Inspection is not assigned to you.');
            if (in_array(strtolower((string)$card->inspection_status),['completed','skipped'],true)) {
                abort(422,'Vehicle Inspection is already completed.');
            }

            if (Schema::hasTable('job_card_inspection')) {
                DB::table('job_card_inspection')->where('job_card_id',$job)->delete();
                foreach($data['items'] as $it){
                    DB::table('job_card_inspection')->insert([
                        'job_card_id'=>$job,
                        'item_name'=>trim((string)$it['item_name']),
                        'status'=>(string)$it['status'],
                        'remarks'=>trim((string)($it['remarks'] ?? '')),
                        'photo'=>'',
                        'created_at'=>now(),
                    ]);
                }
            }

            $card->inspection_status='completed';
            $card->inspection_submitted_at=now();
            $card->inspection_submitted_by=$staffId;
            $card->diagnosis_status='pending';
            $card->estimate_allowed=0;
            $card->customer_public_status='Inspection Completed';
            $card->save();
        });

        return redirect()->route('staff.inspection-report',$job)
            ->with('success','Vehicle Inspection completed and sent to Job Card.');
    }

PHP;
rw(
    $p,
    "    public function checkIn(Request \$request, AttendanceService \$attendance, MandatoryNotificationService \$notifications): JsonResponse",
    $portalMethods."    public function checkIn(Request \$request, AttendanceService \$attendance, MandatoryNotificationService \$notifications): JsonResponse",
    'staff inspection work methods'
);

/* 5) Staff web portal: visible pending queue. */
$p="$root/resources/views/staff/portal.blade.php";
rw(
    $p,
    "<div><span>Running ROT</span><strong>{{ \$data['summary']['running'] }}</strong></div><div><span>Paused ROT</span><strong>{{ \$data['summary']['paused'] }}</strong></div><div><span>Pending Alerts</span><strong>{{ \$data['summary']['pending_notifications'] }}</strong></div>",
    "<div><span>Inspection</span><strong>{{ \$data['summary']['pending_inspections'] ?? 0 }}</strong></div><div><span>Running ROT</span><strong>{{ \$data['summary']['running'] }}</strong></div><div><span>Paused ROT</span><strong>{{ \$data['summary']['paused'] }}</strong></div><div><span>Pending Alerts</span><strong>{{ \$data['summary']['pending_notifications'] }}</strong></div>",
    'staff portal inspection counter'
);
$pendingSection=<<<'BLADE'
<section class="staff-panel inspection-review-panel">
  <div class="panel-head">
    <h2>🚗 Vehicle Inspections Assigned To Me</h2>
    <span>Complete before Diagnosis / Estimate</span>
  </div>
  @if(count($data['inspection_pending'] ?? [])===0)
    <p class="empty">No pending Vehicle Inspection assigned to you.</p>
  @else
    <div class="inspection-review-list">
      @foreach($data['inspection_pending'] as $j)
        <article class="inspection-review-row">
          <div class="inspection-review-main">
            <small>{{ $j['job_no'] }} · {{ $j['vehicle_reg_no'] ?: 'Vehicle —' }}</small>
            <h3>{{ $j['vehicle'] ?: 'Vehicle Inspection' }}</h3>
            <p><b>Customer:</b> {{ $j['customer_name'] ?: '—' }}</p>
            <p><b>Complaint:</b> {{ $j['customer_complaint'] ?: '—' }}</p>
            <div class="inspection-review-statuses">
              <span class="status paused">Inspection {{ strtoupper($j['inspection_status'] ?: 'pending') }}</span>
            </div>
          </div>
          <div class="inspection-review-actions">
            <a class="staff-primary-link" href="{{ route('staff.inspection-work',$j['id']) }}">Start / Continue Inspection →</a>
          </div>
        </article>
      @endforeach
    </div>
  @endif
</section>
BLADE;
rw(
    $p,
    "<section class=\"staff-panel inspection-review-panel\">\n  <div class=\"panel-head\">\n    <h2>🔍 Inspection Reports / Diagnosis Review</h2>",
    $pendingSection."\n<section class=\"staff-panel inspection-review-panel\">\n  <div class=\"panel-head\">\n    <h2>🔍 Inspection Reports / Diagnosis Review</h2>",
    'staff portal pending inspection section'
);

/* 6) Admin Job Card UI: assignment before inspection + submitted inspector after completion. */
$p="$root/resources/views/job-cards/show.blade.php";
$oldPending=<<<'BLADE'
  @if(!$inspectionReady)
    <div class="workflow-message locked">
      <strong>🔒 Vehicle Inspection Pending</strong>
      <span>Vehicle Inspection complete/skip hone ke baad hi Final Diagnosis officially complete kiya ja sakta hai.</span>
    </div>
BLADE;
$newPending=<<<'BLADE'
  @if(!$inspectionReady)
    <div class="workflow-message locked">
      <strong>🔒 Vehicle Inspection Pending</strong>
      <span>Inspection assigned staff ke Staff Portal / Android app me ye Job Card pending task ke roop me dikhega. Inspection submit hone ke baad report yahin available hoga.</span>
    </div>

    <div class="deciding-tech-box">
      <div class="deciding-tech-status">
        <span>Vehicle Inspection Assigned To</span>
        <strong>{{ $job->technician ?: 'Not Assigned' }}</strong>
        @if($job->technician_id)
          <small>✓ Staff ID #{{ $job->technician_id }} · Pending Inspection task enabled.</small>
        @else
          <small>Inspection Technician assign kijiye. Bina assignment Staff Portal me task nahi dikhega.</small>
        @endif
      </div>
      <form method="post" action="{{ route('erp.job-cards.inspection.assign-technician',$job->id) }}" class="deciding-tech-form">
        @csrf
        <label>Select Inspection Technician
          <select name="technician_id" required>
            <option value="">Select staff…</option>
            @foreach($staff as $s)
              <option value="{{ $s->id }}" @selected((int)old('technician_id',$job->technician_id)===(int)$s->id)>{{ $s->name }} ({{ $s->staff_code }})</option>
            @endforeach
          </select>
        </label>
        <button class="btn primary">{{ $job->technician_id ? 'Change Inspection Assignment' : 'Assign Vehicle Inspection' }}</button>
      </form>
    </div>
BLADE;
rw($p,$oldPending,$newPending,'Job Card pending inspection assignment UI');

$oldReport=<<<'BLADE'
        <span class="inspection-review-label">Vehicle Inspection Report</span>
        <strong>{{ strtoupper((string)$job->inspection_status) }}</strong>
        <small>Submitted: {{ optional($job->inspection_submitted_at)->format('d-m-Y h:i A') ?: '—' }}</small>
BLADE;
$newReport=<<<'BLADE'
        <span class="inspection-review-label">Vehicle Inspection Report</span>
        <strong>{{ strtoupper((string)$job->inspection_status) }}</strong>
        <small>Submitted: {{ optional($job->inspection_submitted_at)->format('d-m-Y h:i A') ?: '—' }}</small>
        <small>Submitted By: {{ $inspectionSubmittedBy?->name ?: 'Staff #'.($job->inspection_submitted_by ?: '—') }}{{ $inspectionSubmittedBy?->staff_code ? ' ('.$inspectionSubmittedBy->staff_code.')' : '' }}</small>
BLADE;
rw($p,$oldReport,$newReport,'Job Card submitted inspector UI');

echo "FIX36B patch applied.\n";
