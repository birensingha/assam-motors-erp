@extends('layouts.erp')
@section('title','Date Range Attendance Report — Assam Motors')
@section('topbar_title','Staff Attendance | From–To Report')
@section('content')
<style>
.amrange{color:#20314a;padding:4px 0 24px}.amrange *{box-sizing:border-box}
.amrange h1{font-size:23px;margin:0 0 4px;font-weight:850}.amrange h2{font-size:17px;margin:0 0 10px}
.amrange p{margin:3px 0 12px;color:#66758a}
.amrange .panel{background:var(--card,#fff);border:1px solid #d9e2ee;border-radius:12px;padding:16px;margin:12px 0;box-shadow:0 2px 8px #0b29510a}
.amrange .tools{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.amrange label{display:grid;gap:5px;font-size:12px;font-weight:750;min-width:144px}
.amrange input,.amrange select{border:1px solid #b9c9dc;border-radius:7px;background:#fff;color:#1b334f;min-height:38px;padding:8px;font-size:13px}
.amrange a.action,.amrange button.action{display:inline-flex;align-items:center;justify-content:center;min-height:38px;background:#164e89;border-radius:7px;padding:8px 13px;color:white;border:0;text-decoration:none;font-size:13px;font-weight:750;cursor:pointer}
.amrange a.secondary,.amrange button.secondary{background:#eef3f9;color:#1d4469;border:1px solid #bacbdb}
.amrange .metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px}
.amrange .metric{background:#f4f8fe;padding:12px;border-radius:9px;border:1px solid #dfeafa}
.amrange .metric strong{display:block;font-size:20px;font-weight:850}.amrange .metric span{font-size:11px;color:#516780}
.amrange .table-scroll{overflow:auto;max-height:70vh;border:1px solid #e0e6ed;border-radius:8px}
.amrange table{border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap}
.amrange th,.amrange td{padding:8px 11px;border-bottom:1px solid #e6ecf4;text-align:left}
.amrange th{position:sticky;top:0;background:#eaf1fb;color:#1e3c63;z-index:2}
.amrange tr:nth-child(even) td{background:#f9fbfe}
.amrange .review{color:#a53320;font-weight:bold}
.amrange .tip{font-size:12px;color:#677c94;margin-top:7px}
.amrange .salary-note{border-left:3px solid #cb8e36;padding:9px 12px;background:#fff8e9;font-size:12px}
.amrange .bad{padding:10px;margin:10px 0;background:#fff2ee;color:#ab3323;border-radius:6px}
@media print{
  .amrange .no-print{display:none!important}
  .amrange .panel{border:0;box-shadow:none;padding:0}
  .amrange .table-scroll{overflow:visible;max-height:none;border:0}
  .amrange th{position:static}
  .amrange{color:#000}
}
</style>
<div class="amrange">
  <div class="no-print" style="margin-bottom:9px">
    <a class="action secondary" href="{{ route('erp.attendance') }}">← Monthly Attendance Matrix</a>
    <a class="action secondary" href="{{ route('erp.staff-finance.index') }}">Staff Finance Ledger</a>
  </div>
  <h1>ASSAM MOTORS — Date Range Attendance Report</h1>
  <p>Staff App aur ERP ke saved attendance records · From Date–To Date · IST</p>
  @if($errors->any())
  <div class="bad" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
  @endif
  <section class="panel no-print">
    <form method="GET" action="{{ route('erp.attendance.range') }}" class="tools">
      <label>From Date <input type="date" name="from" value="{{ $from }}" required max="{{ now('Asia/Kolkata')->addYear()->format('Y-m-d') }}"></label>
      <label>To Date <input type="date" name="to" value="{{ $to }}" required></label>
      <label>Staff <select name="staff_id">
        <option value="0">All Staff</option>
        @foreach($staffOptions as $st)
          <option value="{{ $st->id }}" @selected($staffId===(int)$st->id)>{{ $st->name }} · {{ $st->staff_code }}</option>
        @endforeach
      </select></label>
      <label>Department <select name="department">
        <option value="">All Departments</option>
        @foreach($departments as $dep)
          <option value="{{ $dep }}" @selected($department===$dep)>{{ $dep }}</option>
        @endforeach
      </select></label>
      <button class="action" type="submit">Generate Report</button>
      <a class="action secondary" href="{{ route('erp.attendance.range', ['from'=>$from,'to'=>$to,'staff_id'=>$staffId,'department'=>$department,'export'=>'csv']) }}">Download CSV / Excel</a>
      <button class="action secondary" type="button" onclick="window.print()">Print / Save PDF</button>
    </form>
    <div class="tip">Date filter dono taraf inclusive hai. Old/inactive staff ka historical record bhi filter se mil sakta hai. CSV aur print mein wahi selected data aayega.</div>
  </section>
  @php
    $totPresent=array_sum(array_column($summaries,'present'));
    $totAbsent=array_sum(array_column($summaries,'absent'));
    $totOff=array_sum(array_column($summaries,'off'));
    $totReview=array_sum(array_column($summaries,'review'));
    $totHours=array_sum(array_column($summaries,'hours'));
  @endphp
  <section class="panel">
    <h2>Attendance Summary — {{ $from }} to {{ $to }}</h2>
    <div class="metrics">
      <div class="metric"><strong>{{ count($summaries) }}</strong><span>Staff with records</span></div>
      <div class="metric"><strong>{{ $totPresent }}</strong><span>Present days</span></div>
      <div class="metric"><strong>{{ $totAbsent }}</strong><span>Explicitly absent days</span></div>
      <div class="metric"><strong>{{ $totOff }}</strong><span>Off days</span></div>
      <div class="metric"><strong>{{ number_format($totHours,2) }}</strong><span>Total worked hours</span></div>
      <div class="metric"><strong>{{ $totReview }}</strong><span>Dates needing review</span></div>
    </div>
  </section>
  <section class="panel">
    <h2>Staff-wise Report</h2>
    <div class="table-scroll"><table>
      <thead><tr><th>Staff</th><th>Code</th><th>Present</th><th>Absent</th><th>Off</th><th>Late</th><th>Hours</th><th>Review</th></tr></thead>
      <tbody>
        @forelse($summaries as $s)
        <tr>
          <td>{{ $s['name'] }}</td><td>{{ $s['code'] }}</td>
          <td>{{ $s['present'] }}</td><td>{{ $s['absent'] }}</td><td>{{ $s['off'] }}</td>
          <td>{{ $s['late'] }}</td><td>{{ number_format($s['hours'],2) }}</td>
          <td @class(['review'=>$s['review']>0])>{{ $s['review']>0?'Verify '.$s['review']:'OK' }}</td>
        </tr>
        @empty
        <tr><td colspan="8">No recorded attendance for selected filters.</td></tr>
        @endforelse
      </tbody>
    </table></div>
    <div class="tip">Unrecorded date ko automatic Absent nahi maana gaya. Same staff/date ka duplicate ho to report Review flag karega.</div>
  </section>
  <section class="panel">
    <h2>Daily Attendance Detail ({{ count($rows) }} records)</h2>
    <div class="table-scroll"><table>
      <thead><tr><th>Date</th><th>Staff</th><th>Code</th><th>Department</th><th>Check-In</th><th>Check-Out</th><th>Status</th><th>Late</th><th>Hours</th><th>Verification</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r['date'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['code'] }}</td>
          <td>{{ $r['department'] }}</td><td>{{ $r['check_in'] ?: '—' }}</td><td>{{ $r['check_out'] ?: '—' }}</td>
          <td>{{ ucfirst($r['status']) }}</td><td>{{ $r['late']?'Late':'—' }}</td>
          <td>{{ number_format($r['hours'],2) }}</td>
          <td @class(['review'=>$r['review']])>{{ $r['duplicate']?'Duplicate — Check':($r['review']?'Check':'OK') }}</td>
        </tr>
      @empty
        <tr><td colspan="10">No records in selected period.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
  <section class="panel">
    <h2>Automatic Salary / Absence Analysis — Preview Only</h2>
    @if(!$fullMonth)
      <div class="salary-note">Monthly salary estimate dekhne ke liye From Date mahine ki 1 tareekh aur To Date mahine ki aakhri tareekh select karein. Daily attendance report kisi bhi selected range ke liye available hai.</div>
    @else
      <div class="salary-note">Existing HR Salary Impact configuration aur explicitly marked Absent days ke basis par calculation. Ye salary deduction, approved payroll, salary slip ya payable voucher **nahi** hai. HR ko paid leaves, weekly offs, missing punches, join/exit dates aur advance verify karna hoga.</div>
      <div class="table-scroll" style="margin-top:12px"><table>
        <thead><tr><th>Staff</th><th>Monthly Basic (₹)</th><th>Absent Days</th><th>Illustrative Absence Allocation (₹)</th><th>Estimated Basic After Absence (₹)</th><th>Approval</th></tr></thead>
        <tbody>
        @forelse($summaries as $s)
          <tr><td>{{ $s['name'] }} · {{ $s['code'] }}</td>
          <td>{{ $s['salary']===null?'Not configured':number_format($s['salary'],2) }}</td>
          <td>{{ $s['absent'] }}</td>
          <td>{{ $s['absence_allocation']===null?'—':number_format($s['absence_allocation'],2) }}</td>
          <td>{{ $s['salary_after_absence_estimate']===null?'—':number_format($s['salary_after_absence_estimate'],2) }}</td>
          <td class="review">HR Review Required</td></tr>
        @empty
        <tr><td colspan="6">No matching staff attendance for payroll preview.</td></tr>
        @endforelse
        </tbody>
      </table></div>
    @endif
    <div class="tip">Salary profile edit karein: HR Salary Configuration. Actual approved staff payments ke liye existing Staff Finance ledger use karein; automatic postings disabled hain.</div>
  </section>
</div>
@endsection
