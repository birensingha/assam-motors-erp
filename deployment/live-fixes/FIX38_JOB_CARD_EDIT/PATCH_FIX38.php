<?php
declare(strict_types=1);

$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')){
    fwrite(STDERR,"Usage: php PATCH_FIX38.php /path/to/laravel/app\n");
    exit(2);
}
$f=$root.'/resources/views/job-cards/form.blade.php';
if(!is_file($f)) throw new RuntimeException('Missing job-cards/form.blade.php');
$c=file_get_contents($f);
if(str_contains($c,'FIX38_EDIT_FORM_CONSISTENCY')){
    echo "FIX38 already installed\n";
    exit(0);
}
function once(string $src,string $old,string $new,string $id): string {
    $n=substr_count($src,$old);
    if($n!==1) throw new RuntimeException($id.' anchor count='.$n);
    return str_replace($old,$new,$src);
}
$old='<form method="post" action="{{ $editing ? route(\'erp.job-cards.update\',$job->id) : route(\'erp.job-cards.store\') }}" class="classic-form-shell">@csrf';
$new=<<<'BLADE'
{{-- FIX38_EDIT_FORM_CONSISTENCY --}}
<form method="post" action="{{ $editing ? route('erp.job-cards.update',$job->id) : route('erp.job-cards.store') }}" class="classic-form-shell">
  @csrf
  @if($editing)
    @method('PUT')
  @endif
BLADE;
$c=once($c,$old,$new,'shared form method');

$anchor=<<<'BLADE'
  <div class="sticky-form-actions">
    <a class="btn" href="{{ $editing ? route('erp.job-cards.show',$job->id) : route('erp.job-cards.index') }}">Cancel</a>
    <button class="btn primary big-action">{{ $editing ? '💾 Save Job Card Changes' : '＋ Create Job Card' }}</button>
  </div>
BLADE;

$replacement=<<<'BLADE'
  @if($editing)
  <div class="card form-section-card">
    <div class="form-section-head">
      <div><span class="section-icon">📝</span><div><h2>Modification Reason</h2><p>Job Card correction ka reason audit history ke liye mandatory hai.</p></div></div>
    </div>
    <div class="form-grid four-col">
      <label class="jc-full">Modification Reason *
        <textarea name="reason" rows="3" required maxlength="1000" placeholder="Why is this Job Card being corrected?">{{ old('reason') }}</textarea>
        <span class="field-help">Closed / Invoiced / Cancelled Job Card edit nahi hoga.</span>
      </label>
    </div>
  </div>
  @endif

  <div class="sticky-form-actions">
    <a class="btn" href="{{ $editing ? route('erp.job-cards.show',$job->id) : route('erp.job-cards.index') }}">Cancel</a>
    <button class="btn primary big-action">{{ $editing ? '💾 Save Job Card Changes' : '＋ Create Job Card' }}</button>
  </div>
BLADE;
$c=once($c,$anchor,$replacement,'modification reason');
file_put_contents($f,$c);
echo "PASS shared Job Card edit form consistency patched\n";
