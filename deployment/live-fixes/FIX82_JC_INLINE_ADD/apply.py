#!/usr/bin/env python3
"""FIX82: no extra Direct Job Card Entry, forms inside Parts/Labour, one
unfolded ROT+customer billing panel. Original server actions are retained.
Pricing edits use original WIP, active-invoice, historical GST and audit checks.
Not a change to old JC totals nor separate standard hours/billing quantity.
"""
from pathlib import Path
import hashlib,sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
control=root/'app/Http/Controllers/Web/AdminWorkshopControlPageController.php'
expected={
 'view':'f71147948e9a8e51d1c78f56c0438b044908489bd96f3b85aa40fee9b77c9265',
 'control':'7a2eb684535f783c75b6c6ede606236476d7aff5d8ba79017550af1de90a87eb'
}
raw={'view':view.read_bytes(),'control':control.read_bytes()}
txt={key:b.decode('utf8','surrogateescape') for key,b in raw.items()}
marker='FIX82_JC_SINGLE_ENTRY_PANEL'
if all(marker in x for x in txt.values()):
 print('FIX82_ALREADY_APPLIED');sys.exit(0)
if any(marker in x for x in txt.values()):
 raise SystemExit('FIX82_PARTIAL_DEPLOY_ABORT')
for k,b in raw.items():
 h=hashlib.sha256(b).hexdigest()
 print('FIX82_SOURCE_SHA',k,h)
 if h!=expected[k]:raise SystemExit('FIX82_LIVE_SOURCE_CHANGED_ABORT_'+k)
def one(src,old,new,tag):
 n=src.count(old)
 print('FIX82_ANCHOR',tag,n)
 if n!=1:raise SystemExit('FIX82_ANCHOR_MISMATCH_'+tag)
 return src.replace(old,new,1)
s=txt['view']
startToken='{{-- FIX37_DIRECT_ENTRY_PANEL --}}'
endToken='<script>\nfunction am54FilterPartSelect(input){'
if s.count(startToken)!=1 or s.count(endToken)!=1:
 raise SystemExit('FIX82_DIRECT_START_END_NOT_UNIQUE')
start=s.index(startToken);end=s.index(endToken,start)
old=s[start:end]
assert old.count('class="jc-direct-grid"')==1
assert old.count('<form method="post"')==2
pStart=old.index('<form method="post" action="{{ route(\'erp.job-cards.parts.direct\',$job->id) }}"')
pEnd=old.index('</form>',pStart)+len('</form>')
pForm=old[pStart:pEnd]
lStart=old.index('<form method="post" action="{{ route(\'erp.job-cards.labour.direct\',$job->id) }}"')
lEnd=old.index('</form>',lStart)+len('</form>')
lForm=old[lStart:lEnd]
if len(pForm)<280 or len(lForm)<250:raise SystemExit('FIX82_FORM_EXTRACTION_BAD')
assert pForm.count('name="part_master_id"')==1
assert lForm.count('name="labour_master_id"')==1
s=s[:start]+s[end:]
# New single entry rows inside their home sections, existing WIP/invoice safeguards retained.
partBox='''{{-- FIX82_JC_SINGLE_ENTRY_PANEL: Part creation lives only inside Parts. --}}
@if(!in_array(strtolower((string)$job->status),['closed','invoiced','cancelled'],true))
<section class="am82-entry-block" aria-label="Add part to this Job Card">
 <h3>+ Add New Part</h3>
 <p class="muted">Search by name or part code, Enter to select, then Qty. Existing stock and JC rules still apply.</p>
'''+pForm+'''
</section>
@endif
'''
labBox='''{{-- FIX82_JC_SINGLE_ENTRY_PANEL: ROT creation lives only inside Labour/ROT. --}}
@if(in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id))
<section class="am82-entry-block" aria-label="Add ROT to this Job Card">
 <h3>+ Add New Labour / ROT</h3>
 <p class="muted">ROT Master selection first, then assign mechanic on the new row. Operational controls and customer billing are together below.</p>
'''+lForm+'''
</section>
@else
<p class="muted" style="padding:0 12px">New ROT allowed only during WIP without an active Invoice. Return via the authorised workflow if required.</p>
@endif
'''
# Keep same form endpoints, no new forms outside Parts and Labour.
partsHead='<div class="card section-gap jc-purchase-block jc-parts-block">'
s=one(s,partsHead,partsHead+"\n"+partBox,'parts_section_insertion')
labHead='<div class="card section-gap jc-purchase-block jc-labour-block">'
s=one(s,labHead,labHead+"\n"+labBox,'labour_section_insertion')
s=one(s,'<h2 class="section-title">Labour / ROT — Customer Billing</h2>',
       '<h2 class="section-title">Labour / ROT — Entry, Mechanic, Work Control &amp; Customer Billing</h2>',
       'unified_header')
