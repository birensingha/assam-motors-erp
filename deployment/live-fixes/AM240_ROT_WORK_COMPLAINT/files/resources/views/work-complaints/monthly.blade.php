<!doctype html><html><head><meta charset="utf-8"><title>Monthly Staff Work Complaints</title></head><body style="font-family:Arial;max-width:950px;margin:32px auto;padding:12px">
<h2>ASSAM MOTORS — Monthly Work Complaint Performance</h2>
<form method="get"><label>Month <input type="month" name="month" value="{{ $month }}"></label><button type="submit">Apply</button></form>
<p>Reported is not the same as confirmed workmanship. No automatic salary deductions or penalties.</p>
<table border="1" cellpadding="10" cellspacing="0" style="width:100%;border-collapse:collapse">
<thead><tr><th>Staff</th><th>Code</th><th>Reported</th><th>Confirmed workmanship</th><th>Pending investigation</th></tr></thead>
<tbody>@forelse($rows as $r)<tr><td>{{ $r->name ?? ('Staff #'.$r->staff_id) }}</td><td>{{ $r->staff_code }}</td><td>{{ $r->reported_count }}</td><td>{{ $r->confirmed_count }}</td><td>{{ $r->pending_count }}</td></tr>
@empty<tr><td colspan="5">No work complaints reported in this month.</td></tr>@endforelse</tbody></table>
</body></html>
