<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX30.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');

function replaceOnce(string $path,string $old,string $new,string $label): void {
    $c=file_get_contents($path);
    if (str_contains($c,$new)) return;
    $count=substr_count($c,$old);
    if ($count!==1) throw new RuntimeException($label." marker count=".$count);
    file_put_contents($path,str_replace($old,$new,$c));
}

$service="$root/app/Services/EstimateService.php";
$controller="$root/app/Http/Controllers/Web/AdminEstimatePageController.php";
$view="$root/resources/views/job-cards/show.blade.php";

/* EstimateService: supplementary eligibility is separate from initial/revision eligibility. */
replaceOnce(
    $service,
    "public function assertEligible(JobCard \$job): void",
    "public function assertEligible(JobCard \$job, bool \$supplementary = false): void",
    "assertEligible signature"
);

$old=<<<'OLD'
        if (!in_array(strtolower((string)$job->inspection_status), ['completed','skipped'], true)) {
            throw ValidationException::withMessages(['estimate'=>'Complete Vehicle Inspection first.']);
        }
        if (strtolower((string)$job->diagnosis_status) !== 'completed') {
OLD;
$new=<<<'NEW'
        if (!in_array(strtolower((string)$job->inspection_status), ['completed','skipped'], true)) {
            throw ValidationException::withMessages(['estimate'=>'Complete Vehicle Inspection first.']);
        }
        if ($supplementary) {
            if (!in_array(strtolower(trim((string)$job->status)), [
                'open','completed','in progress','in-progress','work in progress','wip'
            ], true)) {
                throw ValidationException::withMessages([
                    'estimate'=>'Supplementary Estimate is not allowed in the current Job Card status.'
                ]);
            }
            return;
        }
        if (strtolower((string)$job->diagnosis_status) !== 'completed') {
NEW;
replaceOnce($service,$old,$new,"supplementary eligibility bypass");

$old=<<<'OLD'
            $requestedKind = strtoupper(trim((string)($payload['estimate_kind'] ?? 'INITIAL')));
            if (!in_array($requestedKind,['INITIAL','REVISED','SUPPLEMENTARY'],true)) {
                $requestedKind = 'REVISED';
            }

            $this->assertEligible($job);

            $jobStatusForEstimate = strtolower(trim((string)$job->status));
OLD;
$new=<<<'NEW'
            $requestedKind = strtoupper(trim((string)($payload['estimate_kind'] ?? 'INITIAL')));
            if (!in_array($requestedKind,['INITIAL','REVISED','SUPPLEMENTARY'],true)) {
                $requestedKind = 'REVISED';
            }

            $releasedBase = JobEstimate::query()
                ->where('job_card_id',$jobId)
                ->whereNotNull('converted_to_job_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $isSupplementary = $requestedKind === 'SUPPLEMENTARY';
            if ($isSupplementary && !$releasedBase) {
                throw ValidationException::withMessages([
                    'estimate_kind'=>'Supplementary Estimate requires a previously approved Estimate that was SENT TO JOB CARD.'
                ]);
            }

            $this->assertEligible($job,$isSupplementary);

            $jobStatusForEstimate = strtolower(trim((string)$job->status));
NEW;
replaceOnce($service,$old,$new,"saveDraft supplementary gate");

$old=<<<'OLD'
            $job->current_estimate_id = $estimate->id;
            $job->status = 'open';
            $job->customer_public_status = 'Estimate Preparation';
            $job->save();
            $this->publicLog($job->id,'Estimate Preparation',$legacyCompleted ? 'Legacy/Completed Job Card moved to Before WIP and Estimate draft saved' : 'Diagnosis completed — estimate is being prepared','ADMIN');
OLD;
$new=<<<'NEW'
            $job->current_estimate_id = $estimate->id;
            if (!$isWipEstimate) {
                $job->status = 'open';
            }
            $job->customer_public_status = $isWipEstimate && $isSupplementary
                ? 'Supplementary Estimate Preparation'
                : 'Estimate Preparation';
            $job->save();
            $this->publicLog(
                $job->id,
                $isWipEstimate && $isSupplementary ? 'Supplementary Estimate Preparation' : 'Estimate Preparation',
                $isWipEstimate && $isSupplementary
                    ? 'Additional work found during WIP — Supplementary Estimate draft saved without reopening Diagnosis.'
                    : ($legacyCompleted ? 'Legacy/Completed Job Card moved to Before WIP and Estimate draft saved' : 'Diagnosis completed — estimate is being prepared'),
                'ADMIN'
            );
NEW;
replaceOnce($service,$old,$new,"preserve WIP on supplementary draft");

$c=file_get_contents($service);
$needle='$this->assertEligible($job);';
$count=substr_count($c,$needle);
if ($count===2) {
    $c=str_replace($needle,"\$this->assertEligible(\$job, strtoupper((string)\$estimate->estimate_kind)==='SUPPLEMENTARY');",$c);
    file_put_contents($service,$c);
} elseif ($count!==0) {
    throw new RuntimeException("send/approve assertEligible marker count=".$count);
}

/* Controller: decide supplementary mode before strict eligibility. */
$old=<<<'OLD'
        $jobCard=JobCard::query()->findOrFail($job);
        $service->assertEligible($jobCard);
        $latest=JobEstimate::query()->where('job_card_id',$job)->where('current_flag',1)->orderByDesc('id')->first();
        $estimate=($latest && strtoupper((string)$latest->status)==='DRAFT')?$latest:null;
        $source=$estimate ?: $latest;
        // Once an approved estimate is released to the Job Card, any extra work must start as a clean SUPPLEMENTARY estimate
        // so already-released scope is not duplicated. Before release, a revision may copy the previous scope.
        $releasedLatest=!$estimate && $latest && $latest->converted_to_job_at;
        $items=($source && !$releasedLatest)?JobEstimateItem::query()->where('estimate_id',$source->id)->orderBy('sort_order')->orderBy('id')->get():collect();
        $defaultKind=$releasedLatest?'SUPPLEMENTARY':($latest?'REVISED':'INITIAL');
OLD;
$new=<<<'NEW'
        $jobCard=JobCard::query()->findOrFail($job);
        $latest=JobEstimate::query()->where('job_card_id',$job)->where('current_flag',1)->orderByDesc('id')->first();
        $estimate=($latest && strtoupper((string)$latest->status)==='DRAFT')?$latest:null;
        $releasedBase=JobEstimate::query()->where('job_card_id',$job)->whereNotNull('converted_to_job_at')->orderByDesc('id')->first();
        $jobStatus=strtolower(trim((string)$jobCard->status));
        $supplementaryStatus=in_array($jobStatus,['open','completed','in progress','in-progress','work in progress','wip'],true);
        $supplementaryFlow=$estimate
            ? strtoupper((string)$estimate->estimate_kind)==='SUPPLEMENTARY'
            : (bool)($releasedBase && $supplementaryStatus);

        $service->assertEligible($jobCard,$supplementaryFlow);

        // Once approved work has been released to the Job Card, additional scope starts clean as SUPPLEMENTARY.
        // An existing supplementary DRAFT keeps its own items when reopened.
        $source=$estimate ?: ($supplementaryFlow ? null : $latest);
        $items=$source?JobEstimateItem::query()->where('estimate_id',$source->id)->orderBy('sort_order')->orderBy('id')->get():collect();
        $defaultKind=$estimate
            ? strtoupper((string)$estimate->estimate_kind)
            : ($supplementaryFlow?'SUPPLEMENTARY':($latest?'REVISED':'INITIAL'));
NEW;
replaceOnce($controller,$old,$new,"create supplementary mode");

/* Remove the temporary generic FIX29 shortcut if present; FIX30 integrates correct state-aware actions. */
$fix29=<<<'OLD'
@section('content')
{{-- FIX29_SUPPLEMENTARY_ESTIMATE_SHORTCUT --}}
<div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin:0 0 12px 0">
  <a class="btn primary" href="{{ route('erp.estimates.create', request()->route('job')) }}">🧾 Estimate / Supplementary Estimate</a>
</div>
OLD;
$vc=file_get_contents($view);
if (str_contains($vc,$fix29)) {
    $vc=str_replace($fix29,"@section('content')",$vc);
    file_put_contents($view,$vc);
}

$old=<<<'OLD'
$latestEstimate=$estimates->first();
$latestEstimateStatus=$latestEstimate ? strtoupper((string)$latestEstimate->status) : null;
$latestEstimateReleased=$latestEstimate && $latestEstimate->converted_to_job_at;
OLD;
$new=<<<'NEW'
$latestEstimate=$estimates->first();
$latestEstimateStatus=$latestEstimate ? strtoupper((string)$latestEstimate->status) : null;
$latestEstimateReleased=$latestEstimate && $latestEstimate->converted_to_job_at;
$hasReleasedEstimate=$estimates->contains(function($x){ return !empty($x->converted_to_job_at); });
$jobStatusForEstimate=strtolower(trim((string)$job->status));
$supplementaryReady=$hasReleasedEstimate && in_array($jobStatusForEstimate,[
    'open','completed','in progress','in-progress','work in progress','wip'
],true);
NEW;
replaceOnce($view,$old,$new,"Job Card supplementary state");

$old=<<<'OLD'
    @if($diagnosisReady)
      <a class="btn primary" href="{{ route('erp.estimates.create',$job->id) }}">Estimate / Revise</a>
    @else
      <a class="btn warning-btn" href="#diagnosis-panel">Complete Diagnosis First</a>
    @endif
OLD;
$new=<<<'NEW'
    @if($supplementaryReady)
      <a class="btn primary" href="{{ route('erp.estimates.create',$job->id) }}">＋ Supplementary Estimate</a>
    @elseif($diagnosisReady)
      <a class="btn primary" href="{{ route('erp.estimates.create',$job->id) }}">Estimate / Revise</a>
    @else
      <a class="btn warning-btn" href="#diagnosis-panel">Complete Diagnosis First</a>
    @endif
NEW;
replaceOnce($view,$old,$new,"top estimate action");

replaceOnce(
    $view,
    '      <span class="workflow-pill {{ $diagnosisReady ? \'ok\' : \'locked\' }}">Estimate: {{ $diagnosisReady ? \'UNLOCKED\' : \'LOCKED\' }}</span>',
    '      <span class="workflow-pill {{ ($diagnosisReady || $supplementaryReady) ? \'ok\' : \'locked\' }}">Estimate: {{ $supplementaryReady ? \'SUPPLEMENTARY READY\' : ($diagnosisReady ? \'UNLOCKED\' : \'LOCKED\') }}</span>',
    "estimate badge"
);

$old=<<<'OLD'
  @elseif(!$diagnosisReady)
    <div class="workflow-message waiting">
OLD;
$new=<<<'NEW'
  @elseif($supplementaryReady)
    <div class="workflow-message ready">
      <strong>＋ Supplementary Estimate Ready</strong>
      <span>Approved work pehle hi Job Card me release ho chuka hai. WIP me Diagnosis ko dobara reopen/complete karne ki zarurat nahi hai. Additional work ko Supplementary Estimate me add kijiye.</span>
    </div>
    <div class="diagnosis-actions">
      <a class="btn ghost" href="{{ route('erp.job-cards.inspection-report',$job->id) }}">🔍 View Inspection Report</a>
      <a class="btn primary" href="{{ route('erp.estimates.create',$job->id) }}">＋ Open Supplementary Estimate →</a>
    </div>
  @elseif(!$diagnosisReady)
    <div class="workflow-message waiting">
NEW;
replaceOnce($view,$old,$new,"diagnosis panel supplementary state");

$old=<<<'OLD'
  <div class="section-head"><div><h2 class="section-title">Estimate History</h2><p class="muted">Approved estimate remains locked. Work reaches the Job Card only after explicit SEND TO JOB CARD.</p></div>@if($diagnosisReady)<a class="btn small primary" href="{{ route('erp.estimates.create',$job->id) }}">Open Estimate</a>@else<a class="btn small warning-btn" href="#diagnosis-panel">Complete Diagnosis First</a>@endif</div>
OLD;
$new=<<<'NEW'
  <div class="section-head"><div><h2 class="section-title">Estimate History</h2><p class="muted">Approved estimate remains locked. Work reaches the Job Card only after explicit SEND TO JOB CARD.</p></div>@if($supplementaryReady)<a class="btn small primary" href="{{ route('erp.estimates.create',$job->id) }}">＋ Supplementary Estimate</a>@elseif($diagnosisReady)<a class="btn small primary" href="{{ route('erp.estimates.create',$job->id) }}">Open Estimate</a>@else<a class="btn small warning-btn" href="#diagnosis-panel">Complete Diagnosis First</a>@endif</div>
NEW;
replaceOnce($view,$old,$new,"estimate history action");

replaceOnce(
    $view,
    '      <a class="btn primary" href="{{ route(\'erp.estimates.create\',$job->id) }}">Open Estimate →</a>',
    '      <a class="btn primary" href="{{ route(\'erp.estimates.create\',$job->id) }}">{{ $supplementaryReady ? \'＋ Supplementary Estimate →\' : \'Open Estimate →\' }}</a>',
    "diagnosis-ready estimate action"
);

echo "FIX30 patch applied.\n";