s=one(s,'Customer labour charges upar item-wise; staff/mechanic ROT control neeche alag expandable panel mein. Existing ROT attendance and billing restrictions apply.',
 'Same ROT for Admin and Staff: select, assign, Start/Pause/Resume/Complete. Mechanic, actual time and standard hours are operational fields; billing is edited independently.',
 'unified_helper')
# Existing separate paid-amount editor removed; inline audited forms replace it.
beg='  {{-- V641_FINANCIAL_NAV: show billing editor at the operational stage where saves are permitted. --}}'
last='  <details class="am65-workshop-toggle" id="am65-rot-controls">'
if s.count(beg)!=1 or s.count(last)!=1:raise SystemExit('FIX82_EXISTING_FINANCIAL_LAYOUT_ANCHORS_INVALID')
a=s.index(beg);b=s.index(last,a)
s=s[:a]+'''  {{-- FIX82 customer billing and ROT history rendered together below.
       Existing GST-basis safety checks and admin audit remain server-authoritative. --}}
'''+s[b:]
# One unified visible panel rather than read-only billing table + collapsed ROT controls.
s=one(s,
'  <details class="am65-workshop-toggle" id="am65-rot-controls">\n    <summary><span>🔧 Workshop ROT / Mechanic Controls<br><small>Assign, Start, Pause/Resume, Complete, Reopen and History — click to expand</small></span></summary>',
'''  <div class="am82-rot-inline" id="am65-rot-controls">
    <div class="am82-rot-title">ROT Selection · Mechanic · Live Work Control · Customer Billing</div>''',
'unfold_rot_controls')
s=one(s,'  </details>\n</div>\n\n\n{{-- FIX71_REMOVED_REVERSAL_COMMENT',
'  </div>\n</div>\n\n\n{{-- FIX71_REMOVED_REVERSAL_COMMENT',
'unfold_rot_close')
# Financial rows show current rates, editable on WIP, with original guarded amount update route.
fin='''      @include('job-cards.rot-billing-control')
      {{-- FIX82_JC_SINGLE_ENTRY_PANEL: in-row WIP billing; no separate amount editor. --}}
      @php($am82Wip=in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id))
      @php($am82Tax=round((float)($line->total??0)*(float)($line->gst_percent??0)/100,2))
      @if($am82Wip)
      <form class="am82-finance-form" method="post"
         action="{{ route('erp.fix48.job-line',[$job->id,'labour',$line->id]) }}">
         @csrf
         <input type="hidden" name="quantity" value="{{ $line->standard_hours }}">
         <input type="hidden" name="expected_old_total" value="{{ $line->total }}">
         <input type="hidden" name="return_to_jc" value="1">
         <label>Std Hrs (locked)<input value="{{ number_format((float)$line->standard_hours,2) }}" readonly tabindex="-1"></label>
         <label>Rate before GST (₹ / Std Hr)<input name="rate" type="number" step="0.01" min="0"
                    value="{{ number_format((float)$line->labour_rate,2,'.','') }}" required></label>
         <label>Discount %<input name="discount_percent" type="number" step="0.01" min="0" max="100"
                    value="{{ number_format((float)($line->discount_percent??0),2,'.','') }}" required></label>
         <label>GST %<input name="gst_percent" type="number" step="0.01" min="0" max="100"
                    value="{{ number_format((float)($line->gst_percent??0),2,'.','') }}" required></label>
         <label>GST Amount ₹<input class="am82-tax-output" value="{{ number_format($am82Tax,2,'.','') }}" readonly tabindex="-1"></label>
         <label>Customer Total ₹<input class="am82-final-output" type="number" min="0" step="0.01" value="{{ number_format((float)$line->total+$am82Tax,2,'.','') }}" title="Edit total to reverse-calculate rate at existing Std Hours and GST"></label>
         <label class="am82-note">Correction Reason *<input name="reason" required minlength="5" maxlength="1000" placeholder="Reason for billing change"></label>
         <div class="am82-actions"><button class="btn small primary" type="submit">Save Billing</button></div>
      </form>
      <p class="am82-scope-help">Current billing formula uses existing Std Hours × rate. Independent Qty=1 billing engine requires a separate audited data migration. Mechanic, times and Std Hrs cannot be changed here. Historical GST mismatches require tax-basis review first.</p>
      @else
      <div class="am82-saved-price"><span>Std Hrs {{ number_format((float)$line->standard_hours,2) }} (locked)</span>
        <span>Rate before GST ₹{{ number_format((float)$line->labour_rate,2) }}</span>
        <span>Discount {{ number_format((float)($line->discount_percent??0),2) }}%</span>
        <span>GST {{ number_format((float)($line->gst_percent??0),2) }}% = ₹{{ number_format($am82Tax,2) }}</span>
        <strong>Customer Total ₹{{ number_format((float)$line->total+$am82Tax,2) }}</strong>
      </div>
      @endif'''
