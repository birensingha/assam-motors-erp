<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assam Motors — Customer Engagement</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,Arial;background:#f3f6fa;color:#18233b}*{box-sizing:border-box}body{margin:0}
header{background:#122a4b;color:white;padding:18px 24px}header small{opacity:.8}main{max-width:1280px;margin:24px auto;padding:0 16px}
h1{font-size:24px}h2{font-size:17px;margin-top:0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:16px}
.card{background:white;border-radius:14px;border:1px solid #e0e6ee;padding:20px;box-shadow:0 2px 8px #1d2a3f0b}
input,select,textarea{width:100%;font:inherit;border:1px solid #cdd6e2;border-radius:8px;padding:10px;margin:5px 0 12px}
button{background:#1264a3;color:white;border:0;border-radius:8px;padding:10px 15px;cursor:pointer}
button.alt{background:#e8eff6;color:#1a3754}label{font-size:13px;font-weight:650}table{width:100%;border-collapse:collapse;font-size:13px}td,th{padding:10px;text-align:left;border-bottom:1px solid #e7ebf0;vertical-align:top}
.note{font-size:12px;color:#62748a}.success{padding:12px;background:#e3f7ed;border-radius:8px}.warning{padding:12px;background:#fff4db;border-radius:8px}
summary{cursor:pointer;font-weight:650} .scroll{overflow:auto} .pill{padding:3px 7px;background:#eff4f9;border-radius:7px}
</style></head><body>
<header><strong>ASSAM MOTORS</strong> <small> / ERP Admin / Customer / Reviews & WhatsApp</small></header>
<main><h1>Customer Reviews, Feedback & WhatsApp</h1>
<p class="warning">Google publishing and WhatsApp automatic sending are not enabled. WhatsApp opens the compose screen and requires manual Send.</p>
@if(session('success'))<p class="success">{{session('success')}}</p>@endif
@if($errors->any())<div class="warning"><strong>Please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="grid">
<section class="card"><h2>Record Workshop Feedback</h2>
<form method="post" action="{{route('am.customer-engagement.feedback')}}">@csrf
<label>ERP Customer ID (optional pending customer mapping)</label><input type="number" name="customer_id" min="1">
<label>Customer Name</label><input name="customer_name" maxlength="160" required>
<label>Vehicle Number</label><input name="vehicle" maxlength="32">
<label>Job Card Reference</label><input name="job_card_ref" maxlength="64">
<label>Rating</label><select name="rating" required>@foreach([5,4,3,2,1] as $n)<option value="{{$n}}">{{$n}} / 5</option>@endforeach</select>
<label>Feedback</label><textarea name="comment" maxlength="3000" required></textarea>
<button>Save Feedback</button></form></section>
<section class="card"><h2>WhatsApp Customer Messaging</h2>
<p class="note">Customer and verified financial balance are retrieved server-side from ERP's canonical ledger adapter. Do not use demo balance values.</p>
<form method="post" action="{{route('am.customer-engagement.message')}}">@csrf
<label>ERP Customer ID</label><input type="number" name="customer_id" min="1" required>
<label>Message Type</label><select name="type"><option value="balance">Account Balance</option><option value="reminder">Payment Reminder</option><option value="feedback">Request Feedback</option></select>
<label><input type="checkbox" name="consent" value="1" style="width:auto" required> I confirm customer messaging consent / permitted contact</label>
<p><button type="submit">Open WhatsApp Message</button></p></form>
<p class="note">Status is recorded as opened_not_sent; not as delivered. WhatsApp number is read from customer master.</p></section>
</div>
<section class="card" style="margin-top:16px"><h2>Customer Feedback Register</h2>
<form method="get" style="display:flex;gap:8px"><input name="q" placeholder="Search name, vehicle, Job Card" value="{{request('q')}}"><button>Search</button></form>
<div class="scroll"><table><thead><tr><th>Customer / Job</th><th>Rating & Feedback</th><th>Reply Draft</th><th>Complaint</th></tr></thead><tbody>
@forelse($feedback as $item)<tr><td><strong>{{$item->customer_name}}</strong><p class="note">{{$item->vehicle}} {{$item->job_card_ref}}</p></td>
<td><span class="pill">{{$item->rating}} ★</span><p>{{$item->comment}}</p><span class="note">{{$item->source}} · {{$item->created_at}}</span></td>
<td><details><summary>{{ $item->reply_draft ? 'Edit draft' : 'Draft reply' }}</summary>
<form method="post" action="{{route('am.customer-engagement.reply')}}">@csrf
<input type="hidden" name="feedback_id" value="{{$item->id}}"><textarea name="reply" maxlength="2000" required>{{$item->reply_draft}}</textarea><button>Save Draft</button></form></details></td>
<td><form method="post" action="{{route('am.customer-engagement.complaint')}}">@csrf<input type="hidden" name="feedback_id" value="{{$item->id}}"><input type="hidden" name="action" value="open"><button class="alt">Open Complaint</button></form></td></tr>
@empty<tr><td colspan="4">No feedback recorded yet.</td></tr>@endforelse</tbody></table></div>{{ $feedback->links() }}</section>
<div class="grid" style="margin-top:16px">
<section class="card"><h2>Complaints</h2><div class="scroll"><table><tr><th>Feedback</th><th>Status</th><th>Resolution</th></tr>
@foreach($complaints as $c)<tr><td>#{{$c->feedback_id}}</td><td>{{$c->status}}</td><td>
@if($c->status!=='resolved')<form method="post" action="{{route('am.customer-engagement.complaint')}}">@csrf<input type="hidden" name="feedback_id" value="{{$c->feedback_id}}"><input type="hidden" name="action" value="resolve"><textarea name="resolution_note" required placeholder="Resolution note"></textarea><button>Resolve</button></form>
@else {{$c->resolution_note}} @endif</td></tr>@endforeach</table></div></section>
<section class="card"><h2>WhatsApp Activity Log</h2><div class="scroll"><table><tr><th>Customer</th><th>Type</th><th>Status</th></tr>
@foreach($messages as $m)<tr><td>{{$m->customer_name_snapshot}}</td><td>{{$m->type}}</td><td>{{$m->status}}</td></tr>@endforeach</table></div></section>
</div>
</main></body></html>