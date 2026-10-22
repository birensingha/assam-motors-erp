#!/usr/bin/env python3
"""FIX90 STAGING: user's Excel-like Labour billing table plus Edit action.
Rendering/UI-only; existing authorized labour edit form and Laravel handler
unchanged. Existing ROT operational cards and OSL remain intact.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes()
sha=hashlib.sha256(raw).hexdigest()
print('FIX90_TARGET_SHA256',sha)
expected='4dab7046b638cbe483a8778c32cfbc23848107cc93880166849183779cac2c55'
s=raw.decode('utf8','surrogateescape')
marker='id="FIX90_LABOUR_EXCEL_BILLING_GRID"'
if marker in s:
 if s.count(marker)==1 and 'data-am90-labour-id' in s:
  print('FIX90_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX90_INCOMPLETE_PATCH')
if sha!=expected:raise SystemExit('FIX90_SOURCE_CHANGED_ABORT')
def one(a,b,label):
 global s
 n=s.count(a)
 print('FIX90_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX90_ANCHOR_'+label+'_INVALID')
 s=s.replace(a,b,1)
billing=r'''
    {{-- FIX90 Labour Billing Summary: Excel columns; original ROT/work controls stay below. --}}
    @php($am90CanEdit=in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id))
    <section class="am90-billing" aria-label="Labour billing register" id="am90-labour-billing">
      <div class="am90-billing-head"><strong>Labour Billing Register</strong><small>Std Hrs locked · Edit requires Correction Reason</small></div>
      <div class="am90-billing-width">
        <table class="am90-table">
          <colgroup><col style="width:4%"><col style="width:16.5%"><col style="width:11%">
           <col style="width:6%"><col style="width:6.5%"><col style="width:8%">
           <col style="width:5%"><col style="width:7.5%"><col style="width:8.5%">
           <col style="width:5.5%"><col style="width:8%"><col style="width:8.5%">
           <col style="width:5%"></colgroup>
          <thead><tr><th>SL NO</th><th>LABOUR</th><th>ROT</th><th>SAC</th>
           <th>STD HRS</th><th>RATE</th><th>DIS%</th><th>DIS AMT</th>
           <th>TAXABLE</th><th>GST%</th><th>GST AMT</th><th>FINAL AMT</th><th>EDIT</th></tr></thead>
          <tbody id="am90-table-body">
          @forelse($am83RotLabour as $line)
            @php($am90Gross=round((float)($line->standard_hours??0)*(float)($line->labour_rate??0),2))
            @php($am90Disc=round($am90Gross*(float)($line->discount_percent??0)/100,2))
            @php($am90Taxable=round((float)($line->total??0),2))
            @php($am90GstAmount=round($am90Taxable*(float)($line->gst_percent??0)/100,2))
            <tr data-am90-row="{{ $line->id }}">
             <td class="am90-num">{{ $loop->iteration }}</td>
             <td class="am90-labour-name">{{ $line->description ?: 'Labour' }}</td>
             <td>{{ $line->rot_code ?: '—' }}</td>
             <td>{{ $line->sac_code ?? $line->sac ?? $line->hsn_sac ?? '—' }}</td>
             <td class="am90-num">{{ number_format((float)($line->standard_hours??0),2) }}</td>
             <td class="am90-num">₹{{ number_format((float)($line->labour_rate??0),2) }}</td>
             <td class="am90-num">{{ number_format((float)($line->discount_percent??0),2) }}%</td>
             <td class="am90-num">₹{{ number_format($am90Disc,2) }}</td>
             <td class="am90-num">₹{{ number_format($am90Taxable,2) }}</td>
             <td class="am90-num">{{ number_format((float)($line->gst_percent??0),2) }}%</td>
             <td class="am90-num">₹{{ number_format($am90GstAmount,2) }}</td>
             <td class="am90-num am90-final">₹{{ number_format($am90Taxable+$am90GstAmount,2) }}</td>
             <td class="am90-action">
               @if($am90CanEdit)<button class="am90-edit" type="button" data-am90-edit="{{ $line->id }}">Edit</button>
               @else <span class="am90-locked" title="Edit allowed during Work In Progress without an active invoice">Locked</span>@endif
             </td>
            </tr>
          @empty
            <tr class="am90-empty-row"><td colspan="13">No Labour/ROT items yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <div class="am90-work-label">Mechanic &amp; ROT Work Controls</div>
'''
old='    <div class="am83-grid-header"><span>LABOUR / WORK + SOURCE</span><span>MECHANIC · ROT STATUS · ACTUAL TIME</span><span>STD HRS (LOCKED)</span><span>RATE / DISC / GST / CUSTOMER TOTAL</span></div>'
one(old,billing,'labour_grid_header')
card='<div class="rot-card rot-{{ strtolower($rot->status ?? \'unassigned\') }}">'
extraCard='''<div class="rot-card rot-{{ strtolower($rot->status ?? 'unassigned') }}"
      data-am90-labour-id="{{ $line->id }}"
      data-am90-description="{{ $line->description ?: 'Labour' }}"
      data-am90-rot="{{ $line->rot_code ?: '—' }}"
      data-am90-sac="{{ $line->sac_code ?? $line->sac ?? $line->hsn_sac ?? '—' }}"
      data-am90-hours="{{ number_format((float)($line->standard_hours??0),3,'.','') }}"
      data-am90-rate="{{ number_format((float)($line->labour_rate??0),2,'.','') }}"
      data-am90-discount="{{ number_format((float)($line->discount_percent??0),2,'.','') }}"
      data-am90-taxable="{{ number_format((float)($line->total??0),2,'.','') }}"
      data-am90-gst="{{ number_format((float)($line->gst_percent??0),2,'.','') }}"
      data-am90-can-edit="{{ $am90CanEdit ? '1' : '0' }}">'''
one(card,extraCard,'rot_card_data')
ui=r'''
{{-- FIX90 single Excel-like Labour billing table, with audited billing form reused in modal. --}}
<style id="FIX90_LABOUR_EXCEL_BILLING_GRID">
.jc-labour-block .am90-billing{margin:9px 0 12px!important;border:1px solid #bacddd;
 border-radius:8px!important;background:#fff!important;overflow:visible!important}
.jc-labour-block .am90-billing-head{display:flex;align-items:center;justify-content:space-between;
 gap:8px;flex-wrap:wrap;padding:7px 9px;background:#f1f6ff;border-bottom:1px solid #d5e3f2}
.jc-labour-block .am90-billing-head strong{font-size:12px!important;color:#153e68}
.jc-labour-block .am90-billing-head small{font-size:10px;color:#55718a}
.jc-labour-block .am90-billing-width{width:100%;overflow-x:visible;overflow-y:visible}
.jc-labour-block table.am90-table{width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;
 margin:0!important;font-family:inherit!important;white-space:normal}
.jc-labour-block .am90-table th{position:sticky;top:0;z-index:11;
 background:#10365d!important;color:#fff!important;border:1px solid #d0dfef!important;
 font-size:9px!important;font-weight:850!important;padding:7px 3px!important;
 line-height:1.15!important;text-align:center!important;white-space:normal!important}
.jc-labour-block .am90-table td{border:1px solid #d6e2ef!important;
 padding:8px 4px!important;font-size:10.5px!important;color:#264767!important;
 vertical-align:middle!important;overflow-wrap:anywhere!important;
 background:#fff;line-height:1.3!important}
.jc-labour-block .am90-table tbody tr:nth-child(even) td{background:#f7faff}
.jc-labour-block .am90-table .am90-num{text-align:right!important;font-variant-numeric:tabular-nums;white-space:nowrap}
.jc-labour-block .am90-table td.am90-labour-name{font-size:11px!important;font-weight:650!important}
.jc-labour-block .am90-table .am90-final{font-weight:850!important;color:#096940!important;background:#f0fff6!important}
.jc-labour-block .am90-table .am90-action{text-align:center!important}
.jc-labour-block .am90-edit{border:1px solid #3375b3!important;background:#eff7ff!important;color:#0b4e88!important;
 border-radius:5px!important;cursor:pointer!important;font-size:10px!important;font-weight:750!important;
 padding:5px 7px!important;min-height:27px!important}
.jc-labour-block .am90-edit:hover{background:#d8eeff!important}
.jc-labour-block .am90-locked{font-size:9px;color:#6c8296}
.jc-labour-block .am90-empty-row td{text-align:center!important;color:#60788d!important;padding:18px!important}
.jc-labour-block .am90-work-label{margin:12px 0 4px;font-size:12px;font-weight:800;color:#1a6851;
 border-bottom:1px solid #c9e3d7;padding:4px 2px}
.jc-labour-block .am83-grid-header{display:none!important}
.jc-labour-block .rot-list .rot-card .am82-finance-form{display:none!important}
.jc-labour-block .rot-list .rot-card .am82-saved-price{display:none!important}
.jc-labour-block .rot-list .rot-card .am82-scope-help{display:none!important}
dialog.am87-dialog.am90-dialog{width:min(1020px,calc(100vw - 30px))!important;max-width:1020px!important}
.am90-dialog .am90-editor-context{font-size:11px!important;color:#466783!important;
 margin:0 0 12px!important}
.am90-dialog .am82-finance-form{display:grid!important;grid-template-columns:repeat(6,minmax(0,1fr))!important;
 gap:8px!important;border:0!important;padding:0!important;box-shadow:none!important;background:white!important;
 margin:0!important}
.am90-dialog .am82-finance-form>label{display:block!important;min-width:0!important;
 font-size:10px!important;font-weight:760!important;color:#244b78!important}
.am90-dialog .am82-finance-form input{box-sizing:border-box!important;width:100%!important;
 max-width:100%!important;min-height:33px!important;margin-top:4px!important;
 padding:5px 6px!important;font-size:11px!important;border:1px solid #b6cee6!important;
 background:#fff!important;color:#173b5d!important;border-radius:5px!important}
.am90-dialog .am82-finance-form input[readonly]{background:#eaf0f8!important;color:#44566b!important}
.am90-dialog .am82-finance-form>.am82-note{grid-column:span 5!important;align-self:end!important}
.am90-dialog .am82-finance-form>.am82-actions{grid-column:span 1!important;
 align-self:end!important;text-align:right!important}
.am90-dialog .am82-finance-form>.am82-actions button{width:100%!important;min-height:33px!important;
 font-size:11px!important;white-space:nowrap!important}
@media(max-width:1110px){
 .jc-labour-block .am90-billing-width{overflow-x:auto!important}
 .jc-labour-block table.am90-table{min-width:1060px!important}
}
@media(max-width:700px){
 .am90-dialog .am82-finance-form{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .am90-dialog .am82-finance-form>.am82-note{grid-column:span 2!important}
 .am90-dialog .am82-finance-form>.am82-actions{grid-column:span 2!important}
}
</style>
<script>
(function(){
'use strict';
function boot(){
  var body=document.getElementById('am90-table-body');
  var list=document.querySelector('.jc-labour-block .rot-list');
  if(!body||!list)return;
  var modal=null,originalForm=null,originalParent=null,originalNext=null;
  function asMoney(v){var n=Number(v)||0;return '₹'+n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function decimal(v){return (Number(v)||0).toFixed(2);}
  function put(row,txt,clazz){
    var cell=document.createElement('td');
    if(clazz)cell.className=clazz;
    cell.textContent=txt;row.appendChild(cell);
    return cell;
  }
  function rebuild(){
    var cards=Array.from(list.children).filter(function(el){return el.matches('.rot-card[data-am90-labour-id]');});
    body.replaceChildren();
    if(!cards.length){
      var row=document.createElement('tr');row.className='am90-empty-row';
      var td=document.createElement('td');td.colSpan=13;td.textContent='No Labour/ROT items yet.';
      row.appendChild(td);body.appendChild(row);return;
    }
    cards.forEach(function(card,i){
      var d=card.dataset,rate=Number(d.am90Rate)||0,hours=Number(d.am90Hours)||0;
      var discount=Number(d.am90Discount)||0,taxable=Number(d.am90Taxable)||0,gst=Number(d.am90Gst)||0;
      var gross=Math.round(hours*rate*100)/100,disc=Math.round(gross*discount)/100;
      var tax=Math.round(taxable*gst)/100;
      var row=document.createElement('tr');row.dataset.am90Row=d.am90LabourId;
      put(row,String(i+1),'am90-num');put(row,d.am90Description||'Labour','am90-labour-name');
      put(row,d.am90Rot||'—');put(row,d.am90Sac||'—');
      put(row,decimal(hours),'am90-num');put(row,asMoney(rate),'am90-num');
      put(row,decimal(discount)+'%','am90-num');put(row,asMoney(disc),'am90-num');
      put(row,asMoney(taxable),'am90-num');put(row,decimal(gst)+'%','am90-num');
      put(row,asMoney(tax),'am90-num');put(row,asMoney(taxable+tax),'am90-num am90-final');
      var action=document.createElement('td');action.className='am90-action';
      if(d.am90CanEdit==='1'){
        var b=document.createElement('button');b.type='button';b.className='am90-edit';
        b.dataset.am90Edit=d.am90LabourId;b.textContent='Edit';action.appendChild(b);
      }else{
        var span=document.createElement('span');span.className='am90-locked';span.textContent='Locked';
        span.title='Billing edits permitted during WIP without invoice';action.appendChild(span);
      }
      row.appendChild(action);body.appendChild(row);
    });
  }
  function closeDialog(){
    if(!modal)return;
    var d=modal;
    modal=null;
    if(originalForm&&originalParent&&originalParent.isConnected){
      originalParent.insertBefore(originalForm,originalNext&&originalNext.parentNode===originalParent?originalNext:null);
    }
    originalForm=null;originalParent=null;originalNext=null;
    if(d.open)d.close();
    d.remove();
  }
  function showEditor(id){
    closeDialog();
    var card=Array.from(list.querySelectorAll('.rot-card[data-am90-labour-id]'))
      .find(function(c){return c.dataset.am90LabourId===String(id);});
    if(!card)return;
    var form=card.querySelector('form.am82-finance-form');
    if(!form)return;
    var row=body.querySelector('tr[data-am90-row="'+String(id)+'"]');
    originalForm=form;originalParent=form.parentNode;originalNext=form.nextSibling;
    var d=document.createElement('dialog');d.className='am87-dialog am90-dialog';
    d.dataset.am87Zone='labour';
    d.setAttribute('aria-label','Edit Labour billing');
    var head=document.createElement('div');head.className='am87-dialog-head';
    var strong=document.createElement('strong');strong.textContent='Edit Labour Billing';
    var close=document.createElement('button');close.type='button';
    close.className='am87-dialog-close';close.textContent='×';
    close.setAttribute('aria-label','Close billing editor');
    close.addEventListener('click',closeDialog);
    head.appendChild(strong);head.appendChild(close);
    var box=document.createElement('div');box.className='am87-dialog-content';
    var context=document.createElement('p');context.className='am90-editor-context';
    context.textContent=card.dataset.am90Rot+' · '+card.dataset.am90Description+' · Std Hrs locked';
    box.appendChild(context);box.appendChild(form);
    d.appendChild(head);d.appendChild(box);
    document.body.appendChild(d);
    modal=d;
    d.addEventListener('cancel',function(e){e.preventDefault();closeDialog();});
    d.addEventListener('click',function(e){if(e.target===d)closeDialog();});
    d.showModal();
    var field=form.querySelector('input[name=rate]');
    if(field)field.focus();
  }
  body.addEventListener('click',function(e){
    var btn=e.target.closest('button[data-am90-edit]');
    if(btn)showEditor(btn.dataset.am90Edit);
  });
  // FIX87 replaces ROT rows after successful AJAX save. Refresh this single
  // billing table from the newly returned server-rendered rows, then close edit.
  new MutationObserver(function(){
    rebuild();
    if(modal)closeDialog();
  }).observe(list,{childList:true});
  // Server renders table initially. Rebuild uses its paired ROT cards for updates.
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
else boot();
})();
</script>
'''
s+='\n'+ui
for t in ['id="am90-labour-billing"','id="am90-table-body"','data-am90-labour-id','class="am82-finance-form"',
          "route('erp.fix48.job-line',[$job->id,'labour',$line->id])",
          "route('erp.job-cards.labour.direct',$job->id)",
          'OSL Jobs / Outside Services']:
 if t not in s: raise SystemExit('FIX90_LOST_CRITICAL_ANCHOR_'+t)
if s.count(marker)!=1 or s.count('id="am90-labour-billing"')!=1:
 raise SystemExit('FIX90_MARKER_FAIL')
if mode=='check':print('FIX90_EXCEL_LABOUR_TABLE_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.fix90_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,view.stat().st_mode&0o777)
 os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX90_LABOUR_TABLE_AND_EDIT_UI_APPLIED_NO_FINANCIAL_WRITES')
