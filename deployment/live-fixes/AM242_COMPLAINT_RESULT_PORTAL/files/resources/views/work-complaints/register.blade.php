<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Work Complaint Register</title>
<style>body{font:14px/1.5 Arial,sans-serif;background:#f4f7fb;color:#1c3347;margin:0}main{max-width:1200px;margin:25px auto;padding:0 14px}h1{font-size:22px;margin-bottom:4px}a{color:#0b5c98;font-weight:700;text-decoration:none}.panel{background:#fff;border:1px solid #d5e0eb;border-radius:10px;padding:16px;margin:14px 0}form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}label{display:grid;gap:4px;font-size:11px;font-weight:700}input,select{padding:9px;border:1px solid #bccddd;border-radius:6px}button{padding:10px 15px;background:#185f9c;color:#fff;border:0;border-radius:6px;cursor:pointer}table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}th,td{padding:10px 8px;text-align:left;border-bottom:1px solid #e1e9f0;vertical-align:top}th{background:#e9f1fa;color:#244866}.scroll{overflow:auto}.muted{color:#5e7488}.pill{background:#eaf0f9;border-radius:12px;padding:3px 7px;font-weight:700;white-space:nowrap}@media(max-width:640px){h1{font-size:18px}}</style></head><body><main>
<h1>ASSAM MOTORS — Employee Work Complaint Register</h1>
<p class="muted">All reported cases remain separate from original completed ROT records. Supervisor findings determine confirmed faults.</p>
<div class="panel"><form method="get" action="{{ route('erp.rot-work-complaints.register') }}">
<label>Month <input type="month" name="month" value="{{ $filters['month'] ?? '' }}"></label>
<label>Staff ID <input type="number" name="staff_id" min="1" value="{{ $filters['staff_id']??'' }}" placeholder="All"></label>
<label>Job Card ID <input type="number" name="job_card_id" min="1" value="{{ $filters['job_card_id']??'' }}" placeholder="All"></label>
<label>Finding <select name="outcome">
<option value="">All</option>
<option value="PENDING" @selected(($filters['outcome']??'')==='PENDING')>Pending Investigation</option>
<option value="WORKMANSHIP_CONFIRMED" @selected(($filters['outcome']??'')==='WORKMANSHIP_CONFIRMED')>Confirmed Workmanship</option>
<option value="NOT_WORKMANSHIP" @selected(($filters['outcome']??'')==='NOT_WORKMANSHIP')>Not Workmanship</option>
<option value="PART_FAILURE" @selected(($filters['outcome']??'')==='PART_FAILURE')>Part Failure</option>
<option value="CUSTOMER_MISUSE" @selected(($filters['outcome']??'')==='CUSTOMER_MISUSE')>Customer Misuse</option>
<option value="INCONCLUSIVE" @selected(($filters['outcome']??'')==='INCONCLUSIVE')>Inconclusive</option>
</select></label><button type="submit">Apply Filters</button></form></div>
<div class="panel scroll"><table><thead><tr><th>Case</th><th>Reported</th><th>JC / Original ROT</th><th>Mechanic</th><th>Complaint</th><th>Status</th><th>Finding</th><th>Result</th></tr></thead>
<tbody>@forelse($cases as $c)<tr>
<td>#{{ $c->id }}</td><td>{{ $c->reported_at }}</td><td>JC #{{ $c->job_card_id }}<br><span class="muted">{{ $c->rot_session_id }}</span></td><td>{{ $c->staff_name ?? ('Staff #'.$c->staff_id) }}<br><span class="muted">{{ $c->staff_code ?? '' }}</span></td><td>{{ \Illuminate\Support\Str::limit($c->description,120) }}</td><td><span class="pill">{{ $c->status }}</span></td><td>{{ str_replace('_',' ',$c->outcome ?? 'Not reviewed') }}</td><td><a href="{{ route('erp.rot-work-complaints.show',$c->id) }}">View Report</a></td>
</tr>@empty<tr><td colspan="8">No complaints match these filters.</td></tr>@endforelse</tbody></table></div>
<p><a href="{{ route('erp.rot-work-complaints.monthly') }}">Monthly Staff Work Complaints</a></p>
</main></body></html>
