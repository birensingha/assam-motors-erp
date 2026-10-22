#!/usr/bin/env python3
"""FIX88: compact JC Parts and edit all four calculated amounts.
Converts user-entered amounts to existing server-authoritative rate/discount/GST
parameters; no controller, route, schema, audit, stock, or finance changes.
"""
from pathlib import Path
import sys,os,re,tempfile,hashlib

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
p=root/'resources/views/job-cards/show.blade.php'
raw=p.read_bytes()
src_sha=hashlib.sha256(raw).hexdigest()
print('FIX88_TARGET_SHA256',src_sha)
s=raw.decode('utf8','surrogateescape')
marker='id="FIX88_COMPACT_EDITABLE_AMOUNTS"'
if marker in s:
 if s.count(marker)==1 and s.count('class="am88-amount-input"')==4:
  print('FIX88_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX88_PARTIALLY_APPLIED')
# Source is guarded by structural anchors and the expected FIX87 deployed version.
for k in ['id="FIX87_NATIVE_PARTS_NO_SCROLL_AJAX"','id="am85-part-form"',
          '<select id="am85-part-search"', 'id="am85-discount"',
          'id="am85-gst"','class="am85-part-add-row"']:
 if s.count(k)!=1:raise SystemExit('FIX88_EXPECTED_ANCHOR_NOT_UNIQUE '+k+' '+str(s.count(k)))
oldtext='Search or add Parts directly. Qty / Rate / Discount / GST / Delete editable until Job Card Close. Stock is posted automatically at Close, exactly once.'
if s.count(oldtext)!=1:raise SystemExit('FIX88_PARTS_DESCRIPTION_CHANGED')
s=s.replace('<p class="muted">'+oldtext+'</p>','',1)
targets={
 'am85-discount-rs':'Discount amount',
 'am85-taxable':'Taxable amount',
 'am85-gst-rs':'GST amount',
 'am85-grand':'Final amount',
}
for name,label in targets.items():
 pattern=r'<output\s+id="'+re.escape(name)+r'"\s+aria-label="[^"]*">[\s\S]*?</output>'
 repl='<input id="'+name+'" class="am88-amount-input" form="am85-part-form" type="number" min="0" step="0.01" inputmode="decimal" aria-label="'+label+'" placeholder="0.00">'
 s,count=re.subn(pattern,repl,s,count=1)
 print('FIX88_OUTPUT_REPLACED',name,count)
 if count!=1:raise SystemExit('FIX88_OUTPUT_MISSING_'+name)
s=s.replace('id="am85-discount" form="am85-part-form" name="discount_percent"',
            'id="am85-discount" form="am85-part-form" name="discount_percent"',1)
# Reverse-calculated discount / GST percentages need fractional precision,
# otherwise HTML's step=0.01 reports a false validation failure.
s=s.replace('aria-label="Discount percentage" min="0" max="100" step="0.01"',
            'aria-label="Discount percentage" min="0" max="100" step="any"',1)
s=s.replace('name="gst_percent" aria-label="GST percentage"\n        type="number" min="0" max="100" step="0.01"',
            'name="gst_percent" aria-label="GST percentage"\n        type="number" min="0" max="100" step="any"',1)
