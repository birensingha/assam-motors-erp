<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <title>Assigned Vehicle Inspection · {{ $card['job_no'] ?? 'Job Card' }} · Assam Motors</title>
 <link rel="stylesheet" href="{{ asset('css/staff-portal.css') }}">
 <style>
 :root{color-scheme:light}body{margin:0;background:#f3f7fc;color:#153653;font-family:Arial,Helvetica,sans-serif}
 .p29-top{padding:15px 20px;background:#0d355e;color:#fff;border-bottom:4px solid #db3f3b;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap}
 .p29-top strong{font-size:21px}.p29-top a{color:#fff;text-decoration:none;border:1px solid #779ab5;border-radius:9px;padding:9px 12px;font-weight:700}
 .p29-shell{max-width:960px;margin:auto;padding:18px 14px 45px}
 .p29-title{background:linear-gradient(110deg,#123e71,#157789);border-radius:14px;padding:22px;color:#fff}
 .p29-title h1{font-size:23px;margin:8px 0}.p29-title p{margin:5px 0;color:#d9ecf4;font-size:13px}
 .p29-panel{background:#fff;border-radius:13px;padding:18px;margin:15px 0;border:1px solid #d2e2f0;box-shadow:0 5px 15px #1244680a}
 .p29-panel h2{margin:0 0 10px;font-size:19px;color:#163e65}.p29-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}
 .p29-meta div{background:#eff6fc;border-radius:9px;padding:12px}.p29-meta small{display:block;color:#6c8293;font-size:11px}.p29-meta strong{font-size:13px}
 .p29-check{display:grid;grid-template-columns:minmax(150px,1fr) 145px minmax(180px,1.4fr);gap:13px;align-items:center;padding:12px 9px;border-bottom:1px solid #e4eef6}
 .p29-check:nth-child(even){background:#f9fcff}.p29-check strong{font-size:13px}
 .p29-check label{display:block;color:#516b84;font-size:12px;font-weight:bold}
 .p29-check select,.p29-check textarea{width:100%;margin-top:5px;box-sizing:border-box;border:1px solid #b7cde0;background:#fff;border-radius:9px;padding:10px;min-height:44px}
 .p29-check textarea{min-height:55px;resize:vertical}
 .p29-btn{display:inline-block;padding:13px 17px;background:#0a8162;color:#fff;border:0;border-radius:10px;cursor:pointer;font-weight:800;min-height:45px}
 .p29-note{border-left:4px solid #dca526;padding:13px;background:#fff8e6;border-radius:8px;font-size:13px;margin:15px 0}
 .p29-success{border-left:4px solid #158661;background:#e2f9ea;padding:13px;border-radius:9px;margin:14px 0}
 .p29-error{border-left:4px solid #c23c3a;background:#fff0ef;padding:13px;border-radius:9px;margin:14px 0}
 @media(max-width:680px){.p29-check{grid-template-columns:1fr 125px}.p29-check label:last-child{grid-column:1/-1}.p29-meta{grid-template-columns:1fr 1fr}}
 </style>
</head>
<body>
 <header class="p29-top"><strong>ASSAM MOTORS</strong><a href="{{ route('staff.portal') }}">← My Staff Dashboard</a></header>
 <main class="p29-shell">
  <div class="p29-title"><small>ASSIGNED VEHICLE INSPECTION</small>
   <h1>{{ $card['job_no'] ?? 'Job Card' }}</h1>
   <p>{{ $card['vehicle_reg_no'] ?? '' }} · {{ $card['customer_name'] ?? '' }}</p>
   <p>Inspection assigned to your Staff ID. This does not change the Deciding Technician.</p>
  </div>
  @if(session('success'))<div class="p29-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="p29-error">{{ $errors->first() }}</div>@endif
  <section class="p29-panel">
   <div class="p29-meta">
    <div><small>Inspection Status</small><strong>{{ strtoupper((string)($card['inspection_status']??'PENDING')) }}</strong></div>
    <div><small>Assigned Date</small><strong>{{ !empty($assignment->assigned_at) ? \Carbon\Carbon::parse($assignment->assigned_at)->format('d-m-Y h:i A') : '—' }}</strong></div>
    <div><small>Current Job Status</small><strong>{{ strtoupper((string)($card['status']??'OPEN')) }}</strong></div>
   </div>
   <div class="p29-note">Inspection completed hone ke baad Final Diagnosis ka option unlock hoga. Technician Decision aur Estimate uske baad alag stages hain.</div>
  </section>
  <section class="p29-panel">
   <h2>Vehicle Inspection Checklist — 10 Points</h2>
   @if($pending && count($items)===0)
    <form method="post" action="{{ route('staff.assigned-inspection.complete',$card['id']) }}" onsubmit="return confirm('Complete this Vehicle Inspection? Saved inspection cannot be overwritten.')">
     @csrf
     @foreach($checks as $i=>$check)
      <div class="p29-check">
       <strong>{{ $i+1 }}. {{ $check }}</strong>
       <input type="hidden" name="items[{{ $i }}][item_name]" value="{{ $check }}">
       <label>Condition<select name="items[{{ $i }}][status]" required><option value="">Choose...</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option></select></label>
       <label>Remarks<textarea name="items[{{ $i }}][remarks]" maxlength="1000" rows="1" placeholder="Observations / findings"></textarea></label>
      </div>
     @endforeach
     <p class="p29-note">All ten conditions required. Submit ke baad report lock ho jayega.</p>
     <button type="submit" class="p29-btn">✓ Complete Vehicle Inspection</button>
    </form>
   @else
    <p class="p29-note">This inspection is already completed, locked, or has existing item data. No overwrite is permitted from Staff Portal.</p>
    @forelse($items as $item)
     <div class="p29-check"><strong>{{ $item['item_name'] ?? 'Inspection item' }}</strong>
      <span>{{ strtoupper((string)($item['status']??'')) }}</span><span>{{ $item['remarks']??'—' }}</span>
     </div>
    @empty
     <p>No submitted checklist rows yet.</p>
    @endforelse
   @endif
  </section>
 </main>
</body></html>
