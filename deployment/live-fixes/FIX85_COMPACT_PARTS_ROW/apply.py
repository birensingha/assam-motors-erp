#!/usr/bin/env python3
"""FIX85: single search-select and compact Parts inline row within JC parts table.
Existing audited parts.direct POST and Parts edit/delete untouched. Discount is
stored by existing controller; client preview is only convenience.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes()
actual=hashlib.sha256(raw).hexdigest()
print('FIX85_SOURCE_SHA',actual)
expected='3e7b7187c41e41055a1809ac801deda39dd73eee708931cd6d800348bdc800f6'
marker='FIX85_COMPACT_PARTS_TABLE_ROW'
s=raw.decode('utf8','surrogateescape')
if marker in s:
 print('FIX85_ALREADY_APPLIED');sys.exit(0)
if actual!=expected:raise SystemExit('FIX85_SOURCE_CHANGED_ABORT')
def one(a,b,name):
 global s
 count=s.count(a);print('FIX85_ANCHOR',name,count)
 if count!=1:raise SystemExit('FIX85_AMBIGUOUS_'+name)
 s=s.replace(a,b,1)
# Remove the entire duplicate quick-add card, keeping original Parts section.
startToken='{{-- FIX82_JC_SINGLE_ENTRY_PANEL: Part creation lives only inside Parts. --}}'
endToken='  <div class="section-head">'
if s.count(startToken)!=1:raise SystemExit('FIX85_DIRECT_CARD_NOT_FOUND')
a=s.index(startToken);b=s.index(endToken,a)
old=s[a:b]
for t in ('Add New Part','name="part_master_id"','name="qty"','name="gst_percent"',
          "route('erp.job-cards.parts.direct',$job->id)"):
 if t not in old:raise SystemExit('FIX85_OLD_DIRECT_FORM_MISMATCH_'+t)
s=s[:a]+s[b:]
# Keep the POST form separate from table structure, bind inputs via HTML5 form attribute.
inline=r'''{{-- FIX85_COMPACT_PARTS_TABLE_ROW — one-row Parts Add inside existing table. --}}
@if(!in_array(strtolower((string)$job->status),['closed','invoiced','cancelled'],true) && empty($job->invoice_id))
<form id="am85-part-form" method="post" action="{{ route('erp.job-cards.parts.direct',$job->id) }}">
 @csrf
 <input type="hidden" name="part_master_id" id="am85-part-id" value="">
</form>
<datalist id="am85-part-options">
 @foreach(($partsMaster ?? collect()) as $m)
 <option value="{{ $m->part_code }} · {{ $m->part_name }} · #{{ $m->id }}"
         data-id="{{ $m->id }}"
         data-rate="{{ number_format((float)($m->selling_price??0),2,'.','') }}"
         data-gst="{{ number_format((float)($m->gst_percent??0),2,'.','') }}"
         data-stock="{{ $m->stock_qty??0 }}"></option>
 @endforeach
</datalist>
@endif
'''
one('  <div class="line-scroll">\n  <table class="data-table jc-itemised-billing">',
    inline+'  <div class="line-scroll">\n  <table class="data-table jc-itemised-billing">','form_before_parts_table')
row=r'''    {{-- FIX85: Add controls in the SAME Parts table; no separate part selector. --}}
    @if(!in_array(strtolower((string)$job->status),['closed','invoiced','cancelled'],true) && empty($job->invoice_id))
    <tr class="am85-part-add-row">
      <td class="am85-first-cell">
        <input id="am85-part-search" type="search" form="am85-part-form"
          list="am85-part-options" autocomplete="off" spellcheck="false"
          aria-label="Search and select Part by code or name"
          placeholder="Search code / Part name · Enter" required>
        <details class="am85-part-note"><summary>+ Note</summary>
         <input form="am85-part-form" name="note" maxlength="1000" aria-label="Part note (optional)"
           placeholder="Part note (optional)" autocomplete="off">
        </details>
      </td>
      <td><input id="am85-qty" form="am85-part-form" name="qty" aria-label="Quantity"
         type="number" min="0.001" step="0.001" value="1" required></td>
      <td><input id="am85-rate" form="am85-part-form" name="rate" aria-label="Rate before GST"
         type="number" min="0" step="0.01" value="" placeholder="Rate" required></td>
      <td><input id="am85-discount" form="am85-part-form" name="discount_percent"
         type="number" aria-label="Discount percentage" min="0" max="100" step="0.01" value="0" required></td>
      <td class="am85-result"><output id="am85-discount-rs" aria-label="Discount amount">₹0.00</output></td>
      <td class="am85-result"><output id="am85-taxable" aria-label="Taxable amount">₹0.00</output></td>
      <td><input id="am85-gst" form="am85-part-form" name="gst_percent" aria-label="GST percentage"
        type="number" min="0" max="100" step="0.01" value="" placeholder="GST" required></td>
      <td class="am85-result"><output id="am85-gst-rs" aria-label="GST amount">₹0.00</output></td>
      <td class="am85-result am85-grand"><output id="am85-grand" aria-label="Final amount">₹0.00</output></td>
      <td><button type="submit" form="am85-part-form" class="btn primary am85-add-button">+ Add Part</button></td>
    </tr>
    @endif
'''
one('    <tbody>\n    @forelse($parts as $p)','    <tbody>\n'+row+'    @forelse($parts as $p)','inline_table_add_row')
css=r'''
<style id="FIX85_COMPACT_PARTS_TABLE_ROW">
/* Compact first table row mirrors saved Parts columns; preserves edit/delete rows. */
.jc-parts-block .line-scroll{overflow-x:auto}
.jc-parts-block .jc-itemised-billing{width:100%;min-width:980px;table-layout:auto}
.jc-parts-block .am85-part-add-row{background:#edf6ff;border-bottom:2px solid #8db8ed}
.jc-parts-block .am85-part-add-row>td{padding:5px 4px!important;vertical-align:middle!important}
.jc-parts-block .am85-part-add-row td:first-child{min-width:235px}
.jc-parts-block .am85-part-add-row input{
 box-sizing:border-box;display:block;width:100%;height:31px!important;min-height:31px!important;
 padding:4px 5px!important;margin:0!important;font-size:11px!important;line-height:1.1!important;
 border:1px solid #b8d1ef;border-radius:5px;background:#fff;color:#163b65;
}
.jc-parts-block .am85-part-add-row input:focus{outline:2px solid #4487e3;outline-offset:0}
.jc-parts-block .am85-part-add-row #am85-part-search{min-width:220px;font-size:11px!important}
.jc-parts-block .am85-part-add-row .am85-result{white-space:nowrap;text-align:right;font-size:11px;color:#2b4b70}
.jc-parts-block .am85-part-add-row .am85-grand{font-weight:900;color:#00662c;font-size:12px}
.jc-parts-block .am85-part-add-row td.am85-first-cell{position:relative}
.jc-parts-block .am85-part-note{font-size:9px;color:#2c5c90;padding-top:2px;line-height:1.05}
.jc-parts-block .am85-part-note summary{cursor:pointer;width:max-content;color:#2e6bb1}
.jc-parts-block .am85-part-note[open] input{
 position:absolute;top:56px;left:0;width:280px;z-index:12;box-shadow:0 3px 12px #c8d8ec;
}
.jc-parts-block .am85-add-button{
 white-space:nowrap!important;padding:7px 9px!important;font-size:10px!important;min-height:31px!important;
}
@media(max-width:1050px){.jc-parts-block .jc-itemised-billing{min-width:1035px}}
</style>
'''
# This styling must not alter the green Labour/OSL panel.
one('<style id="FIX83_SINGLE_LABOUR_OSL_WORKSPACE">',css+'<style id="FIX83_SINGLE_LABOUR_OSL_WORKSPACE">','style_insertion')
js=r'''
<script>
(function(){
  'use strict';
  function boot(){
    const form=document.getElementById('am85-part-form');
    const input=document.getElementById('am85-part-search');
    const datalist=document.getElementById('am85-part-options');
    const id=document.getElementById('am85-part-id');
    if(!form||!input||!datalist||!id)return;
    const options=Array.from(datalist.options);
    const qty=document.getElementById('am85-qty'),rate=document.getElementById('am85-rate'),
      discount=document.getElementById('am85-discount'),gst=document.getElementById('am85-gst');
    const calcOut={disc:document.getElementById('am85-discount-rs'),
      taxable:document.getElementById('am85-taxable'),gst:document.getElementById('am85-gst-rs'),
      grand:document.getElementById('am85-grand')};
    const round=n=>Math.round((Number(n)||0)*100)/100;
    const out=n=>'₹'+round(n).toFixed(2);
    function recalc(){
      const q=Number(qty.value),r=Number(rate.value),d=Number(discount.value),g=Number(gst.value);
      if(!id.value||![q,r,d,g].every(Number.isFinite)||q<=0||r<0||d<0||d>100||g<0||g>100){
        for(const el of Object.values(calcOut))el.textContent='—';
        return;
      }
      const gross=round(q*r),disc=round(gross*d/100),net=round(gross-disc),tax=round(net*g/100);
      calcOut.disc.textContent=out(disc);
      calcOut.taxable.textContent=out(net);
      calcOut.gst.textContent=out(tax);
      calcOut.grand.textContent=out(net+tax);
    }
    let selectedValue='';
    function selectPart(opt,moveFocus){
      if(!opt)return false;
      input.value=opt.value;
      id.value=opt.dataset.id||'';
      selectedValue=opt.value;
      rate.value=(Number(opt.dataset.rate)||0).toFixed(2);
      gst.value=(Number(opt.dataset.gst)||0).toFixed(2);
      input.setCustomValidity('');
      recalc();
      if(moveFocus){qty.focus();qty.select();}
      return true;
    }
    function matching(){
      const text=input.value.trim().toLowerCase();
      if(!text)return null;
      return options.find(o=>o.value.toLowerCase()===text)||
        options.find(o=>o.value.toLowerCase().startsWith(text))||
        options.find(o=>o.value.toLowerCase().includes(text))||null;
    }
    input.addEventListener('input',()=>{
      // Any changed text invalidates the prior selection until exact match.
      if(input.value!==selectedValue){id.value='';selectedValue='';}
      const exact=options.find(o=>o.value===input.value);
      if(exact)selectPart(exact,false);
      recalc();
    });
    input.addEventListener('change',()=>{
      const opt=options.find(o=>o.value===input.value);
      if(opt)selectPart(opt,false);
    });
    input.addEventListener('keydown',e=>{
      if(e.key!=='Enter')return;
      e.preventDefault();
      const option=matching();
      if(option)selectPart(option,true);
      else{input.setCustomValidity('Select a matching Part from the suggestions.');input.reportValidity();}
    });
    input.addEventListener('focus',()=>input.setCustomValidity(''));
    for(const field of [qty,rate,discount,gst])field.addEventListener('input',recalc);
    form.addEventListener('submit',e=>{
      if(!id.value||!options.some(o=>o.dataset.id===id.value&&o.value===input.value)){
        e.preventDefault();input.setCustomValidity('Select an existing Part from the suggestions.');
        input.focus();input.reportValidity();return;
      }
      if(!qty.reportValidity()||!rate.reportValidity()||!discount.reportValidity()||!gst.reportValidity()){
        e.preventDefault();return;
      }
      // Do not submit a second time while the browser navigates.
      const btn=document.querySelector('[form="am85-part-form"][type="submit"]');
      if(btn)btn.disabled=true;
      try{sessionStorage.setItem('am85-focus-part-search','1')}catch(_){}
    });
    try{
      if(sessionStorage.getItem('am85-focus-part-search')==='1'){
        sessionStorage.removeItem('am85-focus-part-search');
        input.focus();
      }
    }catch(_){}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
})();
</script>
'''
one('<style id="FIX85_COMPACT_PARTS_TABLE_ROW">',js+'<style id="FIX85_COMPACT_PARTS_TABLE_ROW">','script_before_style')
for token in ["route('erp.job-cards.parts.direct',$job->id)",
              "route('erp.job-cards.parts.update',[$job->id,$p->id])",
              "route('erp.job-cards.parts.delete',[$job->id,$p->id])",
              "route('erp.job-cards.labour.direct',$job->id)"]:
 if token not in s:raise SystemExit('FIX85_CRITICAL_ENDPOINT_REMOVED_'+token)
if s.count('id="am85-part-form"')!=1 or s.count('id="am85-part-search"')!=1 or s.count('name="discount_percent"')<2:
 raise SystemExit('FIX85_SINGLE_INPUT_DISCOUNT_INCOMPLETE')
if 'Add New Part' in s or 'name="part_master_id" required><option' in s or 'class="am82-entry-block" aria-label="Add part' in s:
 raise SystemExit('FIX85_DUPLICATE_PART_FORM_STILL_PRESENT')
if s.count('FIX85_COMPACT_PARTS_TABLE_ROW')<2:raise SystemExit('FIX85_MARKER_INCOMPLETE')
if mode=='check':
 print('FIX85_SINGLE_SEARCH_DISCOUNT_ONE_ROW_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix85_',dir=view.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,view.stat().st_mode&0o777);os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX85_PARTS_TABLE_COMPACT_ENTRY_APPLIED_VIEW_ONLY')