cssjs=r'''
{{-- FIX88: editable Parts amount columns, compact header, no additional scrolling. --}}
<style id="FIX88_COMPACT_EDITABLE_AMOUNTS">
.jc-parts-block{padding:8px 10px 8px!important;margin-top:8px!important;margin-bottom:9px!important}
.jc-parts-block>.section-head{padding:2px 2px 4px!important;min-height:0!important;margin:0 0 5px!important;border:0!important}
.jc-parts-block>.section-head .section-title{margin:0!important;font-size:13px!important;line-height:1.15!important}
.jc-parts-block>.section-head .muted{display:none!important}
.jc-parts-block .am87-page-table{padding:0!important;margin:0!important}
.jc-parts-block .am87-page-table .jc-itemised-billing th{padding:4px 3px!important;font-size:9px!important}
.jc-parts-block .am87-page-table .jc-itemised-billing td{padding:3px 3px!important}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(1){width:20%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(2){width:6.5%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(3){width:9%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(4){width:7%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(5){width:9.5%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(6){width:9.5%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(7){width:7%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(8){width:9.5%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(9){width:11%}
.jc-parts-block .am87-page-table .jc-itemised-billing th:nth-child(10){width:11%}
.jc-parts-block .am87-page-table .am85-part-add-row td:first-child{min-width:0!important}
.jc-parts-block .am87-page-table .am85-part-add-row input,
.jc-parts-block .am87-page-table .am85-part-add-row select{
  height:29px!important;min-height:29px!important;
  padding:3px 2px!important;font-size:10px!important
}
.jc-parts-block .am87-page-table .am85-part-add-row input.am88-amount-input{
  display:block!important;color:#123b63!important;
  background:#fff!important;border:1px solid #abc5e0!important;
  text-align:right!important;font-weight:700!important;
  width:100%!important;box-sizing:border-box!important
}
.jc-parts-block .am87-page-table .am85-part-add-row #am85-grand{
  border-color:#27a473!important;background:#f3fff6!important;color:#116b3f!important
}
.jc-parts-block .am87-page-table .am85-part-add-row #am85-part-search{
  padding-left:5px!important;font-size:10px!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am85-add-button{
  padding:4px 2px!important;min-height:28px!important
}
.jc-parts-block .am87-page-table .am85-part-note{line-height:1!important;margin-top:1px!important}
.jc-parts-block .am87-page-table .am85-part-add-row{height:auto!important}
</style>
<script>
(function(){
'use strict';
function init(){
 var by=function(id){return document.getElementById(id);};
 var el={form:by('am85-part-form'),part:by('am85-part-search'),pid:by('am85-part-id'),
   qty:by('am85-qty'),rate:by('am85-rate'),pct:by('am85-discount'),
   da:by('am85-discount-rs'),taxable:by('am85-taxable'),gst:by('am85-gst'),
   ga:by('am85-gst-rs'),final:by('am85-grand')};
 if(Object.keys(el).some(function(k){return !el[k];}))return;
 var rr=function(x){return Math.round((Number(x)||0)*100)/100;};
 var money=function(x){return rr(x).toFixed(2);};
 var fmtPct=function(x){return (Math.round(x*1000000)/1000000).toString();};
 var good=function(x){return Number.isFinite(x)&&x>=0;};
 var latestChanged=null;
 var updating=false;
 function gross(){return rr(Number(el.qty.value)*Number(el.rate.value));}
 function state(){
  var q=Number(el.qty.value),r=Number(el.rate.value);
  var d=Number(el.pct.value),g=Number(el.gst.value);
  if(!el.pid.value||!el.qty.value||!el.rate.value||!el.gst.value||
     !good(q)||q<=0||!good(r)||!good(d)||d>100||!good(g)||g>100)
   return null;
  var gr=rr(q*r),disc=rr(gr*d/100),taxable=rr(gr-disc),tax=rr(taxable*g/100);
  return {gross:gr,disc:disc,taxable:taxable,gst:tax,final:rr(taxable+tax)};
 }
 function show(skip){
  var a=state();
  if(!a)return;
  [['da',a.disc],['taxable',a.taxable],['ga',a.gst],['final',a.final]].forEach(function(pair){
   if(pair[0]!==skip)el[pair[0]].value=money(pair[1]);
  });
 }
 function error(field,message){
  field.setCustomValidity(message||'');
 }
 function updateBase(){
  if(updating)return;
  latestChanged=null;
  [el.da,el.taxable,el.ga,el.final].forEach(function(x){error(x,'');});
  show(null);
 }
 function modified(field){
  if(updating)return;
  latestChanged=field;
  var input=el[field],v=Number(input.value),s=state();
  error(input,'');
  if(input.value===''||!good(v)){error(input,'Enter a valid amount.');return;}
  if(!s){error(input,'Select a Part and enter Qty, Rate and GST first.');return;}
  var gr=s.gross;
  if(field==='da'||field==='taxable'){
   var disc=field==='da'?v:rr(gr-v);
   if(disc<0||disc>gr||(!gr&&disc!==0)){
     error(input,'Amount must be between ₹0 and the gross Qty × Rate.');return;
   }
   if(gr>0)el.pct.value=fmtPct(disc/gr*100);
   else el.pct.value='0';
  }else if(field==='ga'){
   if(v>s.taxable||(!s.taxable&&v!==0)){
     error(input,'GST Amount must be within taxable value (GST maximum 100%).');return;
   }
   el.gst.value=s.taxable?fmtPct(v/s.taxable*100):'0';
  }else if(field==='final'){
   var g=Number(el.gst.value),wantNet=rr(v/(1+g/100));
   if(!Number.isFinite(wantNet)){error(input,'Invalid Final Amount.');return;}
   if(wantNet<=gr&&gr>0){
     el.pct.value=fmtPct((gr-wantNet)/gr*100);
   }else if(wantNet>gr){
     var q=Number(el.qty.value),d=Number(el.pct.value);
     var denom=q*(1-d/100);
     if(denom<=0){error(input,'Increase Rate or reduce Discount first.');return;}
     el.rate.value=money(wantNet/denom);
   }else if(gr===0){
     error(input,'Enter Rate before editing Final Amount.');return;
   }
  }
  if(!el.pct.checkValidity()||!el.gst.checkValidity()||!el.rate.checkValidity()){
    error(input,'This amount cannot be represented by the Rate / Discount / GST fields.');
    return;
  }
  show(field);
 }
 [el.qty,el.rate,el.pct,el.gst].forEach(function(x){
  x.addEventListener('input',updateBase);
  x.addEventListener('change',updateBase);
 });
 [['da',el.da],['taxable',el.taxable],['ga',el.ga],['final',el.final]].forEach(function(pair){
  pair[1].addEventListener('input',function(){modified(pair[0]);});
  pair[1].addEventListener('blur',function(){
   if(!pair[1].validationMessage){show(null);}
  });
 });
 el.part.addEventListener('change',function(){
  var chosen=el.part.selectedOptions[0];
  if(chosen&&chosen.value){
    if(!el.rate.value)el.rate.value=money(chosen.dataset.rate||0);
    if(!el.gst.value)el.gst.value=money(chosen.dataset.gst||0);
  }
  updateBase();
 });
 // Browser can restore a selected option without firing 'change'.
 var first=el.part.selectedOptions[0];
 if(first&&first.value){
  el.pid.value=el.part.value;
  if(!el.rate.value)el.rate.value=money(first.dataset.rate||0);
  if(!el.gst.value)el.gst.value=money(first.dataset.gst||0);
 }
 show(null);
 // Existing FIX87 AJAX handler preserves the financial save route.
 // Validate the editable displays before it sends the submitted form.
 el.form.addEventListener('submit',function(e){
  var fields=[el.da,el.taxable,el.ga,el.final];
  var bad=fields.find(function(x){return !x.checkValidity();});
  if(bad){e.preventDefault();e.stopImmediatePropagation();bad.reportValidity();return;}
  if(!state()){e.preventDefault();e.stopImmediatePropagation();
    el.rate.reportValidity();el.qty.reportValidity();return;}
  show(null);
 },true);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
</script>
'''
if marker in cssjs: pass
# Append after FIX87's scripts so event handlers run after native selection updates.
s+='\n'+cssjs
if s.count('class="am88-amount-input"')!=4 or s.count(marker)!=1:
 raise SystemExit('FIX88_FINAL_VALIDATION_FAILED')
for endpoint in ["route('erp.job-cards.parts.direct',$job->id)",
                 "route('erp.job-cards.parts.update',[$job->id,$p->id])",
                 "route('erp.job-cards.parts.delete',[$job->id,$p->id])"]:
 if endpoint not in s:raise SystemExit('FIX88_ENDPOINT_LOST')
if mode=='check':
 print('FIX88_EDITABLE_PRECHECK_PASS_NO_WRITES')
 sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix88_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX88_VIEW_ONLY_EDITABLE_AMOUNTS_APPLIED')
