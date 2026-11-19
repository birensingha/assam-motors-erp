<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assam Motors — Customer Feedback</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,Arial;background:#f3f6fa;color:#18233b}*{box-sizing:border-box}body{margin:0}
header{background:#122a4b;color:white;padding:18px 24px}header small{opacity:.8}main{max-width:1280px;margin:24px auto;padding:0 16px}
h1{font-size:24px}h2{font-size:17px;margin-top:0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:16px}
.card{background:white;border-radius:14px;border:1px solid #e0e6ee;padding:20px;box-shadow:0 2px 8px #1d2a3f0b}
input,select,textarea{width:100%;font:inherit;border:1px solid #cdd6e2;border-radius:8px;padding:10px;margin:5px 0 12px}
button{background:#1264a3;color:white;border:0;border-radius:8px;padding:10px 15px;cursor:pointer}
button.alt{background:#e8eff6;color:#1a3754}label{font-size:13px;font-weight:650}table{width:100%;border-collapse:collapse;font-size:13px}td,th{padding:10px;text-align:left;border-bottom:1px solid #e7ebf0;vertical-align:top}
.note{font-size:12px;color:#62748a}.success{padding:12px;background:#e3f7ed;border-radius:8px}.warning{padding:12px;background:#fff4db;border-radius:8px}
.filters{display:grid;grid-template-columns:minmax(180px,2fr) repeat(2,minmax(120px,1fr)) auto;gap:10px;align-items:end}.filters label{display:block}.filters button{margin-bottom:12px}@media(max-width:680px){.filters{grid-template-columns:1fr}}[hidden]{display:none!important}.picklist{border:1px solid #cdd6e2;border-radius:10px;padding:6px;margin:-6px 0 12px;max-height:240px;overflow:auto;background:white;box-shadow:0 6px 18px #122a4b16}.picklist button{display:block;width:100%;text-align:left;background:#f5f8fc;color:#18233b;margin-bottom:4px}.picklist button:hover,.picklist button:focus{background:#dae9f7}summary{cursor:pointer;font-weight:650} .scroll{overflow:auto} .pill{padding:3px 7px;background:#eff4f9;border-radius:7px}
</style></head><body>
<header><strong>ASSAM MOTORS</strong> <small> / ERP Admin / Customer / Reviews & Feedback</small></header>
<main><h1>Customer Reviews & Feedback</h1>
<p class="note">Service feedback, review drafts and complaints only. Financial balances and payment messaging are on a separate Admin page.</p>
<div class="grid" aria-label="Feedback summary">
  <section class="card"><h2>Total Feedback</h2><strong>{{ number_format($feedbackCount) }}</strong></section>
  <section class="card"><h2>Average Rating</h2><strong>{{ $averageRating === null ? '—' : number_format($averageRating,2).' / 5' }}</strong></section>
  <section class="card"><h2>Open Complaints</h2><strong>{{ number_format($openComplaintCount) }}</strong></section>
</div>
@if(session('success'))<p class="success">{{session('success')}}</p>@endif
@if($errors->any())<div class="warning"><strong>Please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="grid">
<section class="card"><h2>Record Workshop Feedback</h2>
<form id="feedback-entry-form" method="post" action="{{route('am.customer-engagement.feedback')}}">@csrf
<label for="feedback-target-search">Customer / Vehicle / Job Card</label>
<input id="feedback-target-search" type="search" autocomplete="off" placeholder="Type customer name, vehicle no. or JC no." aria-controls="feedback-target-options" aria-expanded="false">
<div id="feedback-target-options" role="listbox" class="picklist" hidden></div>
<input id="feedback-customer-id" type="hidden" name="customer_id" value="">
<input id="feedback-vehicle-id" type="hidden" name="vehicle_id" value="">
<input id="feedback-job-card-id" type="hidden" name="job_card_id" value="">
<p id="feedback-selection" class="note" role="status">Select a customer from ERP Master. Vehicle / Job Card optional where none exists.</p>
<p id="feedback-selection-error" class="warning" hidden></p>
<label>Rating</label><select name="rating" required>@foreach([5,4,3,2,1] as $n)<option value="{{$n}}">{{$n}} / 5</option>@endforeach</select>
<label>Feedback</label><textarea name="comment" maxlength="3000" required></textarea>
<button>Save Feedback</button></form></section>

</div>
<section class="card" style="margin-top:16px"><h2>Customer Feedback Register</h2>
<form method="get" class="filters" role="search">
  <label>Customer / Vehicle / JC
    <input name="q" placeholder="Search name, vehicle or Job Card" value="{{request('q')}}" maxlength="80">
  </label>
  <label>Rating
    <select name="rating">
      <option value="">All ratings</option>
      @foreach([5,4,3,2,1] as $n)
        <option value="{{$n}}" @selected(request('rating') == (string)$n)>{{$n}} / 5</option>
      @endforeach
    </select>
  </label>
  <label>Complaint Status
    <select name="complaint_status">
      <option value="">All statuses</option>
      <option value="open" @selected(request('complaint_status') === 'open')>Open</option>
      <option value="resolved" @selected(request('complaint_status') === 'resolved')>Resolved</option>
      <option value="none" @selected(request('complaint_status') === 'none')>No complaint</option>
    </select>
  </label>
  <button type="submit">Apply Filters</button>
</form>
<div class="scroll"><table><thead><tr><th>Customer / Job</th><th>Rating & Feedback</th><th>Reply Draft</th><th>Complaint</th></tr></thead><tbody>
@forelse($feedback as $item)<tr><td><strong>{{$item->customer_name}}</strong><p class="note">{{$item->vehicle}} {{$item->job_card_ref}}</p></td>
<td><span class="pill">{{$item->rating}} ★</span><p>{{$item->comment}}</p><span class="note">{{$item->source}} · {{$item->created_at}}</span></td>
<td><details><summary>{{ $item->reply_draft ? 'Edit draft' : 'Draft reply' }}</summary>
<form method="post" action="{{route('am.customer-engagement.reply')}}">@csrf
<input type="hidden" name="feedback_id" value="{{$item->id}}"><textarea name="reply" maxlength="2000" required>{{$item->reply_draft}}</textarea><button>Save Draft</button></form></details></td>
<td>
@if($item->complaint_status==='open')<span class="pill">Open complaint</span>
@elseif($item->complaint_status==='resolved')<span class="pill">Resolved</span>
@else
<form method="post" action="{{route('am.customer-engagement.complaint')}}">@csrf
<input type="hidden" name="feedback_id" value="{{$item->id}}">
<input type="hidden" name="action" value="open">
<button class="alt">Open Complaint</button>
</form>
@endif
</td></tr>
@empty<tr><td colspan="4">No feedback recorded yet.</td></tr>@endforelse</tbody></table></div>{{ $feedback->links() }}</section>
<div class="grid" style="margin-top:16px">
<section class="card"><h2>Complaints</h2><div class="scroll"><table><tr><th>Feedback</th><th>Status</th><th>Resolution</th></tr>
@forelse($complaints as $c)<tr><td>#{{$c->feedback_id}}</td><td>{{$c->status}}</td><td>
@if($c->status!=='resolved')<form method="post" action="{{route('am.customer-engagement.complaint')}}">@csrf<input type="hidden" name="feedback_id" value="{{$c->feedback_id}}"><input type="hidden" name="action" value="resolve"><textarea name="resolution_note" required placeholder="Resolution note"></textarea><button>Resolve</button></form>
@else {{$c->resolution_note}} <p class="note">Resolved: {{$c->resolved_at}}</p> @endif</td></tr>
@empty<tr><td colspan="3">No complaints recorded.</td></tr>
@endforelse</table></div></section>

</div>
<section class="card" style="margin-top:16px">
  <h2>Complaint Action History</h2>
  <p class="note">Recorded operator actions for accountability. No outstanding or payment details.</p>
  <div class="scroll"><table>
    <thead><tr><th>Feedback</th><th>Action</th><th>Admin User ID</th><th>Time</th><th>Note</th></tr></thead>
    <tbody>
    @forelse($complaintHistory as $event)
      <tr><td>#{{$event->feedback_id}}</td><td>{{$event->action}}</td>
      <td>{{$event->actor_id}}</td><td>{{$event->created_at}}</td><td>{{$event->note ?? '—'}}</td></tr>
    @empty<tr><td colspan="5">No complaint actions recorded.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
</main>
<script>
(() => {
  const form=document.getElementById('feedback-entry-form');
  if(!form)return;
  const search=document.getElementById('feedback-target-search');
  const options=document.getElementById('feedback-target-options');
  const selected=document.getElementById('feedback-selection');
  const error=document.getElementById('feedback-selection-error');
  const customerId=document.getElementById('feedback-customer-id');
  const vehicleId=document.getElementById('feedback-vehicle-id');
  const jobId=document.getElementById('feedback-job-card-id');
  const endpoint=@json(route('am.customer-engagement.targets'));
  let seq=0;
  let controller=null;
  let searchTimer=null;

  const clearSelection=()=>{
    customerId.value='';
    vehicleId.value='';
    jobId.value='';
    selected.textContent='Select a customer from ERP Master.';
    error.hidden=true;
    error.textContent='';
  };
  const showOptions=visible=>{
    options.hidden=!visible;
    search.setAttribute('aria-expanded',visible?'true':'false');
  };
  search.addEventListener('input',()=>{
    clearSelection();
    options.replaceChildren();
    showOptions(false);
    const current=++seq;
    if(searchTimer)clearTimeout(searchTimer);
    if(controller)controller.abort();
    const query=search.value.trim();
    if(query.length<2)return;
    searchTimer=setTimeout(async()=>{
      controller=new AbortController();
      try {
        const url=new URL(endpoint,window.location.href);
        url.searchParams.set('q',query);
        const resp=await fetch(url.toString(),{
          method:'GET',credentials:'same-origin',
          headers:{Accept:'application/json'},
          cache:'no-store',signal:controller.signal
        });
        if(!resp.ok)throw new Error('ERP customer lookup unavailable');
        const data=await resp.json();
        if(current!==seq)return;
        options.replaceChildren();
        const items=Array.isArray(data.targets)?data.targets:[];
        if(!items.length){
          options.textContent='No matching Customer, Vehicle or Job Card found.';
        } else {
          items.forEach(item=>{
            if(!Number.isSafeInteger(Number(item.customer_id))||Number(item.customer_id)<1)return;
            const btn=document.createElement('button');
            btn.type='button';
            btn.setAttribute('role','option');
            const parts=[String(item.customer_name||''),String(item.vehicle||''),String(item.job_card_ref||'')].filter(Boolean);
            btn.textContent=parts.join(' · ');
            btn.addEventListener('click',()=>{
              customerId.value=String(item.customer_id);
              vehicleId.value=item.vehicle_id?String(item.vehicle_id):'';
              jobId.value=item.job_card_id?String(item.job_card_id):'';
              search.value=parts.join(' · ');
              selected.textContent='Selected: '+parts.join(' · ');
              error.hidden=true;
              showOptions(false);
              ++seq;
            });
            options.appendChild(btn);
          });
        }
        showOptions(true);
      } catch (ex) {
        if(current!==seq||ex.name==='AbortError')return;
        options.textContent='Unable to search ERP Customer Master. Please retry.';
        showOptions(true);
      }
    },250);
  });
  form.addEventListener('submit',event=>{
    if(!customerId.value){
      event.preventDefault();
      error.textContent='Choose an existing Customer from search results before saving feedback.';
      error.hidden=false;
      search.focus();
    }
  });
})();
</script></body></html>