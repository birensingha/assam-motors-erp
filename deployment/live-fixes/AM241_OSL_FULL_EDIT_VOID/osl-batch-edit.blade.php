@extends('layouts.erp')
@section('title','Edit OSL Purchase · ASSAM MOTORS ERP')
@section('content')
<style>
/* AM241_OSL_FULL_EDIT_VOID: styled to match Parts Purchase Edit. */
.am241-wrap{max-width:100%;margin:0 auto;color:#183751}
.am241-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 12px;border-bottom:1px solid #d8e4f1;padding-bottom:13px}
.am241-head h1{font-size:21px;margin:0;color:#0e2d50}
.am241-head small{font-size:11px;color:#657b91;display:block;margin:4px 0}
.am241-actions{display:flex;gap:8px;flex-wrap:wrap}
.am241-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;font-size:12px;font-weight:750;border:1px solid #bccfe4;color:#18426e;background:#fff;border-radius:7px;padding:8px 12px;cursor:pointer;min-height:34px}
.am241-btn.blue{background:#1255a5;color:#fff;border-color:#1255a5}
.am241-btn.green{background:#087c54;color:#fff;border-color:#087c54}
.am241-btn.red{background:#b42338;color:#fff;border-color:#b42338}
.am241-btn:disabled{opacity:.5;cursor:not-allowed}
.am241-card{background:white;border:1px solid #d5e1ee;border-radius:11px;padding:15px;margin-bottom:16px}
.am241-note{border:1px solid #eac985;background:#fff8e8;color:#674814;padding:11px 13px;border-radius:8px;font-size:12px;margin-bottom:14px}
.am241-error{background:#fff0ef;color:#a92727;border:1px solid #e4aaa5;border-radius:9px;padding:12px;margin-bottom:14px;font-size:12px}
.am241-grid{display:grid;grid-template-columns:2fr 1fr 1.1fr;gap:10px;margin:10px 0 16px}
.am241-grid label,.am241-note-field label{display:block;font-size:11px;font-weight:800;color:#34546f}
.am241-grid input,.am241-grid select,.am241-note-field textarea{margin-top:4px;width:100%;height:34px;box-sizing:border-box;border:1px solid #becfe0;border-radius:6px;background:#fff;padding:6px 8px;color:#1c3854;font:inherit;font-size:12px}
.am241-note-field textarea{height:auto;min-height:72px;resize:vertical}
.am241-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:15px 0 8px}
.am241-toolbar strong{font-size:13px}
.am241-toolbar span{color:#657c92;font-size:11px}
.am241-scroller{overflow-x:auto;max-width:100%;border:1px solid #dce5f0;border-radius:8px;background:#fff;padding:6px}
.am241-table{width:100%;min-width:1390px;border-collapse:collapse;table-layout:fixed;font-size:11px}
.am241-table th{height:31px;padding:6px 5px;background:#eaf2fa;text-transform:uppercase;letter-spacing:.02em;text-align:center;white-space:nowrap;border-bottom:1px solid #d2deeb;font-size:10px}
.am241-table td{padding:5px 4px;vertical-align:middle;border-bottom:1px solid #edf1f6}
.am241-table td input,.am241-table td select{height:31px;width:100%;min-width:0;max-width:100%;font-size:11px;box-sizing:border-box;border:1px solid #c4d2e0;border-radius:5px;background:white;color:#1b3b5d;padding:4px 6px}
.am241-table td input[readonly]{background:#eef4f9;color:#46627b}
.am241-table .am241-line-jc{font-weight:750;display:block;max-height:40px;overflow:auto;color:#21496e}
.am241-table .am241-line-jc small{font-weight:normal;color:#66839e}
.am241-table .am241-line-total{display:block;text-align:right;font-weight:850;color:#123b69;white-space:nowrap}
.am241-table .am241-remove{width:26px;height:26px;border-radius:50%;border:1px solid #eab0ab;background:#fff4f2;color:#b82323;cursor:pointer;font-size:18px;line-height:20px}
.am241-bill-footer{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;margin-top:16px}
.am241-summary{border:1px solid #d3dfee;background:#f9fbfe;border-radius:8px;overflow:hidden}
.am241-summary>div{padding:9px 12px;display:flex;justify-content:space-between;border-bottom:1px solid #e3eaf2;font-size:12px}
.am241-summary .am241-grand{background:#eaf3ff;color:#133e6a;font-size:16px;font-weight:850}
.am241-save{display:flex;justify-content:flex-end;gap:10px;margin-top:12px}
.am241-void{border:1px solid #e9b9b9;border-radius:10px;background:#fff7f7;padding:14px;margin-top:15px}
.am241-void h3{font-size:14px;color:#a0222d;margin:0 0 8px}
.am241-void p{font-size:12px;color:#75545b;margin:6px 0 9px}
.am241-void textarea{width:100%;min-height:60px;border:1px solid #deaeb6;border-radius:6px;padding:8px;box-sizing:border-box;font:inherit;font-size:12px}
.am241-locked{display:inline-block;background:#fff1cf;border:1px solid #efcd8d;border-radius:6px;padding:7px;color:#774b0a;font-size:12px}
.am241-muted{color:#698099;font-size:11px}
@media(max-width:900px){.am241-grid{grid-template-columns:1fr 1fr}.am241-bill-footer{grid-template-columns:1fr}}
@media(max-width:600px){.am241-grid{grid-template-columns:1fr}.am241-head h1{font-size:19px}}
</style>
<div class="am241-wrap">
  <div class="am241-head">
    <div>
      <h1>Edit OSL Purchase</h1>
      <small>{{ $batchNo ?: $anchor->purchase_no }} · {{ count($lines) }} existing OSL line(s) · Supplier bill correction</small>
    </div>
    <div class="am241-actions">
      <a class="am241-btn" href="{{ route('erp.purchases.show',$anchor->id) }}">← Cancel / View</a>
      <a class="am241-btn" href="{{ route('erp.purchases.index') }}">Purchase Register</a>
    </div>
  </div>
  @if($errors->any())
    <div class="am241-error" role="alert"><strong>Please correct:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  @if(session('success'))<div class="am241-note">{{ session('success') }}</div>@endif
  <div class="am241-note">
    <strong>Safe audited OSL Purchase editing:</strong>
    Supplier bill ke existing Purchase No aur JC billing link retain honge. Qty/Rate/GST edits original purchase ko duplicate nahi karenge.
    JC customer billing rate is form se silently change nahi hoga; uska edit linked Job Card mein hoga.
  </div>
  @if($blocked)
    <div class="am241-error" role="alert"><strong>Financial editing locked:</strong> {{ $blocked }} <p>Is purchase ko review/print kar sakte hain, lekin direct Save ya Delete allowed nahi hai.</p></div>
  @endif
  <form method="post" action="{{ route('erp.purchases.osl-batch.update',$anchor->id) }}" id="am241-edit-form" class="am241-card">
    @csrf
    @method('PUT')
    <input type="hidden" name="expected_total" value="{{ number_format($oldTotals,2,'.','') }}">
    <fieldset style="margin:0;padding:0;border:0;min-width:0" @if($blocked) disabled @endif>
      <div class="am241-grid">
        <label>Vendor *<select name="vendor_id" required>
          @foreach($vendors as $vendor)
            <option value="{{ $vendor->id }}" @selected((int)old('vendor_id',$anchor->vendor_id)===(int)$vendor->id)>{{ $vendor->vendor_code }} — {{ $vendor->vendor_name }}</option>
          @endforeach
        </select></label>
        <label>Purchase Date *<input type="date" name="purchase_date" value="{{ old('purchase_date',substr((string)$anchor->purchase_date,0,10)) }}" required></label>
        <label>Supplier Invoice<input name="supplier_invoice" maxlength="100" value="{{ old('supplier_invoice',$anchor->supplier_invoice) }}" placeholder="Invoice / Bill No"></label>
      </div>
      <div class="am241-toolbar">
        <div><strong>OSL Line Items</strong> <span>Vendor costing editable; customer billing remains under JC control</span></div>
        <button type="button" class="am241-btn blue" id="am241-add-line" @if($batchNo==='') disabled title="Legacy purchase has no batch ID" @endif>＋ Add Line</button>
      </div>
      <div class="am241-scroller">
        <table class="am241-table">
          <colgroup>
            <col style="width:32px"><col style="width:210px"><col style="width:148px"><col style="width:218px">
            <col style="width:58px"><col style="width:85px"><col style="width:68px"><col style="width:130px">
            <col style="width:62px"><col style="width:88px"><col style="width:86px"><col style="width:92px"><col style="width:38px">
          </colgroup>
          <thead><tr><th>#</th><th>JC / Vehicle</th><th>OSL Master</th><th>Work Description</th><th>Qty</th><th>Vendor Rate</th><th>Disc %</th><th>Supplier GST Basis</th><th>GST %</th><th>Cust. Rate</th><th>Cust. GST</th><th>Purchase ₹</th><th></th></tr></thead>
          <tbody id="am241-lines">
          @foreach($lines as $i=>$line)
            @php
              $job=$allJobs->get((int)$line->job_card_id);
              $billing=$labour->firstWhere('id',(int)$line->labour_line_id);
              $gstMode=strtoupper((string)($line->gst_mode ?: 'EXTRA'));
            @endphp
            <tr class="am241-line" data-existing="1">
              <td class="am241-number">{{ $i+1 }}</td>
              <td>
                <input type="hidden" name="lines[{{ $i }}][purchase_id]" value="{{ $line->id }}">
                <input type="hidden" name="lines[{{ $i }}][job_card_id]" value="{{ $line->job_card_id }}">
                <input type="hidden" name="lines[{{ $i }}][labour_line_id]" value="{{ $line->labour_line_id }}">
                <span class="am241-line-jc">{{ $job->job_no ?? 'JC #'.$line->job_card_id }}
                  <small>{{ $job->vehicle_reg_no ?? '' }} · {{ $job->customer_name ?? '' }}</small>
                </span>
                <small class="am241-muted">Linked OSL #{{ $line->labour_line_id }}</small>
              </td>
              <td><select name="lines[{{ $i }}][osl_master_id]">
                <option value="">Manual Description</option>
                @foreach($masters as $master)
                  <option value="{{ $master->id }}" @selected((int)$line->osl_master_id===(int)$master->id)>{{ $master->osl_code }} — {{ $master->osl_name }}</option>
                @endforeach
              </select></td>
              <td><input name="lines[{{ $i }}][description]" required maxlength="255" value="{{ old('lines.'.$i.'.description',$line->description) }}"></td>
              <td><input type="number" name="lines[{{ $i }}][qty]" class="am241-qty" step="0.001" min="0.001" required value="{{ old('lines.'.$i.'.qty',$line->qty) }}"></td>
              <td><input type="number" name="lines[{{ $i }}][unit_cost]" class="am241-rate" step="0.01" min="0" required value="{{ old('lines.'.$i.'.unit_cost',$line->unit_cost) }}"></td>
              <td><input type="number" name="lines[{{ $i }}][discount_percent]" class="am241-disc" step="0.01" min="0" max="100" required value="{{ old('lines.'.$i.'.discount_percent',$line->discount_percent) }}"></td>
              <td><select name="lines[{{ $i }}][gst_mode]" class="am241-mode">
                @foreach(['EXTRA'=>'GST Extra','INCLUDED'=>'GST Included','NONE'=>'GST NA'] as $mode=>$label)
                  <option value="{{ $mode }}" @selected($gstMode===$mode)>{{ $label }}</option>
                @endforeach
              </select></td>
              <td><input type="number" name="lines[{{ $i }}][gst_percent]" class="am241-gst" step="0.01" min="0" max="100" value="{{ old('lines.'.$i.'.gst_percent',$line->gst_percent) }}" required></td>
              <td><input type="number" readonly aria-label="JC customer rate (change in Job Card)" value="{{ number_format((float)($billing->labour_rate??$line->customer_rate),2,'.','') }}"></td>
              <td><input type="number" readonly aria-label="JC customer GST (change in Job Card)" value="{{ number_format((float)($billing->gst_percent??$line->customer_gst_percent),2,'.','') }}"></td>
              <td><strong class="am241-line-total">₹{{ number_format((float)$line->total_amount,2) }}</strong></td>
              <td><button type="button" class="am241-remove" aria-label="Remove purchase line" title="Remove line (audited)">×</button></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="am241-bill-footer">
        <div class="am241-note-field">
          <label>Narration / Remarks<textarea name="notes" maxlength="800" placeholder="Supplier invoice narration / transport remarks...">{{ old('notes',$anchor->notes) }}</textarea></label>
          <label style="margin-top:12px">Correction Reason *<textarea name="reason" required minlength="6" maxlength="1000" placeholder="Specify the mistake and why the purchase needs correction.">{{ old('reason') }}</textarea></label>
          <p class="am241-muted">Removing a purchase line does not automatically remove its JC customer OSL billing. Reconcile JC separately if required.</p>
        </div>
        <div>
          <div class="am241-summary">
            <div><span>Gross Amount</span><strong id="am241-gross">₹0.00</strong></div>
            <div><span>Discount</span><strong id="am241-discount">₹0.00</strong></div>
            <div><span>Taxable Amount</span><strong id="am241-taxable">₹0.00</strong></div>
            <div><span>GST Amount</span><strong id="am241-gsttotal">₹0.00</strong></div>
            <div><span>Before Round Off</span><strong id="am241-preround">₹0.00</strong></div>
            <div><span>Round Off</span><strong id="am241-roundoff">₹0.00</strong></div>
            <div class="am241-grand"><span>Grand Total</span><strong id="am241-grand">₹0.00</strong></div>
          </div>
          <div class="am241-save">
            <a class="am241-btn" href="{{ route('erp.purchases.show',$anchor->id) }}">Cancel</a>
            <button class="am241-btn green" type="submit" @if($blocked) disabled @endif>💾 Save OSL Purchase</button>
          </div>
        </div>
      </div>
    </fieldset>
  </form>
  <div class="am241-void">
    <h3>Delete / Void OSL Purchase</h3>
    <p>Purchase poore bill ki saari lines audited VOID hongi. Linked Job Card customer OSL billing ya posted payments automatically delete nahi honge. Paid/Invoiced/Closed references par Void blocked rahega.</p>
    <form method="post" action="{{ route('erp.purchases.osl-batch.void',$anchor->id) }}" id="am241-void-form">
      @csrf @method('DELETE')
      <input type="hidden" name="expected_total" value="{{ number_format($oldTotals,2,'.','') }}">
      <label style="display:block;font-size:12px;font-weight:700">Deletion Reason *<textarea name="void_reason" required minlength="8" maxlength="1000" placeholder="Reason for voiding this entire OSL supplier purchase..." @if($blocked) disabled @endif></textarea></label>
      <div class="am241-save">
        @if($blocked)<span class="am241-locked">Void blocked: linked payment or closed/invoiced JC</span>@endif
        <button type="submit" class="am241-btn red" @if($blocked) disabled @endif>🗑 Delete / Void Purchase</button>
      </div>
    </form>
  </div>
</div>
<template id="am241-new-line">
  <tr class="am241-line" data-existing="0">
    <td class="am241-number"></td>
    <td>
      <input type="hidden" name="lines[__INDEX__][job_card_id]" class="am241-job-id">
      <select name="lines[__INDEX__][labour_line_id]" class="am241-billing-choice" required>
        <option value="">Select JC OSL billing line</option>
        @foreach($labour as $l)
          @php $j=$allJobs->get((int)$l->job_card_id); @endphp
          <option value="{{ $l->id }}" data-job="{{ $l->job_card_id }}" data-description="{{ $l->description }}">
          {{ $j->job_no ?? 'JC #'.$l->job_card_id }} — {{ $j->vehicle_reg_no ?? '' }} — {{ $l->description }}
          </option>
        @endforeach
      </select>
      <small class="am241-muted">Select unpurchased JC OSL work</small>
    </td>
    <td><select name="lines[__INDEX__][osl_master_id]"><option value="">Manual Description</option>
      @foreach($masters as $master)<option value="{{ $master->id }}">{{ $master->osl_code }} — {{ $master->osl_name }}</option>@endforeach
    </select></td>
    <td><input name="lines[__INDEX__][description]" required maxlength="255"></td>
    <td><input type="number" name="lines[__INDEX__][qty]" class="am241-qty" step="0.001" min="0.001" value="1" required></td>
    <td><input type="number" name="lines[__INDEX__][unit_cost]" class="am241-rate" step="0.01" min="0" value="0" required></td>
    <td><input type="number" name="lines[__INDEX__][discount_percent]" class="am241-disc" step="0.01" min="0" max="100" value="0" required></td>
    <td><select name="lines[__INDEX__][gst_mode]" class="am241-mode"><option value="EXTRA">GST Extra</option><option value="INCLUDED">GST Included</option><option value="NONE">GST NA</option></select></td>
    <td><input type="number" name="lines[__INDEX__][gst_percent]" class="am241-gst" step="0.01" min="0" max="100" value="0" required></td>
    <td><input readonly class="am241-customer-rate" value="JC"></td>
    <td><input readonly class="am241-customer-gst" value="JC"></td>
    <td><strong class="am241-line-total">₹0.00</strong></td>
    <td><button type="button" class="am241-remove" aria-label="Remove purchase line">×</button></td>
  </tr>
</template>
<script>
(function(){
'use strict';
const form=document.getElementById('am241-edit-form');
const body=document.getElementById('am241-lines');
const add=document.getElementById('am241-add-line');
if(!form||!body)return;
let next=1000;
const labour=@json($labour->map(fn($x)=>['id'=>$x->id,'job'=>$x->job_card_id,'description'=>$x->description,'rate'=>$x->labour_rate,'gst'=>$x->gst_percent])->values());
const find=(line,cls)=>line.querySelector(cls);
const n=(line,cls)=>Number(find(line,cls)?.value)||0;
const r2=x=>Math.round((x+Number.EPSILON)*100)/100;
const money=x=>'₹'+(Number(x)||0).toFixed(2);
function calculate(){
  let gross=0,discount=0,taxable=0,gst=0,pre=0;
  const rows=[...body.querySelectorAll('tr.am241-line')];
  rows.forEach((row,i)=>{
    row.querySelector('.am241-number').textContent=String(i+1);
    const qty=Math.max(0,n(row,'.am241-qty')),rate=Math.max(0,n(row,'.am241-rate'));
    const pct=Math.max(0,Math.min(100,n(row,'.am241-disc')));
    const mode=find(row,'.am241-mode')?.value||'EXTRA';
    const gstInput=find(row,'.am241-gst');
    if(mode==='NONE'&&gstInput){gstInput.value='0';gstInput.readOnly=true;}
    else if(gstInput)gstInput.readOnly=false;
    const gpct=mode==='NONE'?0:Math.max(0,n(row,'.am241-gst'));
    const g=r2(qty*rate),disc=r2(g*pct/100),net=r2(g-disc);
    const t=mode==='INCLUDED'&&gpct>0?r2(net/(1+gpct/100)):net;
    const tax=mode==='INCLUDED'?r2(net-t):mode==='EXTRA'?r2(t*gpct/100):0;
    const total=r2(t+tax);find(row,'.am241-line-total').textContent=money(total);
    gross+=g;discount+=disc;taxable+=t;gst+=tax;pre+=total;
  });
  gross=r2(gross);discount=r2(discount);taxable=r2(taxable);gst=r2(gst);pre=r2(pre);
  const grand=Math.round(pre),off=r2(grand-pre);
  for(const [id,val] of [['gross',gross],['discount',discount],['taxable',taxable],['gsttotal',gst],['preround',pre],['roundoff',off],['grand',grand]]){
    const el=document.getElementById('am241-'+id);if(el)el.textContent=money(val);
  }
}
body.addEventListener('input',calculate);body.addEventListener('change',e=>{
 if(e.target.matches('.am241-billing-choice')){
   const opt=e.target.selectedOptions[0];
   const row=e.target.closest('tr');
   row.querySelector('.am241-job-id').value=opt?.dataset.job||'';
   const desc=row.querySelector('[name$="[description]"]');
   if(desc&&!desc.value)desc.value=opt?.dataset.description||'';
   const match=labour.find(x=>String(x.id)===String(e.target.value));
   if(match){
     row.querySelector('.am241-customer-rate').value=Number(match.rate||0).toFixed(2);
     row.querySelector('.am241-customer-gst').value=Number(match.gst||0).toFixed(2);
   }
 }
 calculate();
});
body.addEventListener('click',e=>{
 const b=e.target.closest('.am241-remove');
 if(!b)return;
 if(body.querySelectorAll('tr.am241-line').length<=1){alert('At least one OSL line is required. Use Delete / Void Purchase to remove the whole bill.');return;}
 if(!confirm('Remove this supplier purchase line from the bill? Change will be audited on Save. JC billing remains separately linked.'))return;
 b.closest('tr').remove();calculate();
});
add?.addEventListener('click',()=>{
 if(body.querySelectorAll('tr.am241-line').length>=50)return;
 const el=document.createElement('tbody');
 el.innerHTML=document.getElementById('am241-new-line').innerHTML.replaceAll('__INDEX__',String(next++));
 const row=el.firstElementChild;
 body.appendChild(row);
 calculate();
 row.querySelector('.am241-billing-choice')?.focus();
});
form.addEventListener('submit',e=>{
 if(!body.querySelectorAll('tr.am241-line').length){e.preventDefault();alert('At least one OSL line is required.');return;}
 if(!form.querySelector('[name="reason"]').value.trim()){e.preventDefault();alert('Correction Reason mandatory.');}
});
const voidForm=document.getElementById('am241-void-form');
voidForm?.addEventListener('submit',e=>{
 const reason=voidForm.querySelector('[name="void_reason"]')?.value.trim()||'';
 if(reason.length<8){e.preventDefault();alert('Delete/Void ke liye valid reason required.');return;}
 if(!confirm('Confirm VOID of ENTIRE OSL Purchase bill? This action is audited and cannot simply be undone.'))e.preventDefault();
});
calculate();
})();
</script>
@endsection
