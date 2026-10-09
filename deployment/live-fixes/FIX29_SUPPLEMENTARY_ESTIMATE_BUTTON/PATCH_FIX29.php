<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX29.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');
$path="$root/resources/views/job-cards/show.blade.php";
$c=file_get_contents($path);
$marker="FIX29_SUPPLEMENTARY_ESTIMATE_SHORTCUT";
if (str_contains($c,$marker)) { echo "FIX29 already installed.\n"; exit(0); }

$needle="@section('content')";
if (substr_count($c,$needle)!==1) throw new RuntimeException("Job Card content section marker mismatch");

$block=<<<'BLADE'
@section('content')
{{-- FIX29_SUPPLEMENTARY_ESTIMATE_SHORTCUT --}}
<div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin:0 0 12px 0">
  <a class="btn primary" href="{{ route('erp.estimates.create', request()->route('job')) }}">🧾 Estimate / Supplementary Estimate</a>
</div>
BLADE;

$c=str_replace($needle,$block,$c);
file_put_contents($path,$c);
echo "FIX29 patch applied.\n";
