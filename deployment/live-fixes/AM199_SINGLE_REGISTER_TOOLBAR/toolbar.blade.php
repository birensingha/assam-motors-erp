{{-- AM199_SINGLE_REGISTER_TOOLBAR: single slim search+totals+filters, NO redundant selected preview or bottom filter card --}}
<style id="AM199_SINGLE_REGISTER_TOOLBAR">
#am62-admin-job-cards .am199-toolbar{display:block!important;box-sizing:border-box!important;width:100%!important;padding:8px 10px!important;margin:7px 0 9px!important;background:#fff!important;border:1px solid #d0dfef!important;border-radius:8px!important}
#am62-admin-job-cards .am199-toolbar *{box-sizing:border-box!important}
#am62-admin-job-cards .am199-top{display:grid!important;grid-template-columns:minmax(265px,1fr) minmax(0,1.5fr)!important;gap:8px!important;align-items:center!important;margin:0 0 6px!important}
#am62-admin-job-cards .am199-search{display:flex!important;gap:6px!important;align-items:center!important;min-width:0!important;margin:0!important;padding:0!important}
#am62-admin-job-cards .am199-search input{min-width:0!important;max-width:none!important;width:100%!important;flex:1!important;height:32px!important;margin:0!important;padding:5px 9px!important;border:1px solid #c5d4e7!important;border-radius:6px!important;background:#fff!important;font-size:11px!important}
#am62-admin-job-cards .am199-search button{min-height:32px!important;height:32px!important;padding:5px 10px!important;font-size:10px!important;margin:0!important}
#am62-admin-job-cards .am199-totals{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:5px!important;min-width:0!important}
#am62-admin-job-cards .am199-total{display:flex!important;justify-content:space-between!important;align-items:center!important;gap:4px!important;min-width:0!important;min-height:32px!important;padding:5px 6px!important;margin:0!important;border-radius:6px!important;background:#f2f6fc!important;border:1px solid #e0eaf7!important;font-size:9px!important;font-weight:750!important;color:#526987!important;white-space:nowrap!important}
#am62-admin-job-cards .am199-total b{font-size:14px!important;font-weight:850!important;color:#10457f!important}
#am62-admin-job-cards .am199-filter{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr)) auto auto!important;gap:6px!important;align-items:center!important;min-width:0!important;width:100%!important;margin:0!important;padding:0!important}
#am62-admin-job-cards .am199-filter select{display:block!important;box-sizing:border-box!important;min-width:0!important;max-width:none!important;width:100%!important;min-height:32px!important;height:32px!important;margin:0!important;padding:4px 8px!important;background:#fff!important;color:#21456d!important;border:1px solid #c6d7ed!important;border-radius:6px!important;font-size:11px!important}
#am62-admin-job-cards .am199-filter button,#am62-admin-job-cards .am199-filter a{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:32px!important;height:32px!important;margin:0!important;padding:5px 10px!important;border-radius:6px!important;font-size:10px!important;white-space:nowrap!important;text-decoration:none!important}
#am62-admin-job-cards .am199-selected{display:flex!important;align-items:center!important;gap:8px!important;flex-wrap:wrap!important;margin:6px 0 0!important;padding:4px 0 0!important;border-top:1px solid #edf2f8!important;font-size:10px!important;color:#5a708c!important}
#am62-admin-job-cards #am199-picked-label{font-weight:800!important;color:#16487e!important}
#am62-admin-job-cards .am62-count{padding:3px 8px!important;font-size:10px!important}
@media(max-width:1050px){#am62-admin-job-cards .am199-top{grid-template-columns:1fr!important}}
@media(max-width:590px){#am62-admin-job-cards .am199-totals{grid-template-columns:repeat(3,minmax(0,1fr))!important}#am62-admin-job-cards .am199-filter{grid-template-columns:repeat(2,minmax(0,1fr))!important}#am62-admin-job-cards .am199-filter select{grid-column:1 / -1!important}#am62-admin-job-cards .am199-filter button,#am62-admin-job-cards .am199-filter a{width:100%!important}}
</style>
<div class="am199-toolbar" role="region" aria-label="Job Card Register search, totals and filters">
 <div class="am199-top">
  <form class="am199-search" method="get" action="{{ route('erp.job-cards.index') }}" id="am62-search-form">
   <input id="am62-search" name="q" value="{{ $filters['q'] }}" autocomplete="off" aria-label="Search Job Cards" placeholder="Search JC / Vehicle / Customer / Invoice">
   <button class="am62-white" type="submit">Search</button>
  </form>
  <div class="am199-totals">
   <div class="am199-total">TOTAL <b>{{ $summary['total'] }}</b></div>
   <div class="am199-total">OPEN <b>{{ $summary['open'] }}</b></div>
   <div class="am199-total">WIP <b>{{ $summary['in_progress'] }}</b></div>
   <div class="am199-total">DONE <b>{{ $summary['completed_closed'] }}</b></div>
   <div class="am199-total">INVOICED <b>{{ $summary['invoiced'] }}</b></div>
  </div>
 </div>
 <form class="am199-filter" method="get" action="{{ route('erp.job-cards.index') }}">
  <input type="hidden" name="q" value="{{ $filters['q'] }}">
  <select name="status" aria-label="Filter Job Card status">
   <option value="">All Status</option>
   @foreach(['open','in-progress','completed','closed','invoiced','cancelled'] as $s)
    <option value="{{ $s }}" @selected(strtolower($filters['status'])===$s)>{{ ucfirst($s) }}</option>
   @endforeach
  </select>
  <select name="service" aria-label="Filter service">
   <option value="">All Services</option>
   @foreach($services as $s)
    <option value="{{ $s }}" @selected($filters['service']===$s)>{{ $s }}</option>
   @endforeach
  </select>
  <select name="inspection" aria-label="Filter inspection">
   <option value="">All Inspection</option>
   @foreach($inspections as $s)
    <option value="{{ $s }}" @selected($filters['inspection']===$s)>{{ $s }}</option>
   @endforeach
  </select>
  <button class="am62-blue" type="submit">Filter</button>
  <a class="am62-white" href="{{ route('erp.job-cards.index') }}">Reset</a>
 </form>
 <div class="am199-selected" aria-live="polite">
  <strong id="am199-picked-label">Selected: {{ $am62Initial->job_no ?? 'No Job Card' }}</strong>
  <span class="am62-count" id="am62-count">{{ $rows->count() }} LOADED</span>
  <span>Row select karein, phir Open JC / Invoice / Return to JC</span>
 </div>
</div>
