
{{-- AM122: presentation-only ROT toolbar; existing AM121 form, V647 time editor,
     FIX90 billing edit and all server-side permission/audit guards are unchanged. --}}
@once
<style id="AM122_VOID_INLINE_ROT_ACTIONS">
.jc-labour-block .am122-rot-inline-tools{
  display:flex!important;flex:1 1 100%!important;align-items:flex-start!important;
  flex-wrap:wrap!important;gap:7px!important;width:100%!important;
  margin:7px 0 5px!important;padding:0!important;order:35!important;
  position:relative!important;overflow:visible!important;min-height:28px!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am-v647-time-editor{
  position:relative!important;display:block!important;flex:0 1 auto!important;
  width:auto!important;order:0!important;margin:0!important;padding:0!important;
  min-height:28px!important;overflow:visible!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am-v647-time-editor>summary{
  font-size:10px!important;min-height:28px!important;box-sizing:border-box!important;
  padding:6px 9px!important;line-height:16px!important;white-space:normal!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am-v647-time-editor[open]{
  width:100%!important;flex:1 1 100%!important;order:0!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am121-void-panel{
  display:block!important;position:relative!important;flex:0 0 auto!important;
  width:auto!important;order:1!important;margin:0!important;
  padding:0!important;border:0!important;background:transparent!important;
  border-radius:5px!important;overflow:visible!important;min-height:28px!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am121-void-panel>summary{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;
  box-sizing:border-box!important;min-height:28px!important;cursor:pointer!important;
  font-size:10px!important;line-height:16px!important;font-weight:800!important;
  padding:6px 9px!important;border-radius:5px!important;
  border:1px solid #d5a1a7!important;background:#fff0f1!important;
  color:#a52337!important;list-style:none!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am121-void-panel>summary::-webkit-details-marker{
  display:none!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am121-void-panel[open]{
  width:100%!important;flex:1 1 100%!important;order:1!important;
  border:1px solid #e8c2c8!important;padding:10px!important;
  background:#fff9fa!important;border-radius:8px!important;
}
.jc-labour-block .am122-rot-inline-tools>details.am121-void-panel[open]>form{
  max-width:560px!important;width:100%!important;box-sizing:border-box!important;
}
.jc-labour-block .am122-rot-inline-tools>.am122-tool-btn{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;
  flex:0 0 auto!important;order:2!important;min-height:28px!important;
  font-size:10px!important;line-height:16px!important;box-sizing:border-box!important;
  font-weight:750!important;padding:6px 9px!important;margin:0!important;
  border:1px solid #abc7df!important;border-radius:5px!important;
  background:#f0f7ff!important;color:#19527f!important;text-decoration:none!important;
  cursor:pointer!important;
}
.jc-labour-block .am122-rot-inline-tools>.am122-tool-btn:hover{background:#e1f0ff!important}
@media(max-width:700px){
 .jc-labour-block .am122-rot-inline-tools{gap:5px!important;margin-top:5px!important}
 .jc-labour-block .am122-rot-inline-tools>details.am121-void-panel>summary,
 .jc-labour-block .am122-rot-inline-tools>.am122-tool-btn{font-size:10px!important;padding:5px 7px!important}
}
</style>
<script>
(function(){
 'use strict';
 function findBillingButton(id){
   if(!id)return null;
   return Array.from(document.querySelectorAll('#am90-table-body button[data-am90-edit]'))
     .find(function(btn){return String(btn.getAttribute('data-am90-edit'))===id;})||null;
 }
 function enhance(){
   var list=document.querySelector('.jc-labour-block .rot-list');
   if(!list)return;
   Array.from(list.querySelectorAll('.rot-card')).forEach(function(card){
     var target=card.querySelector('details.am-v647-time-editor');
     var voidPanel=card.querySelector('details.am121-void-panel');
     if(!target || !voidPanel || !target.parentElement)return;
     if(target.closest('.am122-rot-inline-tools'))return;
     // Reparenting existing DOM nodes retains P55/V647/AM121 forms and listeners.
     // Never duplicate a destructive form or silently create a second action.
     var bar=document.createElement('div');
     bar.className='am122-rot-inline-tools';
     bar.setAttribute('aria-label','ROT time correction and Admin tools');
     target.parentNode.insertBefore(bar,target);
     bar.appendChild(target);
     bar.appendChild(voidPanel);
     var id=String(card.getAttribute('data-am90-labour-id')||'');
     if(findBillingButton(id)){
       var bill=document.createElement('button');
       bill.type='button';bill.className='am122-tool-btn';
       bill.textContent='Edit Billing';
       bill.title='Qty / Rate / Discount / GST where available in Labour Billing editor';
       bill.addEventListener('click',function(){
         var existing=findBillingButton(id);
         if(existing)existing.click();
         else{bill.disabled=true;bill.title='Billing edit not available for this JC status';}
       });
       bar.appendChild(bill);
     }
     var link=Array.from(target.querySelectorAll('a[href]'))
       .find(function(a){return /history/i.test((a.textContent||'')+' '+a.getAttribute('href'));});
     if(link){
       var history=document.createElement('a');
       history.href=link.href;history.textContent='History';
       history.className='am122-tool-btn';
       history.target='_blank';history.rel='noopener noreferrer';
       bar.appendChild(history);
     }
   });
 }
 function ready(){
   enhance();
   var list=document.querySelector('.jc-labour-block .rot-list');
   if(list && window.MutationObserver){
     var observer=new MutationObserver(function(records){
       if(records.some(function(rec){return rec.target===list && rec.addedNodes.length>0;}))enhance();
     });
     observer.observe(list,{childList:true});
   }
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});
 else ready();
})();
</script>
@endonce
