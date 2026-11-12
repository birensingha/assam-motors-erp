<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assam Motors — Customer Outstanding & WhatsApp</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,Arial;background:#f3f6fa;color:#18233b}*{box-sizing:border-box}body{margin:0}
header{background:#122a4b;color:white;padding:18px 24px}main{max-width:1100px;margin:24px auto;padding:0 16px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:16px}.card{background:white;border:1px solid #dce4ee;border-radius:14px;padding:20px}
input,select{width:100%;font:inherit;border:1px solid #cdd6e2;border-radius:8px;padding:10px;margin:5px 0 12px}
button{background:#1264a3;color:white;border:0;border-radius:8px;padding:10px 15px;cursor:pointer}
label{font-size:13px;font-weight:650}.note{font-size:12px;color:#62748a}.warning{background:#fff4db;padding:12px;border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:13px}td,th{padding:9px;text-align:left;border-bottom:1px solid #e7ebf0}.scroll{overflow:auto}
</style></head><body>
<header><strong>ASSAM MOTORS</strong> / ERP Admin / Customer Outstanding & WhatsApp</header>
<main>
<h1>Customer Outstanding & WhatsApp Messaging</h1>
<p class="warning">All balance figures must come from the verified ERP Customer Ledger. WhatsApp opens the compose screen; a user must manually send. No delivery confirmation is implied.</p>
@if(session('success'))<p>{{session('success')}}</p>@endif
@if($errors->any())<div class="warning"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<div class="grid">
<section class="card"><h2>WhatsApp Customer Messaging</h2>
<p class="note">Use ERP Customer ID. The current balance and phone number are retrieved from server-side ERP masters, not entered by hand.</p>
<form method="post" action="{{route('am.customer-messaging.message')}}">@csrf
<label>Customer ID</label><input type="number" name="customer_id" min="1" required>
<label>Message Type</label>
<select name="type"><option value="balance">Current Balance</option><option value="reminder">Payment Reminder</option><option value="feedback">Service Feedback Request</option></select>
<label><input type="checkbox" name="consent" value="1" style="width:auto" required> Confirm permitted customer messaging / consent</label>
<p><button type="submit">Open WhatsApp Message</button></p>
</form></section>
<section class="card"><h2>Customer Outstanding Report</h2>
<p class="note">This section requires the existing ERP customer ledger listing endpoint and filters. No invented balances are displayed.</p>
<p>Link the ERP's verified outstanding statement/register here during source integration; include Invoice (+), Credit Note (−), Receipts (−), Advances / On-account adjustments.</p>
</section></div>
<section class="card" style="margin-top:16px"><h2>WhatsApp Activity (Compose Only)</h2>
<div class="scroll"><table><tr><th>Customer</th><th>Type</th><th>Ledger Snapshot</th><th>Status</th></tr>
@forelse($messages as $m)<tr><td>{{$m->customer_name_snapshot}}</td><td>{{$m->type}}</td><td>{{$m->balance_snapshot===null?'—':'INR '.number_format($m->balance_snapshot,2)}}</td><td>{{$m->status}}</td></tr>
@empty<tr><td colspan="4">No message activity.</td></tr>@endforelse
</table></div></section>
</main></body></html>