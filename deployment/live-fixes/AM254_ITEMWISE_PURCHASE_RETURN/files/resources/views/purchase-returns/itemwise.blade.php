@extends('layouts.erp')
@section('title','Item-wise Purchase Return · ASSAM MOTORS')
@section('content')
{{-- AM254_ITEMWISE_PURCHASE_RETURN: Search part/OSL first; choose exact purchased line by vendor / invoice / JC. --}}
<style>
.am254-page{max-width:1660px;margin:0 auto;color:#193954;font-size:12px}
.am254-page *{box-sizing:border-box}
.am254-head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;background:linear-gradient(110deg,#eef6ff,#f9fcff);border:1px solid #d2e2f2;padding:17px 20px;border-radius:13px;margin:0 0 13px}
.am254-head h1{font-size:24px;color:#153d68;margin:5px 0}
.am254-head p{margin:4px 0 0;color:#5e7892;line-height:1.6;font-size:12px}
.am254-link{display:inline-flex;align-items:center;gap:6px;text-decoration:none;background:#e2effb;color:#16517f;padding:10px 13px;border-radius:8px;font-size:12px;font-weight:780;border:1px solid #bbd4e9}
.am254-card{background:#fff;border:1px solid #d6e3ef;border-radius:12px;padding:15px;margin:0 0 13px}
.am254-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.am254-section-head h2{font-size:15px;margin:0;color:#183f69}
.am254-tag{border-radius:16px;padding:5px 9px;font-size:10px;font-weight:850;background:#eaf4ff;color:#1e5687}
.am254-searcharea{position:relative;z-index:5}
.am254-searchinput{height:45px;width:100%;padding:10px 14px;border:1px solid #a9c4de;border-radius:9px;font-size:14px;color:#173a5b;background:#fff}
.am254-searchinput:focus{outline:2px solid #377fca;outline-offset:1px}
.am254-hint{font-size:11px;color:#607c94;margin:8px 0 0;line-height:1.5}
.am254-results{border:1px solid #bcd3e6;margin-top:8px;border-radius:10px;max-height:365px;overflow:auto;background:#fff}
.am254-results table{width:100%;min-width:980px;border-collapse:collapse}
.am254-results th{position:sticky;top:0;z-index:3;background:#1c4775;color:#fff;text-align:left;font-size:11px;padding:9px 10px;white-space:nowrap}
.am254-results td{padding:9px 10px;border-top:1px solid #e4edf5;vertical-align:top;font-size:11px}
.am254-results tr:nth-child(even){background:#f6faff}
.am254-results .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.am254-results small{display:block;color:#71839a;font-size:10px;margin-top:3px}
.am254-choice{background:#126497;color:#fff;border:none;border-radius:7px;padding:7px 11px;font-size:11px;font-weight:800;white-space:nowrap;cursor:pointer}
.am254-choice:disabled{background:#c4ced8;color:#596779;cursor:not-allowed}
.am254-results .unavailable{opacity:.65}
.am254-empty{padding:19px;color:#71839a;text-align:center;line-height:1.6}
.am254-cols{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:13px}
.am254-cols label{font-size:11px;font-weight:800;color:#4c6580;display:flex;flex-direction:column;gap:6px}
.am254-cols input,.am254-reason textarea{border:1px solid #c2d4e4;border-radius:8px;padding:10px 12px;font-size:12px;min-height:41px;width:100%;color:#183855}
.am254-reason{font-size:11px;color:#4c6580;font-weight:800;display:flex;flex-direction:column;gap:6px}
.am254-reason textarea{min-height:70px;resize:vertical;font-family:inherit}
.am254-table-wrap{overflow:auto;border:1px solid #d4e2ef;border-radius:10px}
.am254-table{width:100%;min-width:1130px;border-collapse:collapse}
.am254-table th{background:#153e6c;color:#fff;text-align:left;font-size:10px;padding:11px 9px;white-space:nowrap}
.am254-table td{padding:10px 9px;border-top:1px solid #e7edf5;vertical-align:top;font-size:11px}
.am254-table tbody tr:nth-child(even){background:#f8fbff}
.am254-table small{display:block;color:#73879a;font-size:10px;margin-top:4px;line-height:1.4}
.am254-table .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.am254-table input[type=number]{max-width:105px;min-height:38px;padding:8px;border:1px solid #adc6de;border-radius:7px;text-align:right;font-size:13px}
.am254-remove{background:#fff0f1;border:1px solid #e6c1c3;color:#a12b39;border-radius:7px;padding:8px 9px;cursor:pointer;font-weight:800}
.am254-foot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:14px;border-top:1px solid #e2ebf4;padding-top:13px}
.am254-foot strong{font-size:23px;color:#145584;font-variant-numeric:tabular-nums}
.am254-foot p{margin:3px 0;font-size:11px;color:#637f96}
.am254-save{background:#157456;border:0;border-radius:9px;padding:12px 18px;color:#fff;font-weight:850;font-size:13px;cursor:pointer;min-height:44px}
.am254-save:disabled{background:#9fb9ad;cursor:not-allowed}
.am254-note{padding:11px 13px;border-left:4px solid #dba34e;background:#fff9ed;color:#765220;border-radius:8px;font-size:11px;line-height:1.65}
.am254-selectedcount{font-size:11px;font-weight:750;color:#32618b}
.am254-status{color:#667e95;font-size:11px}
.am254-error{border:1px solid #f1c5ca;color:#98283a;background:#fff2f3;padding:11px 14px;border-radius:9px;margin:0 0 14px}
.am254-error ul{padding-left:20px;margin:5px 0 0}
@media(max-width:800px){.am254-cols{grid-template-columns:1fr}.am254-head h1{font-size:21px}}
</style>
<div class="am254-page">
  <div class="am254-head">
    <div>
      <div style="font-size:10px;font-weight:900;letter-spacing:.09em;color:#557796">ASSAM MOTORS / PURCHASE CONTROL</div>
      <h1>Item-wise Purchase Return</h1>
      <p>Part / OSL search karein → purchase history mein Vendor, Invoice, Job Card select karein → Return Qty enter karein.</p>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <a class="am254-link" href="{{ route('erp.purchases.index') }}">← Purchase Register</a>
      <a class="am254-link" href="{{ route('erp.purchase-returns.select-bill') }}">Original Bill se Return</a>
    </div>
  </div>
  @if($errors->any())
  <div class="am254-error"><strong>Please correct before saving:</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
  @endif
  <div class="am254-card">
    <div class="am254-section-head"><h2>1. Search Item / Purchase History</h2><span class="am254-tag">Search by Part, ROT/OSL, Vendor, Purchase Bill, Invoice, JC or Vehicle</span></div>
    <div class="am254-searcharea">
      <input id="am254-search" class="am254-searchinput" type="search"
       placeholder="Type item name or code — e.g. Engine Oil / Brake Pad / Purchase Invoice / JC..."
       autocomplete="off" aria-label="Search purchase items and original source history">
      <div class="am254-hint">Select the <strong>exact purchase line</strong> from the results. Same item may have been purchased from different Vendors or for different Job Cards; each source is shown separately.</div>
      <div id="am254-resultbox" class="am254-results" hidden></div>
      <div id="am254-search-status" class="am254-status" style="padding-top:8px" aria-live="polite"></div>
    </div>
  </div>
  <form id="am254-entry-form" method="post" action="{{ route('erp.purchase-returns.itemwise.store') }}">
    @csrf
    <input type="hidden" name="request_key" value="{{ $requestKey }}">
    <div class="am254-card">
      <div class="am254-section-head"><h2>2. Selected Return Items</h2><span id="am254-item-count" class="am254-selectedcount">0 items selected</span></div>
      <div class="am254-table-wrap">
        <table class="am254-table">
          <thead><tr><th>SL</th><th>Part / OSL</th><th>Vendor / Purchase Invoice</th><th>Job Card / Vehicle / Customer Invoice</th><th class="num">Purchased</th><th class="num">Earlier Return</th><th class="num">Available</th><th class="num">Rate</th><th class="num">GST%</th><th>Return Qty</th><th class="num">Approx. Credit</th><th>Remove</th></tr></thead>
          <tbody id="am254-lines"><tr id="am254-empty"><td colspan="12" style="text-align:center;padding:30px;color:#6e8398">Search an item above, then click <strong>Select</strong> against its Vendor / Purchase Invoice / Job Card.</td></tr></tbody>
        </table>
      </div>
      <div class="am254-foot">
        <div><div class="am254-status">Estimated Return Credit (final GST & round-off server-calculated)</div><strong id="am254-total">₹0.00</strong><p id="am254-document-count">Each original supplier bill gets its own audited Purchase Return document.</p></div>
        <span class="am254-status">All selected items are checked again on submission.</span>
      </div>
    </div>
    <div class="am254-card">
      <div class="am254-section-head"><h2>3. Return Document Details</h2><span class="am254-tag">Mandatory: Date and Reason</span></div>
      <div class="am254-cols">
        <label>Return Date
          <input type="date" name="return_date" value="{{ old('return_date',$today) }}" max="{{ $today }}" required>
        </label>
        <label>Supplier Credit Note Reference (optional)
          <input name="supplier_credit_note" value="{{ old('supplier_credit_note') }}" maxlength="120" placeholder="Vendor credit note / acknowledgement number">
        </label>
        <label>Purchase Return Format
          <input readonly value="Supplier Debit Note · Item-wise Source Selection">
        </label>
      </div>
      <label class="am254-reason">Reason / Narration
        <textarea name="reason" minlength="10" maxlength="1500" required placeholder="Example: Defective brake pads returned to supplier against purchase invoice dated ...">{{ old('reason') }}</textarea>
      </label>
      <div class="am254-note" style="margin-top:13px">
        One entry may select items bought from different purchase bills/vendors. The ERP creates a <strong>separate Return document for each original Vendor + Purchase Bill</strong>, together in one database transaction.
        Only available quantity and unpaid supplier liability may be returned. Stock-issued/fitted items must first be reversed on their Job Card. Original purchases remain unchanged.
      </div>
      <div class="am254-foot">
        <div class="am254-status">Original Vendor · Purchase Invoice · Job Card links are retained for each selected source line.</div>
        <button id="am254-submit" class="am254-save" type="submit" disabled>Post Purchase Return</button>
      </div>
    </div>
  </form>
</div>
<script>
(function(){
  "use strict";
  var search=document.getElementById('am254-search');
  var resultBox=document.getElementById('am254-resultbox');
  var status=document.getElementById('am254-search-status');
  var form=document.getElementById('am254-entry-form');
  var tbody=document.getElementById('am254-lines');
  var submit=document.getElementById('am254-submit');
  var selected=new Map(),count=0,timer,controller=null,latest=[];
  var endpoint=@json(route('erp.purchase-returns.search'));
  var fmt=function(n){return Number(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})};
  function td(text,cls){var el=document.createElement('td');if(cls)el.className=cls;el.textContent=text;return el;}
  function meta(texts){
    var tdEl=document.createElement('td');
    texts.forEach(function(t,i){
      var el=document.createElement(i===0?'strong':'small');
      el.textContent=t||'—';tdEl.appendChild(el);
    });return tdEl;
  }
  function update(){
    var sum=0,groups=new Set();
    tbody.querySelectorAll('tr[data-return-line]').forEach(function(tr){
      var d=selected.get(String(tr.dataset.id));if(!d)return;
      var input=tr.querySelector('input[type=number]');
      var q=Number(input.value||0),allowed=Number(input.max||0);
      input.setCustomValidity(q>allowed?'Return Qty exceeds available source/stock.':'');
      var amount=q>0 && q<=allowed && Number(d.purchased_qty)>0
       ? Math.round((Number(d.line_amount)*q/Number(d.purchased_qty))*100)/100:0;
      sum+=amount;
      tr.querySelector('[data-credit]').textContent='−₹'+fmt(amount);
      groups.add(String(d.vendor_id)+'|'+d.purchase_type+'|'+d.purchase_bill);
    });
    document.getElementById('am254-item-count').textContent=selected.size+' items selected';
    document.getElementById('am254-total').textContent='₹'+fmt(sum);
    document.getElementById('am254-document-count').textContent=groups.size+
      ' supplier return document(s), each linked to its original Purchase Bill';
    submit.disabled=selected.size===0;
  }
  function add(d){
    var key=String(d.purchase_id);
    if(selected.has(key)){
      var row=tbody.querySelector('tr[data-id="'+key+'"]');
      if(row){row.querySelector('input[type=number]').focus();row.scrollIntoView({block:'nearest'});}
      status.textContent='This source Purchase line is already in your Return Items.';
      return;
    }
    var available=Number(d.remaining_qty||0);
    var stock=d.physical_stock===null?available:Math.min(available,Number(d.physical_stock));
    if(stock<=0){status.textContent='This source has no available returnable stock/quantity.';return;}
    var empty=document.getElementById('am254-empty');if(empty)empty.remove();
    selected.set(key,d);
    var row=document.createElement('tr');row.dataset.returnLine='1';row.dataset.id=key;
    var id=++count;
    row.appendChild(td(String(selected.size)));
    row.appendChild(meta([d.item_name,d.item_code||d.purchase_type,'Purchase '+d.purchase_bill+' · '+d.purchase_date]));
    row.appendChild(meta([d.vendor_name,'Supplier invoice: '+(d.supplier_invoice||'Not recorded')]));
    row.appendChild(meta([d.job_no||'STOCK', d.vehicle_no||'No vehicle', d.customer_invoice?'Customer invoice: '+d.customer_invoice:'No customer invoice']));
    row.appendChild(td(Number(d.purchased_qty).toFixed(3),'num'));
    row.appendChild(td(Number(d.already_returned_qty).toFixed(3),'num'));
    row.appendChild(td(Number(available).toFixed(3),'num'));
    row.appendChild(td('₹'+fmt(d.unit_cost),'num'));
    row.appendChild(td(Number(d.gst_percent||0).toFixed(2)+'%','num'));
    var qty=document.createElement('td');
    var hidden=document.createElement('input');hidden.type='hidden';hidden.name='lines['+id+'][purchase_id]';hidden.value=key;qty.appendChild(hidden);
    var input=document.createElement('input');input.type='number';input.step='0.001';input.min='0.001';input.max=stock.toFixed(3);input.value='';input.required=true;
    input.name='lines['+id+'][qty]';input.setAttribute('aria-label','Return quantity for '+d.item_name+' from '+d.vendor_name);
    input.addEventListener('input',update);qty.appendChild(input);
    var note=document.createElement('small');note.textContent='Max '+stock.toFixed(3)+' · linked Vendor/Bill';qty.appendChild(note);
    row.appendChild(qty);
    var credit=td('−₹0.00','num');credit.setAttribute('data-credit','1');row.appendChild(credit);
    var remove=document.createElement('td');var button=document.createElement('button');
    button.type='button';button.className='am254-remove';button.textContent='Remove';
    button.addEventListener('click',function(){
      selected.delete(key);row.remove();
      if(selected.size===0){
        var emptyRow=document.createElement('tr');emptyRow.id='am254-empty';
        var emptyTd=document.createElement('td');emptyTd.colSpan=12;emptyTd.style.textAlign='center';emptyTd.style.padding='30px';
        emptyTd.textContent='Search and select a purchased item above to add a Return line.';
        emptyRow.appendChild(emptyTd);tbody.appendChild(emptyRow);
      }
      update();
    });
    remove.appendChild(button);row.appendChild(remove);
    tbody.appendChild(row);
    resultBox.hidden=true;resultBox.textContent='';search.value='';status.textContent=d.item_name+' from '+d.vendor_name+' added. Search the next item.';
    update();input.focus();
  }
  function render(items){
    latest=items;
    resultBox.textContent='';
    resultBox.hidden=false;
    if(!items.length){
      var e=document.createElement('div');e.className='am254-empty';
      e.textContent='No matching original purchase lines. Try the item code, Vendor, Supplier Invoice, JC or Vehicle No.';
      resultBox.appendChild(e);return;
    }
    var table=document.createElement('table'),thead=document.createElement('thead'),tr=document.createElement('tr');
    ['Part / OSL','Vendor','Purchase Bill / Invoice','Job / Vehicle / Invoice','Purchased','Earlier Return','Available','Rate / GST','Select'].forEach(function(x){
      var th=document.createElement('th');th.textContent=x;tr.appendChild(th);
    });
    thead.appendChild(tr);table.appendChild(thead);
    var body=document.createElement('tbody');
    items.forEach(function(d){
      var row=document.createElement('tr');
      if(!d.selectable)row.className='unavailable';
      row.appendChild(meta([d.item_name,d.item_code||d.purchase_type]));
      row.appendChild(meta([d.vendor_name,'Vendor ID '+d.vendor_id]));
      row.appendChild(meta([d.purchase_bill,d.purchase_date,'Supplier Inv: '+(d.supplier_invoice||'—')]));
      row.appendChild(meta([d.job_no||'STOCK',d.vehicle_no||'',d.customer_invoice?'Customer Invoice: '+d.customer_invoice:'']));
      row.appendChild(td(Number(d.purchased_qty).toFixed(3),'num'));
      row.appendChild(td(Number(d.already_returned_qty).toFixed(3),'num'));
      row.appendChild(meta([Number(d.remaining_qty).toFixed(3),d.physical_stock===null?'OSL':('Stock '+Number(d.physical_stock).toFixed(3))]));
      row.appendChild(meta(['₹'+fmt(d.unit_cost),Number(d.gst_percent||0).toFixed(2)+'% GST']));
      var target=document.createElement('td'),btn=document.createElement('button');
      btn.type='button';btn.className='am254-choice';btn.textContent=selected.has(String(d.purchase_id))?'Selected':'Select';
      btn.disabled=!d.selectable;
      btn.addEventListener('click',function(){add(d);});
      target.appendChild(btn);row.appendChild(target);body.appendChild(row);
    });
    table.appendChild(body);resultBox.appendChild(table);
    status.textContent=items.length+' matching purchase source lines; select the correct vendor/invoice/JC.';
  }
  async function lookup(){
    var q=search.value.trim();
    if(controller)controller.abort();
    if(q.length<2){resultBox.hidden=true;status.textContent='Type at least 2 characters to search purchase history.';return;}
    controller=new AbortController();var active=controller;
    status.textContent='Searching purchase history...';
    try{
      var response=await fetch(endpoint+'?q='+encodeURIComponent(q),{
        signal:active.signal,credentials:'same-origin',headers:{'Accept':'application/json'}
      });
      if(!response.ok)throw new Error('Search failed (HTTP '+response.status+').');
      var data=await response.json();
      if(active!==controller)return;
      render(data.items||[]);
    }catch(e){
      if(e.name==='AbortError')return;
      status.textContent='Purchase search could not load. '+e.message;
      resultBox.hidden=true;
    }
  }
  search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(lookup,210);});
  search.addEventListener('keydown',function(e){
    if(e.key==='Escape'){resultBox.hidden=true;return;}
    if(e.key==='Enter'){
      e.preventDefault();
      var item=latest.find(function(x){return x.selectable&&!selected.has(String(x.purchase_id));});
      if(!resultBox.hidden && item)add(item);else lookup();
    }
  });
  form.addEventListener('submit',function(e){
    if(selected.size===0){e.preventDefault();status.textContent='Select at least one original purchased item.';search.focus();return;}
    if(!confirm('Post item-wise Purchase Return? Each original Vendor/Purchase Bill will receive its own debit note and stock / payable amounts will be updated.'))e.preventDefault();
  });
  status.textContent='Type two or more characters to find original purchase records.';
})();
</script>
@endsection
