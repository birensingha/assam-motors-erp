{{-- AM123: mount the existing audited P55 correction form inside the Main Job Card.
     Admin only. AM116 automatically rehomes each P55 row to a per-ROT dialog.
     Original Approval, Invoice, GST and Audit guards are unchanged. --}}
@php
    $am123Actor=request()->user();
    $am123Admin=$am123Actor && (bool)($am123Actor->active??false)
        && strtolower(trim((string)($am123Actor->role??'')))==='admin';
    $am123Rows=collect($am83RotLabour??[]);
    $am123Wip=in_array(strtolower(trim((string)($job->status??''))),
        ['wip','in progress','in-progress','work in progress'],true);
@endphp
@if($am123Admin && $am123Rows->isNotEmpty())
  <div id="am123-correction-forms" hidden>
    @include('job-cards.rot-alteration', [
      'p55Rows'=>$am123Rows,
      'p55InvoiceLocked'=>!empty($job->invoice_id),
      'p55OperationalLocked'=>!$am123Wip,
    ])
  </div>
@endif
@once
<style id="AM123_UNIFIED_ROT_WORKSPACE">
.jc-labour-block .rot-card .am123-fields{
  display:block;margin:7px 0 5px;padding:8px 10px;
  border:1px solid #d1e0ef;border-radius:8px;background:#f8fbff;
}
.jc-labour-block .rot-card .am123-fields>summary{
  cursor:pointer;color:#164b79;font-weight:800;font-size:11px;
  user-select:none;list-style:auto;line-height:1.4;
}
.jc-labour-block .rot-card .am123-fields[open]{
  background:#fff;border-color:#a6c8e4;
}
.jc-labour-block .rot-card .am123-fields .am82-finance-form{
  display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;
  gap:8px!important;margin:10px 0 1px!important;
  padding:0!important;width:100%!important;
  visibility:visible!important;position:static!important;
}
.jc-labour-block .rot-card .am123-fields .am82-finance-form label{
  min-width:0!important;font-size:10px!important;margin:0!important;
}
.jc-labour-block .rot-card .am123-fields .am82-finance-form input{
  width:100%!important;min-width:0!important;height:32px!important;
  font-size:11px!important;box-sizing:border-box!important;
}
.jc-labour-block .rot-card .am123-fields .am82-finance-form .am82-note{
  grid-column:span 3!important;
}
.jc-labour-block .rot-card .am123-fields .am82-finance-form .am82-actions{
  grid-column:span 1!important;align-self:end!important;
}
.jc-labour-block .rot-card .am123-qty-notice{
  font-size:10px;line-height:1.4;padding:6px 8px;margin:8px 0;
  background:#fff8e9;border:1px solid #efd6a8;border-radius:5px;color:#805a24;
}
.jc-labour-block .rot-card .am116-edit-btn{
  min-height:28px!important;font-size:10px!important;padding:5px 9px!important;
}
.jc-labour-block .rot-card .am123-correction-alert{
  color:#97542e;font-size:10px;margin:7px 0;
}
@media(max-width:800px){
 .jc-labour-block .rot-card .am123-fields .am82-finance-form{
   grid-template-columns:repeat(2,minmax(0,1fr))!important;
 }
 .jc-labour-block .rot-card .am123-fields .am82-finance-form .am82-note,
 .jc-labour-block .rot-card .am123-fields .am82-finance-form .am82-actions{
   grid-column:span 2!important;
 }
}
</style>
<script>
(function(){
'use strict';
function label(button){
  if(!button)return;
  button.textContent='Admin Full ROT Correction';
  button.title='ROT/Mechanic/Start/End/Std Hours/Rate/Discount correction with reason and audit';
}
function enhance(){
  var list=document.querySelector('.jc-labour-block .rot-list');
  if(!list)return;
  list.querySelectorAll('.rot-card[data-am90-labour-id]').forEach(function(card){
    if(card.querySelector('.am123-fields'))return;
    // Reuse the SAME actual backend billing form (no duplicate form, no
    // temporary estimate, no forged save action).
    var form=card.querySelector('form.am82-finance-form');
    if(form){
      var details=document.createElement('details');
      details.className='am123-fields';
      var summary=document.createElement('summary');
      summary.textContent='Edit ROT Billing — Rate / Discount / GST / Totals';
      details.appendChild(summary);
      form.parentNode.insertBefore(details,form);
      details.appendChild(form);
      var qty=document.createElement('p');
      qty.className='am123-qty-notice';
      qty.textContent='Qty: current Labour backend has no separate qty column. Std Hours are editable through Admin Full ROT Correction. Qty must not be saved as hours.';
      details.insertBefore(qty,form);
      card.appendChild(details);
    }
    var button=card.querySelector('.am116-edit-btn');
    if(button){
      label(button);
      var bar=card.querySelector('.am122-rot-inline-tools');
      if(bar && !bar.contains(button))bar.appendChild(button);
    }
  });
}
function start(){
  enhance();
  var list=document.querySelector('.jc-labour-block .rot-list');
  if(list && window.MutationObserver){
    new MutationObserver(function(mutations){
      if(mutations.some(function(m){return m.target===list && m.addedNodes.length>0;}))enhance();
    }).observe(list,{childList:true});
  }
  // If P55 cannot be mapped to the Main JC cards, retain the original
  // Amount Editor as fallback rather than exposing a nonworking submit.
  var portal=document.getElementById('am123-correction-forms');
  if(portal && !document.getElementById('am116-rot-dialog')){
    console.warn('AM123 P55 form/card mapping unavailable: use existing Amount Editor.');
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
else start();
})();
</script>
@endonce
