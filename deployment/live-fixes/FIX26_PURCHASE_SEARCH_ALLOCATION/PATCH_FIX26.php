<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX26.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');
function rw(string $path,string $old,string $new,string $label): void {
  $c=file_get_contents($path);
  if (str_contains($c,$new)) return;
  $n=substr_count($c,$old);
  if ($n!==1) throw new RuntimeException("$label marker count=$n");
  file_put_contents($path,str_replace($old,$new,$c));
}

// Part Name/Code search: tolerate spaces, dashes, slash and dots.
$p="$root/app/Http/Controllers/Web/AdminPurchasePageController.php";
$old=<<<'OLD'
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('part_name','like',$like)
                    ->orWhere('part_code','like',$like)
                    ->orWhere('hsn_code','like',$like)
                    ->orWhere('category','like',$like);
            });
        }
OLD;
$new=<<<'NEW'
        if ($q !== '') {
            $like = '%'.$q.'%';
            $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($q)) ?: '';
            $normLike = '%'.$normalized.'%';
            $query->where(function ($w) use ($like, $normalized, $normLike) {
                $w->where('part_name','like',$like)
                    ->orWhere('part_code','like',$like)
                    ->orWhere('hsn_code','like',$like)
                    ->orWhere('category','like',$like);
                if ($normalized !== '') {
                    $norm = static fn(string $col): string => "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(COALESCE({$col},'')),' ',''),'-',''),'/',''),'.','')";
                    $w->orWhereRaw($norm('part_code').' LIKE ?',[$normLike])
                      ->orWhereRaw($norm('part_name').' LIKE ?',[$normLike]);
                }
            });
        }
NEW;
rw($p,$old,$new,'partSearch');

// Job/Vehicle search normalization in existing service source.
$p="$root/app/Services/PurchaseService.php";
$old=<<<'OLD'
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($w) use ($like) {
                $w->where('job_no', 'like', $like)
                    ->orWhere('vehicle_reg_no', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhereRaw("REPLACE(UPPER(COALESCE(vehicle_reg_no,'')),' ','') LIKE REPLACE(UPPER(?),' ','')", [$like]);
            });
        }
OLD;
$new=<<<'NEW'
        if ($search !== '') {
            $like = '%'.$search.'%';
            $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($search)) ?: '';
            $normLike = '%'.$normalized.'%';
            $query->where(function ($w) use ($like, $normalized, $normLike) {
                $w->where('job_no', 'like', $like)
                    ->orWhere('vehicle_reg_no', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like);
                if ($normalized !== '') {
                    $norm = static fn(string $col): string => "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(COALESCE({$col},'')),' ',''),'-',''),'/',''),'.','')";
                    $w->orWhereRaw($norm('job_no').' LIKE ?',[$normLike])
                      ->orWhereRaw($norm('vehicle_reg_no').' LIKE ?',[$normLike]);
                }
            });
        }
NEW;
rw($p,$old,$new,'searchJobs');

// Purchase row: add a searchable text box above existing select.
$p="$root/resources/views/purchases/create.blade.php";
$old=<<<'OLD'
<td><select name="lines[__INDEX__][job_card_id]" class="job-select" @disabled($presetJobId<=0)><option value="">Select JC / Vehicle</option>@foreach($jobs as $j)<option value="{{ $j['id'] }}" @selected($presetJobId===(int)$j['id'])>{{ $j['job_no'] }} — {{ $j['vehicle_reg_no'] }} — {{ $j['customer_name'] }}</option>@endforeach</select></td>
OLD;
$new=<<<'NEW'
<td><div class="job-filter-wrap"><input type="search" class="job-filter" autocomplete="off" placeholder="Search JC / vehicle / customer..." @disabled($presetJobId<=0)><select name="lines[__INDEX__][job_card_id]" class="job-select" @disabled($presetJobId<=0)><option value="">Select JC / Vehicle</option>@foreach($jobs as $j)<option value="{{ $j['id'] }}" @selected($presetJobId===(int)$j['id'])>{{ $j['job_no'] }} — {{ $j['vehicle_reg_no'] }} — {{ $j['customer_name'] }}</option>@endforeach</select></div></td>
NEW;
rw($p,$old,$new,'job filter cell');
rw($p,"/* PURCHASE BOTTOM */",".job-filter-wrap{display:grid;gap:5px}.job-filter{width:100%!important;min-width:0!important;box-sizing:border-box!important;font-weight:600}.job-filter.invalid{border-color:#dc3545!important;box-shadow:0 0 0 3px rgba(220,53,69,.12)!important}\n\n/* PURCHASE BOTTOM */",'job filter css');
rw($p,"{{ asset('js/purchase-entry.js') }}?v=24","{{ asset('js/purchase-entry.js') }}?v=26",'asset version');

