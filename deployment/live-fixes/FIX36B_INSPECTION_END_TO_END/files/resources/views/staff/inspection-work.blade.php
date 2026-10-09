<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Vehicle Inspection · {{ $job->job_no }} · Assam Motors</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#17324d;font-family:Arial,sans-serif}
.top{background:#0b315b;color:#fff;padding:16px 18px}.top a{color:#fff;text-decoration:none}.shell{max-width:900px;margin:18px auto;padding:0 14px 30px}
.card{background:#fff;border:1px solid #dce5ef;border-radius:16px;padding:16px;margin-bottom:12px;box-shadow:0 4px 14px rgba(15,39,69,.05)}
h1,h2{margin:0}.sub{color:#718196;font-size:13px;margin-top:6px}.meta{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:12px}
.meta div{background:#f7fafc;border:1px solid #e5ecf3;border-radius:10px;padding:10px}.meta span{font-size:10px;color:#7a8999;display:block}.meta strong{display:block;margin-top:4px}
.item{border:1px solid #dbe5ee;border-radius:12px;padding:12px;margin:9px 0}.item h3{margin:0 0 8px;font-size:14px}
.controls{display:grid;grid-template-columns:180px 1fr;gap:8px}.controls select,.controls input{width:100%;padding:10px;border:1px solid #cfdbe7;border-radius:9px;background:#fff}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:14px}.btn{border:0;border-radius:9px;padding:11px 15px;font-weight:800;text-decoration:none;display:inline-block}.primary{background:#0b315b;color:#fff}.ghost{background:#eaf0f6;color:#26435f}
.alert{background:#fff0f0;border:1px solid #ffd2d2;color:#9b2929;border-radius:10px;padding:10px;margin-bottom:10px}
@media(max-width:620px){.meta,.controls{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="top"><a href="{{ route('staff.portal') }}">← Staff Portal</a></div>
<main class="shell">
  @if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
  <section class="card">
    <h1>🚗 Vehicle Inspection</h1>
    <div class="sub">Assigned to your Staff login · Complete checklist and submit to Job Card.</div>
    <div class="meta">
      <div><span>Job Card</span><strong>{{ $job->job_no }}</strong></div>
      <div><span>Vehicle</span><strong>{{ $job->vehicle_reg_no ?: '—' }}</strong></div>
      <div><span>Customer</span><strong>{{ $job->customer_name ?: '—' }}</strong></div>
      <div><span>Service</span><strong>{{ $job->service_type ?: '—' }}</strong></div>
    </div>
    <div class="sub"><b>Complaint:</b> {{ $job->customer_complaint ?: '—' }}</div>
  </section>

  @php
    $defaults=['Exterior / Body','Tyres & Wheels','Brakes','Engine Bay','Engine Oil','Coolant','Battery','Lights / Horn','Wipers / Washers','AC / Blower','Steering / Suspension','Underbody / Fluid Leaks','Interior / Safety','Road Test'];
    $oldMap=collect($items)->keyBy(fn($x)=>strtolower(trim((string)$x->item_name)));
  @endphp
  <form method="post" action="{{ route('staff.inspection-submit',$job->id) }}" class="card">
    @csrf
    <h2>Inspection Checklist</h2>
    @foreach($defaults as $i=>$name)
      @php($old=$oldMap->get(strtolower($name)))
      <div class="item">
        <h3>{{ $name }}</h3>
        <input type="hidden" name="items[{{ $i }}][item_name]" value="{{ $name }}">
        <div class="controls">
          <select name="items[{{ $i }}][status]" required>
            <option value="good" @selected(($old->status ?? 'good')==='good')>✓ Good</option>
            <option value="fair" @selected(($old->status ?? '')==='fair')>△ Fair / Attention</option>
            <option value="poor" @selected(($old->status ?? '')==='poor')>⚠ Poor / Repair Required</option>
          </select>
          <input name="items[{{ $i }}][remarks]" value="{{ $old->remarks ?? '' }}" placeholder="Remarks / observation">
        </div>
      </div>
    @endforeach
    <div class="actions">
      <a class="btn ghost" href="{{ route('staff.portal') }}">Cancel</a>
      <button class="btn primary" type="submit" onclick="return confirm('Vehicle Inspection complete karke Job Card me submit karna hai?')">✓ Submit Inspection</button>
    </div>
  </form>
</main>
</body>
</html>