s=one(s,"      @include('job-cards.rot-billing-control')",fin,'inline_per_rot_finance')
# Show the existing parts selection search and labour select as searchable keyboard-first inputs.
labSel='<label class="wide">Labour / ROT<select name="labour_master_id" required>'
labSearch='<label class="wide">Search ROT by name/code<input type="search" id="am82-rot-search" autocomplete="off" placeholder="Type ROT name/code, Enter to select" aria-label="Search ROT"></label>\n        <label class="wide">Labour / ROT<select id="am82-rot-select" name="labour_master_id" required>'
s=one(s,labSel,labSearch,'labour_typeahead')
s=one(s,'<label>Std Hours<input type="number" step="0.01" min="0.01" name="standard_hours" placeholder="Master hours"></label>',
'''<label>Std Hours (from Master)<input id="am82-new-std" type="text" placeholder="Auto from ROT Master" readonly></label>''',
'standard_hours_readonly_on_add')
# Store master attributes for live preview (without changing defaults on server).
s=one(s,
'<option value="{{ $m->id }}">{{ $m->rot_code }} · {{ $m->rot_description }}</option>',
'<option value="{{ $m->id }}" data-hours="{{ $m->standard_hours }}" data-rate="{{ $m->labour_rate }}" data-gst="{{ $m->gst_percent }}">{{ $m->rot_code }} · {{ $m->rot_description }}</option>',
'master_option_metadata')
s=one(s,
 '<label>Rate<input type="number" step="0.01" min="0" name="labour_rate" placeholder="Master rate"></label>',
 '<label>Rate before GST<input type="number" step="0.01" min="0" name="labour_rate" placeholder="Master rate"></label>',
 'new_rot_rate_label')
s=one(s,
 '<label class="wide">Note<input name="note" placeholder="Work note (optional)"></label>',
 '<label>Customer Total ₹<input id="am82-add-labour-total" readonly placeholder="Select ROT"></label>\n        <label class="wide">Note<input name="note" placeholder="Work note (optional)"></label>',
 'new_rot_live_total')
