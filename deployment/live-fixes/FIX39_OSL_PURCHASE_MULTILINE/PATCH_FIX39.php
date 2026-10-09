<?php
declare(strict_types=1);

$root=rtrim((string)($argv[1] ?? ''),'/');
if($root==='' || !is_file($root.'/artisan')){
    fwrite(STDERR,"Usage: php PATCH_FIX39.php /path/to/laravel/app\n");
    exit(2);
}

$view=$root.'/resources/views/purchases/osl-create.blade.php';
if(!is_file($view)) throw new RuntimeException('Missing OSL create view');

$c=file_get_contents($view);
$marker='FIX39_OSL_PARTS_STYLE';
if(str_contains($c,$marker)){
    echo "FIX39 already installed\n";
    exit(0);
}

function replaceOnce(string $src,string $old,string $new,string $id): string {
    $count=substr_count($src,$old);
    if($count!==1) throw new RuntimeException($id.' anchor count='.$count);
    return str_replace($old,$new,$src);
}

$cssAnchor='@media(max-width:1000px){.osl-bottom{grid-template-columns:1fr}}';
$css=<<<'CSS'
/* FIX39_OSL_PARTS_STYLE */
.am-view-tools{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.am-view-tools .am-view-label{font-size:.78rem;font-weight:800;color:#60748a;text-transform:uppercase;letter-spacing:.04em;margin-right:2px}
.am-view-tools .am-view-btn{min-width:48px;padding:.45rem .6rem;border:1px solid #c7d5e4;border-radius:8px;background:#fff;color:#29445f;font-weight:800;cursor:pointer}
.am-view-tools .am-view-btn.active{background:#123b6d;color:#fff;border-color:#123b6d;box-shadow:0 3px 10px rgba(18,59,109,.18)}
#osl-form.am-readable-100{font-size:1rem}
#osl-form.am-readable-115{font-size:1.075rem}
#osl-form.am-readable-125{font-size:1.14rem}
#osl-form.am-readable-115 input,#osl-form.am-readable-115 select,#osl-form.am-readable-115 textarea{font-size:1rem;min-height:42px}
#osl-form.am-readable-125 input,#osl-form.am-readable-125 select,#osl-form.am-readable-125 textarea{font-size:1.055rem;min-height:46px}
#osl-form.am-readable-115 .line-table th{padding-top:12px!important;padding-bottom:12px!important}
#osl-form.am-readable-125 .line-table th{padding-top:14px!important;padding-bottom:14px!important}
.osl-form .line-scroll{max-height:none!important;overflow-x:auto!important;overflow-y:visible!important}
.osl-form .line-table{min-width:1420px}
.osl-form .line-table th{background:#eaf4ff;color:#173f68}
.osl-form .osl-toolbar{padding:10px 12px;border-radius:11px;background:#f7fbff;border:1px solid #d5e5f3}
.osl-form #add-osl-line{background:#f59e0b!important;border-color:#f59e0b!important;color:#fff!important}
.osl-form .osl-save-btn{background:#16803a!important;border-color:#16803a!important;color:#fff!important}
.osl-form .remove-line{background:#fff1f0!important;border:1px solid #efb2ad!important;color:#b42318!important;font-weight:900}
.osl-form .job-filter-wrap{display:grid;gap:5px}
.osl-form .job-filter{width:100%;min-width:0;box-sizing:border-box;font-weight:700;background:#fffdf2;border-color:#e7cf8b}
.osl-form .job-filter:focus{outline:none;border-color:#d79a19;box-shadow:0 0 0 3px rgba(215,154,25,.12)}
.osl-form .job-card{width:100%;min-width:0}
.osl-form .job-filter.invalid{border-color:#d93632!important;box-shadow:0 0 0 3px rgba(217,54,50,.10)!important}
.osl-form .osl-master{background:#f5f9ff}
.osl-form .line-total{color:#0d3b70}.osl-form .customer-total{color:#247238}
@media(max-width:1000px){
  .osl-form .line-table{min-width:1280px}
  .am-view-tools{justify-content:flex-start}
}
@media print{.am-view-tools{display:none!important}#osl-form.am-readable-115,#osl-form.am-readable-125{font-size:1rem!important}}
CSS;
$c=replaceOnce($c,$cssAnchor,$css."\n".$cssAnchor,'css');

$headOld=<<<'HTML'
<div class="page-head">
  <div><h1>OSL Purchase / Outside Labour</h1><p>Parts Purchase jaisa multi-line supplier bill entry. Har OSL line apne Job Card / Vehicle se linked rahega.</p></div>
  <a class="btn ghost" href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
</div>
HTML;
$headNew=<<<'HTML'
<div class="page-head">
  <div><h1>OSL Purchase / Outside Labour</h1><p>Parts Purchase jaisa multi-line supplier bill entry. Har OSL line apne Job Card / Vehicle se linked rahega.</p></div>
  <div class="am-view-tools" data-am-view-tools="osl">
    <span class="am-view-label">View</span>
    <button type="button" class="am-view-btn" data-am-scale="100">100%</button>
    <button type="button" class="am-view-btn" data-am-scale="115">115%</button>
    <button type="button" class="am-view-btn" data-am-scale="125">125%</button>
    <a class="btn ghost" href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
  </div>
</div>
HTML;
$c=replaceOnce($c,$headOld,$headNew,'page head');

$jobOld=<<<'HTML'
  <td><select name="lines[__INDEX__][job_card_id]" class="job-card" required><option value="">Select JC / Vehicle</option>@foreach($jobs as $j)<option value="{{ $j['id'] }}">{{ $j['job_no'] }} — {{ $j['vehicle_reg_no'] }} — {{ $j['customer_name'] }}</option>@endforeach</select></td>
HTML;
$jobNew=<<<'HTML'
  <td><div class="job-filter-wrap"><input type="search" class="job-filter" autocomplete="off" placeholder="Search JC / vehicle / customer..."><select name="lines[__INDEX__][job_card_id]" class="job-card" required><option value="">Select JC / Vehicle</option>@foreach($jobs as $j)<option value="{{ $j['id'] }}">{{ $j['job_no'] }} — {{ $j['vehicle_reg_no'] }} — {{ $j['customer_name'] }}</option>@endforeach</select></div></td>
HTML;
$c=replaceOnce($c,$jobOld,$jobNew,'job filter cell');

$jsAnchor=<<<'JS'
  if (!body || !template || !add || !form) return;

  let serial = 0;
JS;
$jsInsert=<<<'JS'
  if (!body || !template || !add || !form) return;

  const viewTools = document.querySelector('[data-am-view-tools="osl"]');
  const scaleKey = 'am_erp_osl_view_scale';
  const allowedScales = ['100','115','125'];
  const applyScale = scale => {
    scale=String(scale||'115');
    if(!allowedScales.includes(scale)) scale='115';
    form.classList.remove('am-readable-100','am-readable-115','am-readable-125');
    form.classList.add('am-readable-'+scale);
    viewTools?.querySelectorAll('[data-am-scale]').forEach(b=>b.classList.toggle('active',b.dataset.amScale===scale));
    try{ localStorage.setItem(scaleKey,scale); }catch(_){}
  };
  let initialScale='115';
  try{ initialScale=localStorage.getItem(scaleKey)||'115'; }catch(_){}
  applyScale(initialScale);
  viewTools?.querySelectorAll('[data-am-scale]').forEach(b=>b.addEventListener('click',()=>applyScale(b.dataset.amScale)));

  let serial = 0;
JS;
$c=replaceOnce($c,$jsAnchor,$jsInsert,'view scale js');

$appendAnchor=<<<'JS'
    const row = holder.firstElementChild;
    body.appendChild(row);

    const set = (cls,val) => { const el=row.querySelector(cls); if(el && val!==undefined && val!==null) el.value=String(val); };
JS;
$appendInsert=<<<'JS'
    const row = holder.firstElementChild;
    body.appendChild(row);

    const jobFilter=row.querySelector('.job-filter');
    const jobSelect=row.querySelector('.job-card');
    if(jobFilter && jobSelect){
      const options=[...jobSelect.options].map((o,i)=>({o,i,text:String(o.textContent||'').toUpperCase()}));
      const syncSelectedLabel=()=>{
        const opt=jobSelect.options[jobSelect.selectedIndex];
        if(opt && opt.value) jobFilter.value=String(opt.textContent||'').trim();
      };
      const filterJobs=()=>{
        const q=jobFilter.value.trim().toUpperCase();
        options.forEach(x=>{ x.o.hidden=x.i>0 && q!=='' && !x.text.includes(q); });
        jobFilter.classList.remove('invalid');
      };
      jobFilter.addEventListener('input',filterJobs);
      jobFilter.addEventListener('keydown',e=>{
        if(e.key!=='Enter') return;
        const visible=options.filter(x=>x.i>0 && !x.o.hidden);
        if(visible.length===1){
          e.preventDefault();
          jobSelect.value=visible[0].o.value;
          syncSelectedLabel();
          jobSelect.dispatchEvent(new Event('change',{bubbles:true}));
        }
      });
      jobSelect.addEventListener('change',syncSelectedLabel);
    }

    const set = (cls,val) => { const el=row.querySelector(cls); if(el && val!==undefined && val!==null) el.value=String(val); };
JS;
$c=replaceOnce($c,$appendAnchor,$appendInsert,'job filter js');

$setAnchor=<<<'JS'
    set('.job-card', data.job_card_id ?? (body.children.length===1 && presetJobId>0 ? presetJobId : ''));
    set('.osl-master', data.osl_master_id ?? ''); set('.description', data.description ?? '');
JS;
$setNew=<<<'JS'
    set('.job-card', data.job_card_id ?? (body.children.length===1 && presetJobId>0 ? presetJobId : ''));
    if(jobSelect?.value){
      const opt=jobSelect.options[jobSelect.selectedIndex];
      if(opt && opt.value) jobFilter.value=String(opt.textContent||'').trim();
    }
    set('.osl-master', data.osl_master_id ?? ''); set('.description', data.description ?? '');
JS;
$c=replaceOnce($c,$setAnchor,$setNew,'preset job label');

$resetOld=<<<'JS'
      if(body.children.length<=1){ row.querySelectorAll('input').forEach(i=>{ if(i.classList.contains('qty')) i.value='1'; else i.value='0'; }); row.querySelector('.description').value=''; row.querySelector('.job-card').value=''; row.querySelector('.osl-master').value=''; }
JS;
$resetNew=<<<'JS'
      if(body.children.length<=1){ row.querySelectorAll('input').forEach(i=>{ if(i.classList.contains('qty')) i.value='1'; else if(i.classList.contains('job-filter') || i.classList.contains('description')) i.value=''; else i.value='0'; }); row.querySelector('.description').value=''; row.querySelector('.job-card').value=''; row.querySelector('.osl-master').value=''; }
JS;
$c=replaceOnce($c,$resetOld,$resetNew,'single-row reset');

$submitOld=<<<'JS'
    const bad=rows.find(row => !row.querySelector('.job-card').value || (!row.querySelector('.osl-master').value && !row.querySelector('.description').value.trim()) || !(num(row,'.qty')>0) || !(num(row,'.customer-rate')>0));
    if(bad){ e.preventDefault(); bad.scrollIntoView({behavior:'smooth',block:'center'}); alert('Har OSL line me Job Card / Vehicle, OSL Work/Description, Qty aur Customer Rate zaroori hai.'); }
JS;
$submitNew=<<<'JS'
    const bad=rows.find(row => !row.querySelector('.job-card').value || (!row.querySelector('.osl-master').value && !row.querySelector('.description').value.trim()) || !(num(row,'.qty')>0) || !(num(row,'.customer-rate')>0));
    if(bad){
      e.preventDefault();
      if(!bad.querySelector('.job-card').value) bad.querySelector('.job-filter')?.classList.add('invalid');
      bad.scrollIntoView({behavior:'smooth',block:'center'});
      alert('Har OSL line me Job Card / Vehicle, OSL Work/Description, Qty aur Customer Rate zaroori hai.');
    }
JS;
$c=replaceOnce($c,$submitOld,$submitNew,'submit validation');

file_put_contents($view,$c);
echo "PASS OSL Parts-style search/readability UI patched\n";
