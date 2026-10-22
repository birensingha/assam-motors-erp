#!/usr/bin/env python3
"""FIX87: native select like ROT, no inner Parts scroller, modal reasons, AJAX saves.
Staging UI view-only patch; existing Laravel POST guards and transactions stay intact.
"""
from pathlib import Path
import hashlib, re, os, sys, tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes();sha=hashlib.sha256(raw).hexdigest()
print('FIX87_TARGET_SHA256',sha)
expected='ae3fae9fa373a5c0fb906928b9e484eb1d1e80df9ddfacb88225a48fc150c0cb'
s=raw.decode('utf8','surrogateescape')
if 'id="FIX87_NATIVE_PARTS_NO_SCROLL_AJAX"' in s:
 if 'id="am87-native-parts"' in s: print('FIX87_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX87_INCOMPLETE_MARKERS')
if sha!=expected:raise SystemExit('FIX87_CONCURRENT_SOURCE_CHANGE_ABORT')
def replace_one(old,new,label):
 global s
 n=s.count(old);print('FIX87_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX87_ANCHOR_FAIL_'+label)
 s=s.replace(old,new,1)
# Remove datalist: the FIX85 and FIX86 native/special popup scripts both
# exit without binding, preventing two conflicting dropdown implementations.
s,n=re.subn(r'<datalist id="am85-part-options">[\s\S]*?</datalist>', '',s,count=1)
if n!=1:raise SystemExit('FIX87_EXPECTED_EXACTLY_ONE_DATALIST')
native=r'''<select id="am85-part-search" form="am85-part-form" required
           aria-label="Select Part from master" title="Select Part code or name">
           <option value="">Select Part / Item...</option>
           @foreach(($partsMaster ?? collect()) as $m)
           <option value="{{ $m->id }}" data-rate="{{ number_format((float)($m->selling_price??0),2,'.','') }}"
                  data-gst="{{ number_format((float)($m->gst_percent??0),2,'.','') }}">
              {{ $m->part_code }} · {{ $m->part_name }}
           </option>
           @endforeach
         </select>'''
search=re.search(r'<input id="am85-part-search" type="search" form="am85-part-form"[\s\S]*?required>',s)
if not search or len(re.findall(r'<input id="am85-part-search" ',s))!=1:raise SystemExit('FIX87_PART_INPUT_ANCHOR_FAIL')
s=s[:search.start()]+native+s[search.end():]
# Insert status beside heading, not as a separate input section.
replace_one('<h2 class="section-title">Parts</h2>',
            '<h2 class="section-title">Parts</h2><span class="am87-inline-status" id="am87-parts-status" role="status" aria-live="polite"></span>',
            'part_status')
# Avoid an independent horizontal scrollbar; table instead adapts to page.
replace_one('  <div class="line-scroll">\n  <table class="data-table jc-itemised-billing">',
            '  <div class="line-scroll am87-page-table">\n  <table class="data-table jc-itemised-billing">',
            'parts_table')