# Add fluid on-page style and JS after part search existing script, no browser POST simulation.
enhance=r'''
<style id="FIX82_JC_SINGLE_ENTRY_PANEL">
.am82-entry-block{margin:8px 12px 14px;padding:13px 14px;border:1px solid #c0d8ec;
 border-left:4px solid #1b619e;border-radius:10px;background:#f5faff}
.am82-entry-block h3{margin:0 0 5px;color:#15416e;font-size:14px}
.am82-entry-block .jc-entry-form{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:9px;align-items:end}
.am82-entry-block .jc-entry-form label{min-width:0;font-size:11px;font-weight:750}
.am82-entry-block .jc-entry-form label.wide{grid-column:span 2}
.am82-entry-block .jc-entry-form input,.am82-entry-block .jc-entry-form select{
 width:100%;min-height:35px}
.am82-entry-block .jc-entry-form .actions{grid-column:1/-1;justify-content:flex-end}
.am82-rot-inline{display:block;max-width:100%;padding:0 10px 10px}
.am82-rot-title{padding:11px 10px;font-size:12px;font-weight:850;color:#15416e;background:#eaf3ff;
 margin-bottom:9px;border:1px solid #c8dbf3;border-radius:8px}
.am82-finance-form{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:9px;
 padding:12px;margin-top:9px;border:1px solid #bfd5f0;border-radius:9px;background:#f4f8ff}
.am82-finance-form label{font-size:11px;font-weight:750;color:#244b78}
.am82-finance-form input{width:100%;min-height:34px;padding:6px 8px;margin-top:3px;
 border:1px solid #b5cde8;border-radius:6px}
.am82-finance-form input[readonly]{background:#e7edf5;color:#37455a}
.am82-finance-form .am82-note{grid-column:span 4}
.am82-finance-form .am82-actions{grid-column:span 2;align-self:end;text-align:right}
.am82-finance-form .am82-final-output{border-color:#3471b4;background:white;font-weight:900}
.am82-scope-help{font-size:10px;color:#7c5b1b;padding:5px 9px}
.am82-saved-price{display:flex;flex-wrap:wrap;gap:12px;padding:9px 10px;
 border:1px solid #c8d9ed;border-radius:8px;background:#f0f5fc;font-size:11px}
.am82-saved-price strong{color:#134b85}
@media(max-width:1120px){
.am82-finance-form{grid-template-columns:repeat(3,minmax(130px,1fr))}
.am82-entry-block .jc-entry-form{grid-template-columns:repeat(2,minmax(120px,1fr))}
}
@media(max-width:640px){
.am82-finance-form{grid-template-columns:repeat(2,minmax(115px,1fr))}
.am82-finance-form .am82-note{grid-column:span 2}
.am82-finance-form .am82-actions{grid-column:span 2}
.am82-entry-block .jc-entry-form label.wide{grid-column:span 2}
}
</style>
<script>
(function(){
  'use strict';
  const money = n=>Math.round((Number(n)||0)*100)/100;
  const f = n=>money(n).toFixed(2);
  const searchRot=document.getElementById('am82-rot-search');
  const selectRot=document.getElementById('am82-rot-select');
  const std=document.getElementById('am82-new-std');
  if(searchRot&&selectRot){
    const matches=()=>{
      const query=searchRot.value.trim().toLowerCase();
      for(const opt of selectRot.options)opt.hidden=Boolean(query&&opt.value&&!opt.text.toLowerCase().includes(query));
      const selected=selectRot.selectedOptions[0];
      if(selected?.hidden)selectRot.value='';
    };
    searchRot.addEventListener('input',matches);
    searchRot.addEventListener('keydown',ev=>{
      if(ev.key!=='Enter')return;
      const opt=Array.from(selectRot.options).find(x=>x.value&&!x.hidden);
      if(!opt)return;
      ev.preventDefault();selectRot.value=opt.value;
      selectRot.dispatchEvent(new Event('change',{bubbles:true}));
      selectRot.closest('form')?.querySelector('[name="labour_rate"]')?.focus();
    });
    const sync=()=>{
      const opt=selectRot.selectedOptions[0];
      if(!opt||!opt.value)return;
      if(std)std.value=f(opt.dataset.hours||1);
      for(const [name,key] of [['labour_rate','rate'],['gst_percent','gst']]){
        const input=selectRot.closest('form')?.querySelector('[name="'+name+'"]');
        if(input)input.value=f(opt.dataset[key]||0);
      }
      selectRot.closest('form')?.querySelector('[name="labour_rate"]')?.dispatchEvent(new Event('input'));
    };
    selectRot.addEventListener('change',sync);
  }
  const partSearch=document.querySelector('form[action*="/parts/direct"] input[type=search]');
  if(partSearch)partSearch.addEventListener('keydown',ev=>{
    if(ev.key!=='Enter')return;
    const form=partSearch.closest('form'),sel=form.querySelector('[name="part_master_id"]');
    const query=partSearch.value.trim().toLowerCase();
    const opt=Array.from(sel?.options||[]).find(o=>o.value&&o.text.toLowerCase().includes(query));
    if(!opt)return;
    ev.preventDefault();sel.value=opt.value;form.querySelector('[name="qty"]')?.focus();
  });
  const addForm=selectRot?.closest('form');
  if(addForm){
    const preview=()=>{
      const hrs=Number(std?.value||selectRot.selectedOptions[0]?.dataset.hours||1);
      const rate=Number(addForm.querySelector('[name="labour_rate"]')?.value||selectRot.selectedOptions[0]?.dataset.rate||0);
      const disc=Number(addForm.querySelector('[name="discount_percent"]')?.value||0);
      const tax=Number(addForm.querySelector('[name="gst_percent"]')?.value||0);
      const base=money(hrs*rate*(1-disc/100));
      const out=addForm.querySelector('#am82-add-labour-total');if(out)out.value=f(base+money(base*tax/100));
    };
    for(const input of addForm.querySelectorAll('input,select'))input.addEventListener('input',preview);
    addForm.addEventListener('submit',()=>{
      try{sessionStorage.setItem('am82-next-rot','yes');}catch(_){}
    });
    try{
      if(sessionStorage.getItem('am82-next-rot')==='yes'){
        sessionStorage.removeItem('am82-next-rot');
        if(document.visibilityState!=='hidden')searchRot?.focus();
      }
    }catch(_){}
  }
  for(const form of document.querySelectorAll('.am82-finance-form')){
    const hrs=Number(form.querySelector('[name="quantity"]')?.value||0);
    const rate=form.querySelector('[name="rate"]'),disc=form.querySelector('[name="discount_percent"]');
    const gst=form.querySelector('[name="gst_percent"]'),tax=form.querySelector('.am82-tax-output');
    const total=form.querySelector('.am82-final-output');
    if(!rate||!disc||!gst||!tax||!total)continue;
    let syncing=false;
    const compute=()=>{
      if(syncing)return;syncing=true;
      const taxable=money(hrs*Number(rate.value||0)*(1-Number(disc.value||0)/100));
      const t=money(taxable*Number(gst.value||0)/100);
      tax.value=f(t);total.value=f(taxable+t);syncing=false;
    };
    for(const field of [rate,disc,gst])field.addEventListener('input',compute);
    total.addEventListener('change',()=>{
      const target=Number(total.value),d=(1-Number(disc.value||0)/100),
        multiplier=hrs*d*(1+Number(gst.value||0)/100);
      if(!Number.isFinite(target)||target<0||multiplier<=0)return compute();
      rate.value=f(target/multiplier);compute();
    });
    form.addEventListener('keydown',e=>{
      if(e.key==='Enter' && e.target?.tagName==='INPUT'){
        e.preventDefault();
        const next=form.querySelector('[name="reason"]');
        if(next)next.focus();
      }
    });
  }
})();
</script>
'''
# Put after original search helper script and before parts panel, so both are reachable on render.
anchor='</script>\n<div class="card section-gap jc-purchase-block jc-parts-block">'
s=one(s,anchor,'</script>\n'+enhance+'\n<div class="card section-gap jc-purchase-block jc-parts-block">','css_js_insertion')
# Update controller only to return to SAME JC after approved in-page rate correction.
c=txt['control']
oldRoute="return redirect()->route('erp.fix48.job-amounts',$job)->with('success','Amount recalculated; GST separately posted to Job Card total.');"
newRoute="""// FIX82_JC_SINGLE_ENTRY_PANEL: in-page WIP rate save returns to Job Card.
        $destination=$request->boolean('return_to_jc')?'erp.job-cards.show':'erp.fix48.job-amounts';
        return redirect()->route($destination,$job)->with('success','Amount recalculated; GST separately posted to Job Card total.');"""
