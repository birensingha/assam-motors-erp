<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX27.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');

function replaceOnce(string $path,string $old,string $new,string $label): void {
    $c=file_get_contents($path);
    if (str_contains($c,$new)) return;
    $count=substr_count($c,$old);
    if ($count!==1) throw new RuntimeException($label." marker count=".$count);
    file_put_contents($path,str_replace($old,$new,$c));
}

$purchase="$root/resources/views/purchases/create.blade.php";
$estimate="$root/resources/views/estimates/create.blade.php";

$purchaseCss=<<<'CSS'

/* FIX27_READABILITY */
.am-view-tools{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.am-view-tools .am-view-label{font-size:.78rem;font-weight:800;color:#60748a;text-transform:uppercase;letter-spacing:.04em;margin-right:2px}
.am-view-tools .am-view-btn{min-width:48px;padding:.45rem .6rem;border:1px solid #c7d5e4;border-radius:8px;background:#fff;color:#29445f;font-weight:800;cursor:pointer}
.am-view-tools .am-view-btn.active{background:#123b6d;color:#fff;border-color:#123b6d;box-shadow:0 3px 10px rgba(18,59,109,.18)}
#purchase-form.am-readable-100{font-size:1rem}
#purchase-form.am-readable-115{font-size:1.075rem}
#purchase-form.am-readable-125{font-size:1.14rem}
#purchase-form.am-readable-115 input,#purchase-form.am-readable-115 select,#purchase-form.am-readable-115 textarea{font-size:1rem;min-height:41px}
#purchase-form.am-readable-125 input,#purchase-form.am-readable-125 select,#purchase-form.am-readable-125 textarea{font-size:1.055rem;min-height:45px}
#purchase-form.am-readable-115 .line-table thead th{padding-top:12px!important;padding-bottom:12px!important}
#purchase-form.am-readable-125 .line-table thead th{padding-top:14px!important;padding-bottom:14px!important}
#purchase-form.am-readable-115 .line-table tbody td{padding-top:7px!important;padding-bottom:7px!important}
#purchase-form.am-readable-125 .line-table tbody td{padding-top:9px!important;padding-bottom:9px!important}
#purchase-form.am-readable-115 .part-option{min-height:59px}
#purchase-form.am-readable-125 .part-option{min-height:64px;font-size:1.02rem}
@media print{#purchase-form.am-readable-115,#purchase-form.am-readable-125{font-size:1rem!important} .am-view-tools{display:none!important}}
CSS;

replaceOnce($purchase,"\n</style>\n@endpush",$purchaseCss."\n</style>\n@endpush","purchase CSS");

$purchaseHeadOld=<<<'OLD'
<div class="page-head">
    <div><h1>Parts Purchase Entry</h1><p>Fast keyboard entry for multiple parts in one supplier bill.</p></div>
    <a class="btn ghost" href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
</div>
OLD;
$purchaseHeadNew=<<<'NEW'
<div class="page-head">
    <div><h1>Parts Purchase Entry</h1><p>Fast keyboard entry for multiple parts in one supplier bill.</p></div>
    <div class="am-view-tools" data-am-view-tools="purchase">
        <span class="am-view-label">View</span>
        <button type="button" class="am-view-btn" data-am-scale="100">100%</button>
        <button type="button" class="am-view-btn" data-am-scale="115">115%</button>
        <button type="button" class="am-view-btn" data-am-scale="125">125%</button>
        <a class="btn ghost" href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
    </div>
</div>
NEW;
replaceOnce($purchase,$purchaseHeadOld,$purchaseHeadNew,"purchase header");

$purchaseScript=<<<'SCRIPT'

<script>
document.addEventListener('DOMContentLoaded',function(){
    const form=document.getElementById('purchase-form');
    const tools=document.querySelector('[data-am-view-tools="purchase"]');
    if(!form||!tools) return;
    const key='am_erp_purchase_view_scale';
    const allowed=['100','115','125'];
    const apply=(scale)=>{
        if(!allowed.includes(String(scale))) scale='115';
        form.classList.remove('am-readable-100','am-readable-115','am-readable-125');
        form.classList.add('am-readable-'+scale);
        tools.querySelectorAll('[data-am-scale]').forEach(b=>b.classList.toggle('active',b.dataset.amScale===scale));
        try{localStorage.setItem(key,scale);}catch(_){}
    };
    let initial='115';
    try{initial=localStorage.getItem(key)||'115';}catch(_){}
    apply(initial);
    tools.querySelectorAll('[data-am-scale]').forEach(b=>b.addEventListener('click',()=>apply(b.dataset.amScale)));
});
</script>
SCRIPT;
replaceOnce($purchase,"\n@endsection\n@push('scripts')",$purchaseScript."\n@endsection\n@push('scripts')","purchase view script");

$estimateHeadPush=<<<'NEW'
@push('head')
<style>
/* FIX27_READABILITY */
.am-view-tools{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.am-view-tools .am-view-label{font-size:.78rem;font-weight:800;color:#60748a;text-transform:uppercase;letter-spacing:.04em;margin-right:2px}
.am-view-tools .am-view-btn{min-width:48px;padding:.45rem .6rem;border:1px solid #c7d5e4;border-radius:8px;background:#fff;color:#29445f;font-weight:800;cursor:pointer}
.am-view-tools .am-view-btn.active{background:#123b6d;color:#fff;border-color:#123b6d;box-shadow:0 3px 10px rgba(18,59,109,.18)}
#estimate-form.am-readable-100{font-size:1rem}
#estimate-form.am-readable-115{font-size:1.075rem}
#estimate-form.am-readable-125{font-size:1.14rem}
#estimate-form.am-readable-115 input,#estimate-form.am-readable-115 select,#estimate-form.am-readable-115 textarea{font-size:1rem;min-height:41px}
#estimate-form.am-readable-125 input,#estimate-form.am-readable-125 select,#estimate-form.am-readable-125 textarea{font-size:1.055rem;min-height:45px}
#estimate-form.am-readable-115 .line-table th,#estimate-form.am-readable-115 .line-table td{padding-top:9px;padding-bottom:9px}
#estimate-form.am-readable-125 .line-table th,#estimate-form.am-readable-125 .line-table td{padding-top:11px;padding-bottom:11px}
#estimate-form.am-readable-115 .line-scroll{min-height:330px}
#estimate-form.am-readable-125 .line-scroll{min-height:390px}
@media print{#estimate-form.am-readable-115,#estimate-form.am-readable-125{font-size:1rem!important}.am-view-tools{display:none!important}}
</style>
@endpush

NEW;
replaceOnce($estimate,"@section('title','Estimate — '.$jobCard->job_no)\n@section('content')","@section('title','Estimate — '.$jobCard->job_no)\n".$estimateHeadPush."@section('content')","estimate head CSS");

$estimateHeadOld=<<<'OLD'
<div class="page-head">
  <div><div class="breadcrumbs"><a href="{{ route('erp.job-cards.show',$jobCard->id) }}">{{ $jobCard->job_no }}</a> / Estimate</div><h1>{{ $estimate ? 'Edit Draft' : ($latest && $latest->converted_to_job_at ? 'Create Supplementary Estimate' : ($latest ? 'Create Revision' : 'Create Estimate')) }}</h1><p>{{ $jobCard->customer_name }} · {{ $jobCard->vehicle_reg_no }} · Diagnosis: {{ $jobCard->final_diagnosis ?: '—' }}</p></div>
  @if($latest)<a class="btn" href="{{ route('erp.estimates.show',$latest->id) }}">View Latest</a>@endif
</div>
OLD;
$estimateHeadNew=<<<'NEW'
<div class="page-head">
  <div><div class="breadcrumbs"><a href="{{ route('erp.job-cards.show',$jobCard->id) }}">{{ $jobCard->job_no }}</a> / Estimate</div><h1>{{ $estimate ? 'Edit Draft' : ($latest && $latest->converted_to_job_at ? 'Create Supplementary Estimate' : ($latest ? 'Create Revision' : 'Create Estimate')) }}</h1><p>{{ $jobCard->customer_name }} · {{ $jobCard->vehicle_reg_no }} · Diagnosis: {{ $jobCard->final_diagnosis ?: '—' }}</p></div>
  <div class="am-view-tools" data-am-view-tools="estimate">
    <span class="am-view-label">View</span>
    <button type="button" class="am-view-btn" data-am-scale="100">100%</button>
    <button type="button" class="am-view-btn" data-am-scale="115">115%</button>
    <button type="button" class="am-view-btn" data-am-scale="125">125%</button>
    @if($latest)<a class="btn" href="{{ route('erp.estimates.show',$latest->id) }}">View Latest</a>@endif
  </div>
</div>
NEW;
replaceOnce($estimate,$estimateHeadOld,$estimateHeadNew,"estimate header");

$estimateScript=<<<'SCRIPT'

<script>
document.addEventListener('DOMContentLoaded',function(){
    const form=document.getElementById('estimate-form');
    const tools=document.querySelector('[data-am-view-tools="estimate"]');
    if(!form||!tools) return;
    const key='am_erp_estimate_view_scale';
    const allowed=['100','115','125'];
    const apply=(scale)=>{
        if(!allowed.includes(String(scale))) scale='115';
        form.classList.remove('am-readable-100','am-readable-115','am-readable-125');
        form.classList.add('am-readable-'+scale);
        tools.querySelectorAll('[data-am-scale]').forEach(b=>b.classList.toggle('active',b.dataset.amScale===scale));
        try{localStorage.setItem(key,scale);}catch(_){}
    };
    let initial='115';
    try{initial=localStorage.getItem(key)||'115';}catch(_){}
    apply(initial);
    tools.querySelectorAll('[data-am-scale]').forEach(b=>b.addEventListener('click',()=>apply(b.dataset.amScale)));
});
</script>
SCRIPT;
replaceOnce($estimate,"\n@if($amEstimateWip)\n<script>",$estimateScript."\n@if($amEstimateWip)\n<script>","estimate view script");

echo "FIX27 patch applied.\n";