extra=r'''
{{-- FIX87_NATIVE_PARTS_NO_SCROLL_AJAX: native master select, readable reason modal,
     and scoped AJAX changes with existing server validation unchanged. --}}
<style id="FIX87_NATIVE_PARTS_NO_SCROLL_AJAX">
.jc-parts-block{overflow:visible!important;max-height:none!important}
.jc-parts-block .line-scroll.am87-page-table{
  overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important;
  max-height:none!important;width:100%!important
}
.jc-parts-block .am87-page-table .jc-itemised-billing{
  width:100%!important;max-width:100%!important;min-width:0!important;
  table-layout:fixed!important;border-collapse:collapse
}
.jc-parts-block .am87-page-table .jc-itemised-billing thead th{
  position:sticky!important;top:0!important;z-index:12;
  color:white!important;background:#0c315a!important;box-shadow:0 2px 0 #0c315a;
  white-space:normal!important;line-height:1.2!important;
}
.jc-parts-block .am87-page-table th,.jc-parts-block .am87-page-table td{
  padding:5px 4px!important;min-width:0!important;overflow-wrap:anywhere
}
.jc-parts-block .am87-page-table th:nth-child(1){width:20%}
.jc-parts-block .am87-page-table th:nth-child(2){width:7%}
.jc-parts-block .am87-page-table th:nth-child(3){width:10%}
.jc-parts-block .am87-page-table th:nth-child(4){width:7%}
.jc-parts-block .am87-page-table th:nth-child(5){width:9%}
.jc-parts-block .am87-page-table th:nth-child(6){width:9%}
.jc-parts-block .am87-page-table th:nth-child(7){width:7%}
.jc-parts-block .am87-page-table th:nth-child(8){width:8%}
.jc-parts-block .am87-page-table th:nth-child(9){width:11%}
.jc-parts-block .am87-page-table th:nth-child(10){width:12%}
.jc-parts-block .am87-page-table .am85-part-add-row input,
.jc-parts-block .am87-page-table .am85-part-add-row select{
  box-sizing:border-box!important;width:100%!important;min-width:0!important;
  height:32px!important;min-height:32px!important;padding:3px 4px!important;
  margin:0!important;border:1px solid #9dbbd9!important;border-radius:6px!important;
  background:#fff!important;color:#153b66!important;font-size:11px!important
}
.jc-parts-block .am87-page-table .am85-part-add-row select#am85-part-search{
  min-width:0!important;padding-right:2px!important;cursor:pointer!important;
  appearance:auto!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am85-add-button{
  min-height:30px!important;width:100%!important;padding:4px 3px!important;
  white-space:normal!important;font-size:10px!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am85-result{
  font-size:10px!important;white-space:normal!important
}
.jc-parts-block .am87-page-table details.action-pop{
  display:inline-block!important;margin:1px!important
}
.jc-parts-block .am87-page-table details.action-pop summary{
  padding:5px 6px!important;font-size:10px!important;white-space:nowrap
}
.jc-parts-block .am87-page-table .am85-part-note{position:static!important;
  font-size:9px!important;margin-top:2px!important}
.jc-parts-block .am87-page-table .am85-part-note[open] input{
  position:absolute!important;top:54px!important;left:0!important;right:auto!important;
  width:235px!important;z-index:100
}
.am87-inline-status{font-size:11px;color:#176044;margin-left:8px;font-weight:600}
.am87-toast{
  position:fixed;right:20px;bottom:20px;z-index:2147483500;
  padding:12px 16px;background:#13583e;color:white;max-width:min(460px,90vw);
  border-radius:9px;box-shadow:0 7px 26px #102a4355;font-size:12px
}
.am87-toast.error{background:#9d2424}
dialog.am87-dialog{
  position:fixed!important;inset:0!important;margin:auto!important;
  box-sizing:border-box;width:min(470px,calc(100vw - 28px))!important;
  max-height:calc(100vh - 30px)!important;
  border:1px solid #b6c9db!important;border-radius:12px!important;
  padding:0!important;overflow:auto!important;
  background:white!important;color:#19334d!important;
  box-shadow:0 20px 70px #0d264765!important;z-index:2147483000!important
}
dialog.am87-dialog::backdrop{background:rgba(10,28,50,.55)}
.am87-dialog-head{display:flex;justify-content:space-between;align-items:center;
  gap:12px;padding:13px 16px;background:#eaf3ff;border-bottom:1px solid #bed4ed}
.am87-dialog-head strong{font-size:14px;color:#153c67}
.am87-dialog-close{border:1px solid #b4c8d9;background:white;color:#254768;
  border-radius:6px;min-width:33px;height:30px;cursor:pointer}
.am87-dialog-content{padding:16px}
.am87-dialog-content form.pop-form{position:static!important;display:grid!important;
  transform:none!important;inset:auto!important;width:100%!important;min-width:0!important;
  max-width:100%!important;max-height:none!important;overflow:visible!important;
  box-sizing:border-box!important;box-shadow:none!important;
  background:white!important;border:0!important;padding:0!important;
  margin:0!important;gap:12px!important;z-index:auto!important}
.am87-dialog-content label{display:grid!important;gap:5px!important;font-size:12px!important;
  color:#344b65!important;font-weight:600!important}
.am87-dialog-content input,.am87-dialog-content select,.am87-dialog-content textarea{
  box-sizing:border-box!important;width:100%!important;max-width:100%!important;
  min-height:36px!important;padding:8px!important;color:#183752!important;
  background:white!important;border:1px solid #adbed1!important;border-radius:6px!important;
  font-size:12px!important
}
.am87-dialog-content textarea{min-height:85px!important;resize:vertical}
.am87-dialog-content button[type=submit],.am87-dialog-content button:not([type]){
  min-height:36px!important;font-size:12px!important
}
@media(max-width:720px){
 .jc-parts-block .am87-page-table .jc-itemised-billing{min-width:720px!important}
 .jc-parts-block{overflow-x:visible!important}
 .jc-parts-block .am87-page-table .jc-itemised-billing{font-size:10px!important}
}
</style>
<script>
(function(){
'use strict';
function boot(){
  var select=document.getElementById('am85-part-search'),
      form=document.getElementById('am85-part-form'),
      partId=document.getElementById('am85-part-id'),
      qty=document.getElementById('am85-qty'),
      rate=document.getElementById('am85-rate'),
      discount=document.getElementById('am85-discount'),
      gst=document.getElementById('am85-gst');
  function money(v){return '₹'+(Math.round((Number(v)||0)*100)/100).toFixed(2);}
  function recalc(){
    if(!partId||!qty||!rate||!discount||!gst)return;
    var q=Number(qty.value),r=Number(rate.value),d=Number(discount.value),g=Number(gst.value);
    var values=[q,r,d,g], good=Boolean(partId.value)&&
      values.every(Number.isFinite)&&q>0&&r>=0&&d>=0&&d<=100&&g>=0&&g<=100;
    var output=['am85-discount-rs','am85-taxable','am85-gst-rs','am85-grand']
      .map(function(id){return document.getElementById(id);});
    if(!good){output.forEach(function(e){if(e)e.textContent='—';});return;}
    var gross=Math.round(q*r*100)/100,disc=Math.round(gross*d)/100;
    var net=Math.round((gross-disc)*100)/100,tax=Math.round(net*g)/100;
    [disc,net,tax,net+tax].forEach(function(v,i){if(output[i])output[i].textContent=money(v);});
  }
  if(select&&form&&partId){
    select.addEventListener('change',function(){
      var opt=select.selectedOptions[0];
      partId.value=select.value||'';
      if(opt&&partId.value){
        rate.value=(Number(opt.dataset.rate)||0).toFixed(2);
        gst.value=(Number(opt.dataset.gst)||0).toFixed(2);
        qty.focus();qty.select();
      }
      recalc();
    });
    [qty,rate,discount,gst].forEach(function(el){
      if(el)el.addEventListener('input',recalc);
    });
    form.addEventListener('submit',function(ev){
      if(!select.value||partId.value!==select.value){
        ev.preventDefault();select.setCustomValidity('Select a Part from the list.');
        select.reportValidity();return;
      }
      select.setCustomValidity('');
    });
    select.addEventListener('change',function(){select.setCustomValidity('');});
    recalc();
  }
  var modal=null, modalZone=null, busy=false;
  function closeModal(){
    if(modal){if(modal.open)modal.close();modal.remove();modal=null;modalZone=null;}
  }
  document.addEventListener('click',function(e){
    var summary=e.target.closest('summary');
    if(!summary||!summary.parentElement.matches('details.action-pop'))return;
    var parent=summary.closest('.jc-parts-block,.jc-labour-block');
    if(!parent)return;
    var sourceForm=summary.parentElement.querySelector('form.pop-form');
    if(!sourceForm)return;
    e.preventDefault();
    closeModal();
    modal=document.createElement('dialog');
    modal.className='am87-dialog';
    modalZone=parent.classList.contains('jc-parts-block')?'parts':'labour';
    modal.dataset.am87Zone=modalZone;
    var head=document.createElement('div');head.className='am87-dialog-head';
    var title=document.createElement('strong');title.textContent=summary.textContent.trim();
    var close=document.createElement('button');close.type='button';
    close.className='am87-dialog-close';close.textContent='×';
    close.setAttribute('aria-label','Close edit or delete panel');
    close.addEventListener('click',closeModal);
    head.appendChild(title);head.appendChild(close);
    var content=document.createElement('div');content.className='am87-dialog-content';
    content.appendChild(sourceForm.cloneNode(true));
    modal.appendChild(head);modal.appendChild(content);
    document.body.appendChild(modal);
    modal.addEventListener('close',function(){if(modal){modal.remove();modal=null;modalZone=null;}});
    modal.addEventListener('click',function(ev){if(ev.target===modal)closeModal();});
    modal.showModal();
    var first=modal.querySelector('input:not([type=hidden]),textarea,select');
    if(first)first.focus();
  });
  var notice=null;
  function toast(message,isError){
    if(notice)notice.remove();
    notice=document.createElement('div');
    notice.className='am87-toast'+(isError?' error':'');
    notice.textContent=message;notice.setAttribute('role','status');
    document.body.appendChild(notice);
    // No timer: user can read outcome; next action replaces the notice.
  }
  function updateParts(doc){
    var current=document.querySelector('.jc-parts-block .jc-itemised-billing tbody');
    var source=doc.querySelector('.jc-parts-block .jc-itemised-billing tbody');
    if(!current||!source)throw new Error('Updated Parts table missing in response.');
    var add=current.querySelector('.am85-part-add-row');
    var rows=Array.from(source.children).filter(function(el){
      return !el.classList.contains('am85-part-add-row');
    }).map(function(el){return document.importNode(el,true);});
    current.replaceChildren.apply(current,(add?[add]:[]).concat(rows));
  }
  function updateLabour(doc){
    var current=document.querySelector('.jc-labour-block .rot-list');
    var source=doc.querySelector('.jc-labour-block .rot-list');
    if(!current||!source)throw new Error('Updated Labour/ROT rows missing in response.');
    current.replaceChildren.apply(current,Array.from(source.children).map(function(el){
      return document.importNode(el,true);
    }));
    var sourceBadge=doc.querySelector('.jc-labour-block .am83-workflow-strip span');
    var badge=document.querySelector('.jc-labour-block .am83-workflow-strip span');
    if(sourceBadge&&badge)badge.textContent=sourceBadge.textContent;
  }
  function matchingZone(f){
    if(f.id==='am85-part-form')return 'parts';
    if(f.closest('.am87-dialog'))return f.closest('.am87-dialog').dataset.am87Zone||null;
    if(f.closest('.jc-parts-block')&&f.matches('form.pop-form'))return 'parts';
    if(f.closest('.jc-labour-block')&&
       (f.matches('form.jc-entry-form')||f.matches('form.am82-finance-form')||
        f.matches('form.pop-form')))return 'labour';
    return null;
  }
  document.addEventListener('submit',async function(e){
    var submitted=e.target;if(!(submitted instanceof HTMLFormElement))return;
    var zone=matchingZone(submitted);
    if(!zone)return;
    if(busy){e.preventDefault();return;}
    if(!submitted.reportValidity()){e.preventDefault();return;}
    e.preventDefault();
    if(zone==='parts'&&submitted.id==='am85-part-form'&&(!partId||!partId.value)){
      toast('Select Part before adding.',true);select&&select.focus();return;
    }
    busy=true;
    var button=submitted.querySelector('[type=submit],button:not([type])');
    var hiddenButton= submitted.id==='am85-part-form'?
      document.querySelector('[form="am85-part-form"][type="submit"]'):null;
    if(button)button.disabled=true;
    if(hiddenButton)hiddenButton.disabled=true;
    var scrollY=window.scrollY;
    var isAdd=submitted.id==='am85-part-form'||
      (zone==='labour'&&submitted.matches('form.jc-entry-form'));
    var before=zone==='parts'?document.querySelectorAll('.jc-parts-block tbody tr:not(.am85-part-add-row)').length:
      document.querySelectorAll('.jc-labour-block .rot-list .rot-card').length;
    try{
      var response=await fetch(submitted.action,{
        method:(submitted.method||'POST').toUpperCase(),
        body:new FormData(submitted),
        headers:{'Accept':'text/html'},
        redirect:'follow',credentials:'same-origin',cache:'no-store'
      });
      if(!response.ok)throw new Error('Server returned HTTP '+response.status);
      if(new URL(response.url,location.href).origin!==location.origin)
        throw new Error('Unexpected server redirect.');
      var raw=await response.text();
      var doc=new DOMParser().parseFromString(raw,'text/html');
      var target=zone==='parts'?'.jc-parts-block .jc-itemised-billing tbody':'.jc-labour-block .rot-list';
      // An auth redirect or validation page is not proof of a successful write.
      if(!doc.querySelector(target))
        throw new Error('Server did not return Job Card rows. Please review login or validation.');
      var error=doc.querySelector('.alert-danger,.alert.error,.error-alert,.validation-errors');
      if(error&&error.textContent.trim()){
        throw new Error(error.textContent.trim().slice(0,240));
      }
      var after=zone==='parts'?doc.querySelectorAll('.jc-parts-block tbody tr:not(.am85-part-add-row)').length:
        doc.querySelectorAll('.jc-labour-block .rot-list .rot-card').length;
      if(isAdd&&after<=before)
        throw new Error('New item not confirmed in Job Card; inspect the section before retrying.');
      if(zone==='parts')updateParts(doc);
      else updateLabour(doc);
      closeModal();
      if(submitted.id==='am85-part-form'){
        select.value='';partId.value='';qty.value='1';rate.value='';discount.value='0';gst.value='';
        var note=document.querySelector('.am85-part-note input[name=note]');
        if(note)note.value='';
        recalc();select.focus({preventScroll:true});
      }else if(isAdd&&submitted.matches('form.jc-entry-form')){
        submitted.reset();
        var rotSearch=document.getElementById('am82-rot-search');
        if(rotSearch)rotSearch.value='';
      }
      window.scrollTo({top:scrollY,behavior:'instant'});
      toast((zone==='parts'?'Parts':'Labour / ROT')+' section updated without full page reload.',false);
    }catch(err){
      toast((err&&err.message?err.message:'Server response not confirmed')+
            ' — Please check the record before trying again.',true);
    }finally{
      busy=false;
      if(button&&button.isConnected)button.disabled=false;
      if(hiddenButton&&hiddenButton.isConnected)hiddenButton.disabled=false;
    }
  },true);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
else boot();
})();
</script>
'''
# Appended after original CSS so sticky-table and native-select overrides win.
s+='\n'+extra
for token in ['id="am87-native-parts"','id="am85-part-search"','id="am85-discount"','id="am85-part-id"',
              'class="am85-part-add-row"',"route('erp.job-cards.parts.direct',$job->id)",
              "route('erp.job-cards.parts.update',[$job->id,$p->id])",
              "route('erp.job-cards.parts.delete',[$job->id,$p->id])",
              "route('erp.job-cards.labour.direct',$job->id)",
              "route('erp.fix48.job-line',[$job->id,'labour',$line->id])"]:
 if token=='id="am87-native-parts"':continue
 if token not in s:raise SystemExit('FIX87_CRITICAL_UI_REMOVED_'+token)
if s.count('<select id="am85-part-search"')!=1 or 'datalist id="am85-part-options"' in s:
 raise SystemExit('FIX87_NATIVE_SELECT_INCOMPLETE')
if mode=='check':print('FIX87_NATIVE_SELECT_MODAL_ASYNC_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.fix87_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,view.stat().st_mode&0o777);os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX87_NATIVE_SELECT_REASON_MODAL_AJAX_APPLIED_VIEW_ONLY')