c=one(c,oldRoute,newRoute,'same_job_redirect')
# Safety source-level: all original POSTs, tax guard and session action routes remain.
for token in ["route('erp.job-cards.parts.direct',$job->id)",
              "route('erp.job-cards.labour.direct',$job->id)",
              "route('erp.job-cards.labour.assign',[$job->id,$line->id])",
              "route('erp.rot.start',$rot->id)",
              "route('erp.rot.pause',$rot->id)",
              "route('erp.rot.resume',$rot->id)",
              "route('erp.rot.complete',$rot->id)",
              "route('erp.fix48.job-line',[$job->id,'labour',$line->id])"]:
 if s.count(token)<1:raise SystemExit('FIX82_OPERATIONAL_LINK_LOST_'+token)
for token in ['class="jc-direct-entry"','Direct Job Card Entry',
              "@include('job-cards.partials.am65-customer-labour')",
              'class="am65-workshop-toggle"']:
 if token in s:raise SystemExit('FIX82_SEPARATE_PANEL_STILL_PRESENT_'+token)
if s.count('FIX82_JC_SINGLE_ENTRY_PANEL')<4 or c.count('FIX82_JC_SINGLE_ENTRY_PANEL')!=1:
 raise SystemExit('FIX82_MARKER_INCOMPLETE')
if s.count('<select name="part_master_id" required>')!=1 or s.count('<select id="am82-rot-select" name="labour_master_id" required>')!=1:
 raise SystemExit('FIX82_DUPLICATE_PART_LABOUR_FORM')
if s.count('am82-finance-form')<3:
 raise SystemExit('FIX82_INLINE_EDIT_FORM_MISSING')
if mode=='check':
 print('FIX82_TWO_FORM_RELOCATION_UNIFIED_ROT_PREFLIGHT_PASS');sys.exit(0)
def write(path,value):
 fd,tmp=tempfile.mkstemp(prefix='.fix82-',dir=path.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(value)
  os.chmod(tmp,path.stat().st_mode&0o777)
  os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
write(view,s);write(control,c)
print('FIX82_JC_VIEW_AND_INPLACE_REDIRECT_APPLIED_NO_FINANCIAL_POSTINGS')
