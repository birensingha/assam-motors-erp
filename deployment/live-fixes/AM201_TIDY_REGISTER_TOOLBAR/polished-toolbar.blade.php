{{-- AM201_TIDY_REGISTER_TOOLBAR: polished single-row search/counters + equal filters + calm selected-record footer.
     Structural replacement for AM199 toolbar ONLY. All form actions, names, IDs and row-selection JS remain untouched. --}}
<style id="AM201_TIDY_REGISTER_TOOLBAR">
/* Scoped to Job Card Register; all values are defined here rather than relying on an
   additional competing override stylesheet. Old AM199 inline CSS is replaced. */
#am62-admin-job-cards .am201-toolbar {
 display:block!important;box-sizing:border-box!important;width:100%!important;
 padding:12px 14px 9px!important;margin:8px 0 12px!important;
 border:1px solid #d1deee!important;border-radius:11px!important;
 background:#fff!important;box-shadow:0 2px 7px rgba(14,53,94,.035)!important;
 color:#18365b!important;
}
#am62-admin-job-cards .am201-toolbar *,
#am62-admin-job-cards .am201-toolbar *::before,
#am62-admin-job-cards .am201-toolbar *::after{box-sizing:border-box!important}
#am62-admin-job-cards .am201-top {
 display:grid!important;
 grid-template-columns:minmax(0,2fr) minmax(0,3fr)!important;
 gap:12px!important;align-items:stretch!important;
 width:100%!important;margin:0 0 10px!important;padding:0!important;
}
#am62-admin-job-cards .am201-search {
 display:flex!important;align-items:stretch!important;
 gap:7px!important;min-width:0!important;max-width:none!important;
 margin:0!important;padding:0!important;width:100%!important;flex-wrap:nowrap!important;
}
#am62-admin-job-cards .am201-search input {
 display:block!important;flex:1 1 auto!important;min-width:0!important;max-width:none!important;width:100%!important;
 height:40px!important;min-height:40px!important;
 margin:0!important;padding:9px 12px!important;background:#fff!important;
 border:1px solid #c1d2e6!important;border-radius:7px!important;
 color:#1b385f!important;font-size:12px!important;line-height:1.3!important;
 outline-offset:2px!important;
}
#am62-admin-job-cards .am201-search input::placeholder {color:#70839b!important;opacity:1!important}
#am62-admin-job-cards .am201-search input:focus-visible,
#am62-admin-job-cards .am201-filters select:focus-visible {
 outline:2px solid #81ade4!important;outline-offset:1px!important;border-color:#3279bb!important;
}
#am62-admin-job-cards .am201-search button {
 display:inline-flex!important;align-items:center!important;justify-content:center!important;
 flex:0 0 76px!important;min-width:76px!important;max-width:76px!important;
 height:40px!important;min-height:40px!important;margin:0!important;padding:8px 12px!important;
 background:#f5f9ff!important;border:1px solid #aac4e0!important;border-radius:7px!important;
 color:#17457b!important;font-size:12px!important;font-weight:800!important;white-space:nowrap!important;
 cursor:pointer!important;
}
#am62-admin-job-cards .am201-search button:hover {background:#e9f2ff!important}
#am62-admin-job-cards .am201-totals {
 display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;
 align-items:stretch!important;min-width:0!important;width:100%!important;
 gap:7px!important;margin:0!important;padding:0!important;
}
#am62-admin-job-cards .am201-total {
 display:flex!important;flex-direction:row!important;align-items:center!important;
 justify-content:space-between!important;gap:7px!important;
 min-width:0!important;min-height:40px!important;height:40px!important;
 margin:0!important;padding:7px 10px!important;
 border-radius:7px!important;border:1px solid #e0eaf6!important;
 background:#f3f7fd!important;color:#526983!important;
 font-size:10px!important;font-weight:800!important;line-height:1.25!important;white-space:nowrap!important;
 letter-spacing:.012em!important;
}
#am62-admin-job-cards .am201-total b {
 display:inline-block!important;flex:0 0 auto!important;margin:0!important;
 color:#153f70!important;font-size:16px!important;font-weight:850!important;
 line-height:1!important;font-variant-numeric:tabular-nums!important;
}
#am62-admin-job-cards .am201-filters {
 display:grid!important;
 grid-template-columns:repeat(3,minmax(0,1fr)) 82px 70px!important;
 align-items:stretch!important;gap:8px!important;
 width:100%!important;min-width:0!important;margin:0!important;padding:0!important;
}
#am62-admin-job-cards .am201-filters input[type="hidden"]{display:none!important}
#am62-admin-job-cards .am201-filters select {
 display:block!important;width:100%!important;max-width:none!important;min-width:0!important;
 min-height:39px!important;height:39px!important;
 margin:0!important;padding:7px 11px!important;
 border:1px solid #c1d2e6!important;border-radius:7px!important;
 background:#fff!important;color:#22466e!important;font-size:12px!important;
 font-weight:550!important;line-height:1.25!important;cursor:pointer!important;
}
#am62-admin-job-cards .am201-filters .am201-filter-btn,
#am62-admin-job-cards .am201-filters .am201-reset-btn {
 display:inline-flex!important;align-items:center!important;justify-content:center!important;
 width:100%!important;min-width:0!important;max-width:none!important;
 min-height:39px!important;height:39px!important;
 margin:0!important;padding:7px 10px!important;border-radius:7px!important;
 font-size:11px!important;font-weight:800!important;line-height:1.2!important;
 text-decoration:none!important;white-space:nowrap!important;cursor:pointer!important;
}
#am62-admin-job-cards .am201-filters .am201-filter-btn {
 border:1px solid #1956a0!important;background:#1956a0!important;color:#fff!important;
}
#am62-admin-job-cards .am201-filters .am201-filter-btn:hover{background:#12457e!important}
#am62-admin-job-cards .am201-filters .am201-reset-btn {
 border:1px solid #c1d2e6!important;background:#fff!important;color:#21517f!important;
}
#am62-admin-job-cards .am201-filters .am201-reset-btn:hover{background:#eef5ff!important}
#am62-admin-job-cards .am201-selected {
 display:flex!important;align-items:center!important;gap:9px!important;
 width:100%!important;min-height:28px!important;
 padding:8px 0 0!important;margin:10px 0 0!important;
 border-top:1px solid #e8eff7!important;color:#607792!important;
 font-size:11px!important;line-height:1.35!important;
}
#am62-admin-job-cards #am199-picked-label {
 display:block!important;min-width:0!important;flex:1 1 auto!important;
 font-size:11px!important;font-weight:750!important;color:#274d78!important;
 overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;
}
#am62-admin-job-cards .am201-selected .am62-count {
 display:inline-flex!important;flex:0 0 auto!important;align-items:center!important;
 margin-left:auto!important;padding:5px 9px!important;min-height:24px!important;
 background:#eaf2fe!important;color:#23538b!important;border-radius:999px!important;
 font-size:10px!important;font-weight:800!important;white-space:nowrap!important;
}
#am62-admin-job-cards .am201-selected .am201-tip {
 display:inline-block!important;flex:0 0 auto!important;font-size:10px!important;
 color:#6d8299!important;white-space:nowrap!important;
}
@media(max-width:1180px) {
 #am62-admin-job-cards .am201-top {grid-template-columns:1fr!important;gap:8px!important}
 #am62-admin-job-cards .am201-selected .am201-tip {display:none!important}
}
@media(max-width:740px) {
 #am62-admin-job-cards .am201-toolbar {padding:10px!important}
 #am62-admin-job-cards .am201-filters {grid-template-columns:repeat(3,minmax(0,1fr))!important}
 #am62-admin-job-cards .am201-filters button {grid-column:1 / 2!important}
 #am62-admin-job-cards .am201-filters a {grid-column:2 / 3!important}
}
@media(max-width:580px) {
 #am62-admin-job-cards .am201-totals {grid-template-columns:repeat(2,minmax(0,1fr))!important}
 #am62-admin-job-cards .am201-filters {grid-template-columns:repeat(2,minmax(0,1fr))!important}
 #am62-admin-job-cards .am201-filters select {grid-column:1 / -1!important}
 #am62-admin-job-cards .am201-filters button {grid-column:1 / 2!important}
 #am62-admin-job-cards .am201-filters a {grid-column:2 / 3!important}
 #am62-admin-job-cards .am201-total {height:37px!important;min-height:37px!important}
}
</style>
<div class="am199-toolbar am201-toolbar" role="region" aria-label="Job Card Register search, totals and filters">
 <div class="am201-top">
  <form class="am201-search" method="get" action="{{ route('erp.job-cards.index') }}" id="am62-search-form">
   <input id="am62-search" name="q" value="{{ $filters['q'] }}" autocomplete="off" aria-label="Search Job Cards" placeholder="Search Job Card No / Customer / Vehicle / Invoice">
   <button class="am62-white" type="submit">Search</button>
  </form>
  <div class="am201-totals">
   <div class="am201-total">TOTAL JC <b>{{ $summary['total'] }}</b></div>
   <div class="am201-total">OPEN <b>{{ $summary['open'] }}</b></div>
   <div class="am201-total">WIP <b>{{ $summary['in_progress'] }}</b></div>
   <div class="am201-total">COMPLETED <b>{{ $summary['completed_closed'] }}</b></div>
   <div class="am201-total">INVOICED <b>{{ $summary['invoiced'] }}</b></div>
  </div>
 </div>
 <form class="am201-filters" method="get" action="{{ route('erp.job-cards.index') }}">
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
  <button class="am62-blue am201-filter-btn" type="submit">Apply</button>
  <a class="am62-white am201-reset-btn" href="{{ route('erp.job-cards.index') }}">Reset</a>
 </form>
 <div class="am201-selected" aria-live="polite">
  <strong id="am199-picked-label">Selected: {{ $am62Initial->job_no ?? 'No Job Card' }}</strong>
  <span class="am201-tip">Open JC / Invoice / Return to JC from the selected row</span>
  <span class="am62-count" id="am62-count">{{ $rows->count() }} LOADED</span>
 </div>
</div>
