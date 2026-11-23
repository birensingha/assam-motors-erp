{{-- Standalone Blade view pending connection to the existing ERP layout/controller. --}}
@php
  $fromDate = request('from', now('Asia/Kolkata')->startOfMonth()->format('Y-m-d'));
  $toDate = request('to', now('Asia/Kolkata')->format('Y-m-d'));
  $chosenStaff = request('staff_id', '');
  $rows = $report['rows'] ?? [];
  $summaries = $report['staff_summary'] ?? [];
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ASSAM MOTORS — Attendance Report</title>
<style>
  :root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#172238;background:#f4f6fa}
  *{box-sizing:border-box}body{margin:0}.wrap{max-width:1520px;margin:0 auto;padding:16px 20px}
  h1{font-size:22px;margin:0}.hint{font-size:12px;color:#687587}
  .panel{background:#fff;border:1px solid #d8e0eb;border-radius:10px;padding:14px;margin-top:14px}
  .filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap}
  .filters label{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:650;min-width:160px}
  input,select{border:1px solid #b9c7d8;padding:9px 10px;border-radius:7px;background:#fff;min-height:38px;font:inherit}
  button,.action{border:0;border-radius:7px;background:#15376b;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer;font-size:13px;display:inline-block}
  .action.secondary{background:#eff3f8;color:#172238;border:1px solid #becbda}
  .table-wrap{overflow:auto;max-height:70vh}table{border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap}
  th,td{text-align:left;border-bottom:1px solid #e7ebf0;padding:9px 11px}
  th{background:#eaf0f8;position:sticky;top:0;z-index:1}
  tr:nth-child(even) td{background:#fafbfd}.review{color:#ab2b2b;font-weight:bold}
  .summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
  .summary article{border:1px solid #e1e6ed;border-radius:8px;padding:12px;background:#fafcff}
  .summary b{display:block;margin-bottom:7px}.summary small{display:block;color:#5a677a;line-height:1.7}
  @media print{.filters,.print-hide{display:none!important}body{background:#fff}.wrap{padding:0}.panel{border:0;padding:0}.table-wrap{max-height:none;overflow:visible}th{position:static}}
</style>
</head>
<body><main class="wrap">
  <header>
    <h1>ASSAM MOTORS — Staff Attendance Report</h1>
    <div class="hint">Bishnu Rabha Path, Sawkuchi, Guwahati, Assam</div>
  </header>
  <section class="panel">
    <form method="GET" class="filters" aria-label="Attendance report filters">
      <label>From Date <input name="from" type="date" required value="{{ $fromDate }}"></label>
      <label>To Date <input name="to" type="date" required value="{{ $toDate }}" min="{{ $fromDate }}"></label>
      <label>Staff
        <select name="staff_id">
          <option value="">All Staff</option>
          @foreach(($staffOptions ?? []) as $staff)
            <option value="{{ $staff['id'] }}" @selected((string)$chosenStaff === (string)$staff['id'])>{{ $staff['name'] }} ({{ $staff['id'] }})</option>
          @endforeach
        </select>
      </label>
      <button type="submit">Show Report</button>
      <a class="action secondary" href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->only(['from','to','staff_id']),['export'=>'csv'])) }}">Export CSV</a>
      <button type="button" class="action secondary" onclick="window.print()">Print / PDF</button>
    </form>
    <p class="hint">From/To inclusive. Missing checkout requires Admin review before payroll approval. CSV export needs its controller endpoint connected.</p>
  </section>
  <section class="panel">
    <h2 style="font-size:16px;margin:0 0 12px">Staff Summary</h2>
    <div class="summary">
      @forelse($summaries as $person)
        <article>
          <b>{{ $person['staff_name'] ?: $person['staff_id'] }} ({{ $person['staff_id'] }})</b>
          <small>Recorded: {{ $person['recorded_days'] }} | Present: {{ $person['status_counts']['present'] }} | Absent: {{ $person['status_counts']['absent'] }}</small>
          <small>Half-day: {{ $person['status_counts']['half_day'] }} | Paid leave: {{ $person['status_counts']['paid_leave'] }} | Review: {{ $person['review_days'] }}</small>
          <small>Work: {{ number_format($person['work_minutes']/60,2) }} h | OT: {{ number_format($person['overtime_minutes']/60,2) }} h</small>
        </article>
      @empty
        <span class="hint">No attendance records in selected period.</span>
      @endforelse
    </div>
  </section>
  <section class="panel">
    <h2 style="font-size:16px;margin:0 0 12px">Daily Entries ({{ count($rows) }})</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Staff Code</th><th>Name</th><th>Status</th><th>Check-In</th><th>Check-Out</th><th>Work Hours</th><th>Late (min)</th><th>OT (min)</th><th>Review</th></tr></thead>
      <tbody>
        @forelse($rows as $row)
          <tr>
            <td>{{ $row['date'] }}</td><td>{{ $row['staff_id'] }}</td><td>{{ $row['staff_name'] }}</td>
            <td>{{ str_replace('_',' ',ucwords($row['status'],'_')) }}</td><td>{{ $row['check_in'] ?: '—' }}</td><td>{{ $row['check_out'] ?: '—' }}</td>
            <td>{{ number_format($row['work_minutes']/60,2) }}</td><td>{{ $row['late_minutes'] }}</td>
            <td>{{ $row['overtime_minutes'] }}</td>
            <td @class(['review' => $row['needs_review']])>{{ $row['needs_review'] ? 'CHECK' : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="10">No records found.</td></tr>
        @endforelse
      </tbody>
    </table></div>
  </section>
</main></body></html>