// JS: filter the line's complete Job Card option set by normalized search text.
$p="$root/public/js/purchase-entry.js";
$anchor=<<<'OLD'
  function wire(row) {
OLD;
$block=<<<'NEW'
  function normalizeLookup(value) {
    return String(value ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  }

  function wireJobFilter(row) {
    const filter = row.querySelector('.job-filter');
    const select = row.querySelector('.job-select');
    if (!filter || !select) return;
    const source = [...select.options].map(o => ({value:o.value, text:o.textContent || ''}));
    const selected = select.value;
    if (selected) {
      const opt = source.find(o => o.value === selected);
      if (opt) filter.value = opt.text;
    }
    const rebuild = () => {
      const qRaw = filter.value.trim();
      const q = normalizeLookup(qRaw);
      const prior = select.value;
      select.replaceChildren();
      source.forEach((o, idx) => {
        if (idx !== 0 && q && !normalizeLookup(o.text).includes(q)) return;
        const el = document.createElement('option');
        el.value = o.value; el.textContent = o.text;
        select.appendChild(el);
      });
      if (prior && [...select.options].some(o => o.value === prior)) select.value = prior;
      if (q && select.options.length === 2 && !select.value) select.selectedIndex = 1;
    };
    filter.addEventListener('input', () => { filter.classList.remove('invalid'); rebuild(); });
    filter.addEventListener('focus', rebuild);
    filter.addEventListener('keydown', e => {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      if (!select.value && select.options.length > 1) select.selectedIndex = 1;
      if (select.value) finishRow(row); else filter.classList.add('invalid');
    });
    select.addEventListener('change', () => {
      const opt = select.options[select.selectedIndex];
      if (select.value && opt) filter.value = opt.textContent || '';
    });
  }

  function wire(row) {
NEW;
rw($p,$anchor,$block,'wireJobFilter block');
$old=<<<'OLD'
        const job = row.querySelector('.job-select');
        const needsJob = e.target.value === 'JOB';
        job.disabled = !needsJob;
        if (!needsJob) job.value = '';
OLD;
$new=<<<'NEW'
        const job = row.querySelector('.job-select');
        const filter = row.querySelector('.job-filter');
        const needsJob = e.target.value === 'JOB';
        job.disabled = !needsJob;
        filter.disabled = !needsJob;
        if (!needsJob) { job.value = ''; filter.value = ''; }
        else window.setTimeout(() => filter.focus(), 0);
NEW;
rw($p,$old,$new,'allocation filter enable');
rw($p,"    wirePartSearch(row);\n    wireEnterFlow(row);","    wirePartSearch(row);\n    wireJobFilter(row);\n    wireEnterFlow(row);",'wire call');
rw($p,"      if (allocation.value === 'JOB') job.focus(); else finishRow(row);","      if (allocation.value === 'JOB') row.querySelector('.job-filter').focus(); else finishRow(row);",'enter flow');
rw($p,"        row.querySelector('.job-select').disabled = true; closeResults(row);","        row.querySelector('.job-select').disabled = true; const jf=row.querySelector('.job-filter'); jf.value=''; jf.disabled=true; closeResults(row);",'reset filter');
$old=<<<'OLD'
    if (invalid) {
      e.preventDefault(); const search = invalid.querySelector('.part-search');
      search.classList.add('invalid'); search.focus(); renderResults(invalid, search.value); return;
    }
    if (!entered.length) {
OLD;
$new=<<<'NEW'
    if (invalid) {
      e.preventDefault(); const search = invalid.querySelector('.part-search');
      search.classList.add('invalid'); search.focus(); renderResults(invalid, search.value); return;
    }
    const missingJob = entered.find(row => row.querySelector('.allocation').value === 'JOB' && !row.querySelector('.job-select').value);
    if (missingJob) {
      e.preventDefault(); const filter = missingJob.querySelector('.job-filter'); filter.classList.add('invalid'); filter.focus(); return;
    }
    if (!entered.length) {
NEW;
rw($p,$old,$new,'submit job validation');
file_put_contents($p,file_get_contents($p));
echo "FIX26 patch applied.\n";