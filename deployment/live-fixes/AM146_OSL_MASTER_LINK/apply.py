#!/usr/bin/env python3
"""AM146 STAGING guarded patch: OSL master shared, JC billing edit calculator,
Admin audited remove/exclude, OSL purchase exact line linkage.
check|apply APPROOT. Backup + transactional source file replacement.
"""
from pathlib import Path
from datetime import datetime
import os,sys,hashlib,tempfile,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'): raise SystemExit('AM146_USAGE check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():raise SystemExit('AM146_NOT_STAGING')
names=['routes/web.php','resources/views/job-cards/show.blade.php','resources/views/purchases/osl-create.blade.php',
'app/Http/Controllers/Web/AdminPurchasePageController.php','app/Http/Controllers/Web/AdminJobCardOslLineController.php','app/Services/PurchaseService.php']
paths={n:root/n for n in names}
controllerSource=Path(__file__).with_name('AdminOslQuickMasterController.php')
if not controllerSource.is_file():raise SystemExit('AM146_CONTROLLER_PACKAGE_MISSING')
for n,p in paths.items():
 if not p.is_file() or p.is_symlink():raise SystemExit('AM146_MISSING_'+n)
original={n:p.read_bytes() for n,p in paths.items()}
s={n:b.decode('utf8','surrogateescape') for n,b in original.items()}
routes='routes/web.php';jc='resources/views/job-cards/show.blade.php';pv='resources/views/purchases/osl-create.blade.php'
pc='app/Http/Controllers/Web/AdminPurchasePageController.php';oc='app/Http/Controllers/Web/AdminJobCardOslLineController.php';ps='app/Services/PurchaseService.php'
mark='AM146_OSL_MASTER'
if mark in s[jc] and 'erp.osl-masters.quick-create' in s[routes]:
 print('AM146_ALREADY_INSTALLED');sys.exit(0)
if mark in ''.join(s.values()):raise SystemExit('AM146_PARTIAL_PATCH_ABORT')
def once(n,old,new,label):
 if s[n].count(old)!=1: raise SystemExit(f'AM146_ANCHOR_{label}_COUNT={s[n].count(old)}')
 s[n]=s[n].replace(old,new,1)
# Endpoint within protected ERP route group
once(routes, '    // FIX91 Strict Admin-only direct OSL billing; supplier purchase is separate.',
'''    // AM146_OSL_MASTER: shared Admin OSL Master Quick Create for JC and supplier purchase.
    \\Illuminate\\Support\\Facades\\Route::post('/osl-masters/quick-create',
      [\\App\\Http\\Controllers\\Web\\AdminOslQuickMasterController::class,'store'])
      ->name('erp.osl-masters.quick-create');
    // FIX91 Strict Admin-only direct OSL billing; supplier purchase is separate.''','route')
# OSL billing financial fields, master name, detailed discount amount.
once(oc, "          'description'=>['required','string','max:255'],",
"""          'description'=>['required','string','max:255'],
          'osl_master_id'=>['nullable','integer','min:1'],
          'discount_amount'=>['nullable','numeric','min:0','max:999999999'],""",'osl_validate')
once(oc,"          $qty=round((float)$d['quantity'],2);",
"""          // AM146_OSL_MASTER: selecting master is authoritative, free text
          // remains accepted only for existing historical direct OSL rows.
          $master=null;
          if(!empty($d['osl_master_id'])){
            $master=DB::table('workshop_osl_master')->where('id',(int)$d['osl_master_id'])->where('active',1)->first();
            if(!$master) throw ValidationException::withMessages(['osl_master_id'=>'Select an active OSL Master item.']);
            if(mb_strtoupper(trim((string)$d['description']))!==mb_strtoupper(trim((string)$master->osl_name)))
                throw ValidationException::withMessages(['osl_master_id'=>'OSL description differs from selected Master. Select the item again.']);
          }
          $qty=round((float)$d['quantity'],2);""",'master_validate')
once(oc, "          $off=round($base*$discount/100,2);",
"""          $off=isset($d['discount_amount']) ? round((float)$d['discount_amount'],2) : round($base*$discount/100,2);
          if($off>$base+0.009) throw ValidationException::withMessages(['discount_amount'=>'Discount cannot exceed OSL gross amount.']);
          $discount=$base>0 ? round(100*$off/$base,2) : 0;""",'discount')
once(oc, "'rot_code'=>'OSL','mechanic_id'=>0,", "'rot_code'=>$master ? (string)$master->osl_code : 'OSL','mechanic_id'=>0,",'new_code')
# Delete: linked estimate -> audited void/exclusion, direct line -> hard delete with audit,
# refuse if ACTIVE supplier purchase currently references line.
once(oc,"            if(!empty($old->source_estimate_item_id))\n                throw ValidationException::withMessages(['reason'=>'OSL from approved estimate cannot be deleted directly. Reverse the estimate release first.']);",
"""            // AM146_OSL_MASTER: approved estimate stays immutable; void only
            // this JC's billing inclusion with an audit, not approved estimate data.
            $estimateLinked=!empty($old->source_estimate_item_id);
            if((int)($old->billing_included??1)===0)
                throw ValidationException::withMessages(['reason'=>'OSL billing line was already excluded. Refresh JC.']);""",'estimate_void')
once(oc,"                foreach(['customer_billing_line_id','job_card_labour_id','billing_line_id'] as $col){",
"                foreach(['labour_line_id','customer_billing_line_id','job_card_labour_id','billing_line_id'] as $col){",'purchase_id_list')
once(oc,"DB::table('workshop_purchases')->where('job_card_id',$job)->where($col,$line)->exists())",
"""DB::table('workshop_purchases')->where('job_card_id',$job)->where($col,$line)
                            ->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'")
                            ->whereNull('deleted_at')->exists())""",'active_link_guard')
once(oc,"            DB::table('job_card_labour')->where('id',$line)->where('job_card_id',$job)->delete();",
"""            if($estimateLinked){
                DB::table('job_card_labour')->where('id',$line)->where('job_card_id',$job)->update([
                    'billing_included'=>0,
                    'billing_reason'=>'Admin excluded approved-estimate OSL billing: '.$data['reason'],
                ]);
            }else{
                DB::table('job_card_labour')->where('id',$line)->where('job_card_id',$job)->delete();
            }""",'delete_or_exclude')
once(oc,"'action'=>'OSL_DELETE_FIX91','before_json'=>json_encode((array)$old,JSON_INVALID_UTF8_SUBSTITUTE),",
"""'action'=>$estimateLinked?'OSL_EXCLUDE_ESTIMATE_AM146':'OSL_DELETE_DIRECT_AM146',
               'before_json'=>json_encode((array)$old,JSON_INVALID_UTF8_SUBSTITUTE),""",'audit_action')
once(oc, "'after_json'=>null,'reason'=>$data['reason'],'actor_id'=>$actor->id,",
"""'after_json'=>$estimateLinked ? json_encode((array)DB::table('job_card_labour')->find($line),JSON_INVALID_UTF8_SUBSTITUTE):null,
               'reason'=>$data['reason'],'actor_id'=>$actor->id,""",'audit_snapshot')
# Backend OSL purchase linking: exact line IDs, auto-link ONE safe name match,
# never duplicate a customer sale line or overwrite estimate-approved pricing.
once(pc, "            'lines.*.osl_master_id' => ['nullable','integer','min:1'],",
"""            'lines.*.osl_master_id' => ['nullable','integer','min:1'],
            'lines.*.labour_line_id' => ['nullable','integer','min:1'],""",'purchase_validate')
once(ps,"            $billingLine = $lineId ? DB::table('job_card_labour')->where('id', $lineId)->where('job_card_id', $jobId)->lockForUpdate()->first() : null;",
"""            // AM146_OSL_MASTER: link OSL Purchase to the same JC customer
            // OSL billing line, never auto-create a duplicate if a match exists.
            // Ambiguous existing matches require explicit selection.
            if(!$lineId){
                $matched=DB::table('job_card_labour')->where('job_card_id',$jobId)
                  ->whereRaw("UPPER(COALESCE(mechanic_name,''))='OSL'")
                  ->where('billing_included',1)
                  ->whereRaw('LOWER(TRIM(description))=?',[mb_strtolower(trim($desc))])
                  ->lockForUpdate()->get(['id']);
                if($matched->count()>1)
                    throw ValidationException::withMessages(['labour_line_id'=>'Multiple JC OSL billing lines match. Select exact JC OSL line before Purchase.']);
                if($matched->count()===1)$lineId=(int)$matched->first()->id;
            }
            if($lineId && DB::table('workshop_purchases')
                ->where('job_card_id',$jobId)->where('labour_line_id',$lineId)
                ->where('purchase_type','OSL')
                ->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'")
                ->whereNull('deleted_at')->lockForUpdate()->exists()){
                throw ValidationException::withMessages(['labour_line_id'=>'This JC OSL billing line already has an active supplier purchase. Open existing purchase to edit; do not create duplicate.']);
            }
            $billingLine = $lineId ? DB::table('job_card_labour')->where('id', $lineId)->where('job_card_id', $jobId)->lockForUpdate()->first() : null;""",'purchase_auto_link')
# Retain approved JC sale / price on linking, but not change it in supplier purchase save.
start="""                if ($billingLine) {
                    DB::table('job_card_labour')->where('id', $lineId)->where('job_card_id', $jobId)->update([
                        'rot_code' => $code, 'description' => $desc, 'standard_hours' => $std, 'labour_rate' => $saleRate,
                        'discount_percent' => 0, 'discount_amount' => 0, 'gst_percent' => $saleGst, 'total' => $customerAmount,
                        'mechanic_id' => 0, 'mechanic_name' => 'OSL',
                    ]);
                } else {"""
end="""                if ($billingLine) {
                    // Exact JC OSL line is existing customer sale, not cost inward:
                    // preserve price, discount, GST, description and approved estimate.
                    $customerAmount=(float)$billingLine->total;
                    $saleRate=(float)$billingLine->labour_rate;
                    $saleGst=(float)$billingLine->gst_percent;
                } else {"""
once(ps,start,end,'do_not_overwrite_customer_price')
# An existing JC billing line was not changed by its supplier purchase:
# do not inadvertently re-sync legacy stored totals during cost inward.
once(ps,"                $this->syncJobTotals($jobId);\n            }\n\n            $money = \\App\\Services\\OslPurchaseTax::quote",
        "                if(!$billingLine) $this->syncJobTotals($jobId);\n            }\n\n            $money = \\App\\Services\\OslPurchaseTax::quote",'preserve_linked_jc_totals')
# Presentation: remove approved estimate OSL voided from billing table, keep work history elsewhere.
once(jc,"          @forelse($labour as $line)",
"""          @forelse(collect($labour)->filter(static fn($line)=>
            !(strtoupper(trim((string)($line->mechanic_name??'')))==='OSL'
                && (int)($line->billing_included??1)===0)) as $line)""",'exclude_view')
# From OSL Edit/Add direct purchase preselect current JC.
s[jc]=s[jc].replace("route('erp.purchases.osl.create')","route('erp.purchases.osl.create',['job_card_id'=>$job->id])")
# Add reusable computed OSL pricing + inline master picker to dynamic FIX91 dialog.
markerAnchor='<script id="am93-osl-row-sort">'
ui=r'''
{{-- AM146_OSL_MASTER: one OSL master for JC and Purchase, live billing math. --}}
@php($am146Masters=\Illuminate\Support\Facades\DB::table('workshop_osl_master')->where('active',1)->orderBy('osl_name')->get(['id','osl_code','osl_name','gst_percent']))
<script id="am146-osl-calculations">
(function(){
 'use strict';
 const masterItems=@json($am146Masters);
 const createUrl=@json(route('erp.osl-masters.quick-create'));
 const money=n=>'₹'+(Number.isFinite(n)?n:0).toFixed(2);
 const n=(f,name)=>Number(f.elements.namedItem(name)?.value||0)||0;
 let selectedForm=null;
 function upgrade(){
  const dialog=document.querySelector('dialog.am91-osl-dialog[open]');
  const form=dialog?.querySelector('form.am91-osl-form');
  if(!form||form===selectedForm||form.querySelector('.am146-master-row'))return;
  selectedForm=form;
  const description=form.elements.namedItem('description');
  const quantity=form.elements.namedItem('quantity');
  const rate=form.elements.namedItem('rate');
  const disc=form.elements.namedItem('discount_percent');
  const gst=form.elements.namedItem('gst_percent');
  const deleteButton=dialog.querySelector('button.am91-delete');
  if(deleteButton && form.action.match(/\/\d+$/)){
    deleteButton.hidden=false;
    deleteButton.textContent='Delete / Remove OSL Billing';
    deleteButton.title='Admin reason mandatory. Approved estimate stays locked. Active supplier purchase blocks removal.';
  }
  const masterRow=document.createElement('div');masterRow.className='am146-master-row';
  const label=document.createElement('label');label.textContent='OSL Master Item';
  const select=document.createElement('select');select.name='osl_master_id';
  const none=document.createElement('option');none.value='';none.textContent='Manual / legacy item (select Master for new work)';select.appendChild(none);
  function addMaster(x){
    const opt=document.createElement('option');
    opt.value=x.id;opt.dataset.name=x.osl_name||x.name;opt.dataset.gst=x.gst_percent||0;
    opt.textContent=(x.osl_code||x.code)+' — '+(x.osl_name||x.name);
    select.appendChild(opt);return opt;
  }
  masterItems.forEach(addMaster);
  const current=(description.value||'').trim().toUpperCase();
  const found=Array.from(select.options).find(o=>o.dataset.name?.trim().toUpperCase()===current);
  if(found)select.value=found.value;
  label.appendChild(select);
  const add=document.createElement('button');add.type='button';
  add.textContent='+ Create OSL Master';add.className='am146-new-master';
  const feedback=document.createElement('span');feedback.className='am146-hint';
  masterRow.append(label,add,feedback);
  form.insertBefore(masterRow,form.firstChild.nextSibling);
  select.addEventListener('change',()=>{
    const option=select.options[select.selectedIndex];
    if(option?.dataset?.name){description.value=option.dataset.name;description.dispatchEvent(new Event('input',{bubbles:true}));}
  });
  add.addEventListener('click',async()=>{
    const name=(description.value||'').trim();
    if(name.length<3){feedback.textContent='Enter OSL Service name first';description.focus();return;}
    add.disabled=true;feedback.textContent='Saving Master...';
    try {
      const csrf=form.elements.namedItem('_token')?.value||'';
      const res=await fetch(createUrl,{method:'POST',credentials:'same-origin',headers:{
        'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf
      },body:JSON.stringify({name})});
      const body=await res.json();
      if(!res.ok||!body.ok)throw Error(body?.errors?.name?.[0]||body?.message||('HTTP '+res.status));
      let opt=Array.from(select.options).find(o=>String(o.value)===String(body.id));
      if(!opt){opt=addMaster(body);masterItems.push({id:body.id,osl_code:body.code,osl_name:body.name,gst_percent:0});}
      select.value=body.id;description.value=body.name;
      feedback.textContent='Master ready; selectable in Purchase too';
    }catch(ex){feedback.textContent=ex.message||'Master not saved';}
    finally{add.disabled=false;}
  });
  const moneyRow=document.createElement('div');moneyRow.className='am146-amounts';
  moneyRow.innerHTML='<div><span>OSL Price (Gross)</span><strong data-am146="gross"></strong></div>'+
    '<div><span>Discount %</span><strong data-am146="percent"></strong></div>'+
    '<label>Discount Amount ₹<input name="discount_amount" type="number" min="0" step="0.01" value="0"></label>'+
    '<div><span>Taxable Amount</span><strong data-am146="taxable"></strong></div>'+
    '<div><span>GST %</span><strong data-am146="gstpercent"></strong></div>'+
    '<div><span>GST Amount ₹</span><strong data-am146="gstamount"></strong></div>'+
    '<div class="am146-total"><span>FINAL AMOUNT ₹</span><strong data-am146="final"></strong></div>';
  const amount=moneyRow.querySelector('input[name="discount_amount"]');
  const action=form.querySelector('.am91-form-actions');
  form.insertBefore(moneyRow,action);
  const set=(field,v)=>moneyRow.querySelector('[data-am146="'+field+'"]').textContent=v;
  const round=v=>Math.round((v+Number.EPSILON)*100)/100;
  function recalc(source){
    const gross=round(Math.max(0,n(form,'quantity'))*Math.max(0,n(form,'rate')));
    if(source==='percentage')amount.value=round(gross*Math.min(100,Math.max(0,n(form,'discount_percent')))/100).toFixed(2);
    if(source==='amount' && gross>0)disc.value=round(100*Math.min(gross,Math.max(0,Number(amount.value)||0))/gross).toFixed(2);
    const off=Math.max(0,Number(amount.value)||0);
    if(off>gross){amount.setCustomValidity('Discount exceeds gross amount');}
    else amount.setCustomValidity('');
    const taxable=round(gross-off),ga=round(taxable*Math.max(0,n(form,'gst_percent'))/100);
    set('gross',money(gross));set('percent',(Number(disc.value)||0).toFixed(2)+'%');
    set('taxable',money(taxable));set('gstpercent',(Number(gst.value)||0).toFixed(2)+'%');
    set('gstamount',money(ga));set('final',money(round(taxable+ga)));
  }
  disc.addEventListener('input',()=>recalc('percentage'));
  amount.addEventListener('input',()=>recalc('amount'));
  [quantity,rate,gst].forEach(e=>e.addEventListener('input',()=>recalc(e===gst?'gst':'percentage')));
  recalc('percentage');
 }
 document.addEventListener('click',e=>{
    if(e.target.closest('button[data-am91-osl-edit],#am91-add-osl'))upgrade();
 });
 const observer=new MutationObserver(()=>{if(document.querySelector('dialog.am91-osl-dialog[open]'))upgrade();else selectedForm=null;});
 observer.observe(document.body,{childList:true});
})();
</script>
<style id="am146-osl-styles">
.am91-osl-dialog .am146-master-row{grid-column:1/-1;display:flex;gap:9px;flex-wrap:wrap;align-items:end;border:1px solid #d2e3ef;background:#f3f9ff;padding:9px;border-radius:8px;margin-bottom:8px}
.am91-osl-dialog .am146-master-row label{flex:1 1 200px;min-width:0}
.am91-osl-dialog .am146-master-row select{display:block;min-height:35px;width:100%;font-size:12px}
.am91-osl-dialog .am146-new-master{padding:9px 12px;background:#155a88;color:#fff;border:0;border-radius:6px;cursor:pointer;font-weight:750}
.am91-osl-dialog .am146-hint{width:100%;font-size:11px}
.am91-osl-dialog .am146-amounts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;grid-column:1/-1;background:#f5faff;border:1px solid #c7dae9;padding:10px;border-radius:9px}
.am91-osl-dialog .am146-amounts>div,.am91-osl-dialog .am146-amounts>label{display:flex;flex-direction:column;gap:6px;background:#fff;padding:8px;border-radius:6px;min-width:0;font-size:10px!important;color:#365c7c}
.am91-osl-dialog .am146-amounts strong{font-size:12px;color:#093866}
.am91-osl-dialog .am146-amounts .am146-total{background:#e2f5ec}
.am91-osl-dialog .am146-amounts .am146-total strong{color:#146344}
.am91-osl-dialog .am146-amounts input{width:100%;height:32px;padding:5px!important}
@media(max-width:650px){.am91-osl-dialog .am146-amounts{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
'''
once(jc,markerAnchor,ui+'\n'+markerAnchor,'ui_anchor')
# Purchase: return same Master inline creation and exact JC billing line selector.
# Preload only jobs present in form for predictable filter.
once(pc, "        $presetJobId = max(0,(int)$request->query('job_card_id',0));",
"""        // AM146_OSL_MASTER: exact JC billing choices for supplier cost linkage.
        $jobIds=collect($jobs)->pluck('id')->filter()->map(fn($id)=>(int)$id)->values()->all();
        $oslBillingOptions=DB::table('job_card_labour')->whereIn('job_card_id',$jobIds)
            ->whereRaw("UPPER(COALESCE(mechanic_name,''))='OSL'")
            ->where('billing_included',1)
            ->orderBy('id')->get(['id','job_card_id','description','standard_hours','labour_rate','gst_percent','total']);
        $presetJobId = max(0,(int)$request->query('job_card_id',0));""",'controller_options')
once(pc,"            'presetJobId'\n        ));","            'presetJobId',\n            'oslBillingOptions'\n        ));",'controller_compact')
pui=r'''
{{-- AM146_OSL_MASTER: shared OSL Master and explicit Job Card billing link. --}}
<style id="am146-purchase-styles">
#osl-form .am146-purchase-line-link{display:block;margin-top:7px;border:1px solid #b3d2e7;padding:5px;border-radius:6px;background:#f1f8ff}
#osl-form .am146-purchase-line-link label{display:block;font-size:10px;color:#315a79;font-weight:800}
#osl-form .am146-purchase-line-link select{height:32px;font-size:11px;max-width:100%}
#osl-form .am146-purchase-create{background:#145983;color:white;border:0;border-radius:6px;padding:8px 12px;cursor:pointer;margin-left:6px}
</style>
<script id="am146-purchase-link-script">
(function(){
 'use strict';
 const lines=@json($oslBillingOptions);
 const createUrl=@json(route('erp.osl-masters.quick-create'));
 const form=document.getElementById('osl-form');
 const body=document.getElementById('osl-lines');
 if(!form||!body)return;
 const toolbar=form.querySelector('.osl-toolbar');
 const btn=document.createElement('button');btn.type='button';btn.className='am146-purchase-create';btn.textContent='+ New OSL Master';
 toolbar?.appendChild(btn);
 const csrf=form.querySelector('input[name="_token"]')?.value||'';
 const existing=()=>Array.from(body.querySelectorAll('tr.osl-line'));
 function refresh(row){
   const jobId=Number(row.querySelector('.job-card')?.value||0);
   const select=row.querySelector('.am146-jc-link');
   if(!select)return;
   const old=select.value;
   select.replaceChildren();
   const empty=document.createElement('option');empty.value='';empty.textContent='New JC sale line (only if no matching OSL exists)';select.appendChild(empty);
   lines.filter(x=>Number(x.job_card_id)===jobId).forEach(x=>{
     const o=document.createElement('option');o.value=x.id;
     o.textContent='#'+x.id+' · '+x.description+' · ₹'+Number(x.labour_rate||0).toFixed(2)+' · GST '+x.gst_percent+'%';
     o.dataset.description=x.description;o.dataset.rate=x.labour_rate;o.dataset.gst=x.gst_percent;
     select.appendChild(o);
   });
   if(Array.from(select.options).some(o=>o.value===old))select.value=old;
   if(!select.value) {
     const d=row.querySelector('.description')?.value?.trim().toUpperCase();
     const matches=Array.from(select.options).filter(o=>o.value && o.dataset.description?.trim().toUpperCase()===d);
     if(matches.length===1)select.value=matches[0].value;
   }
   sync(row);
 }
 function sync(row){
   const select=row.querySelector('.am146-jc-link');
   const opt=select?.options[select.selectedIndex];
   if(!opt?.value)return;
   const d=row.querySelector('.description'),selling=row.querySelector('.customer-rate'),gst=row.querySelector('.customer-gst');
   if(d)d.value=opt.dataset.description||d.value;
   if(selling)selling.value=opt.dataset.rate||selling.value;
   if(gst)gst.value=opt.dataset.gst||gst.value;
   row.dispatchEvent(new Event('input',{bubbles:true}));
 }
 function decorate(row){
   if(row.querySelector('.am146-purchase-line-link'))return;
   const slot=row.querySelector('.job-filter-wrap');
   if(!slot)return;
   const holder=document.createElement('div');holder.className='am146-purchase-line-link';
   const label=document.createElement('label');label.textContent='Link Existing JC OSL Billing Line';
   const select=document.createElement('select');select.className='am146-jc-link';
   const job=row.querySelector('.job-card');
   const field=document.createElement('input');field.type='hidden';
   const name=row.querySelector('.osl-master')?.getAttribute('name')||'';
   field.name=name.replace('[osl_master_id]','[labour_line_id]');
   holder.append(label,select,field);slot.appendChild(holder);
   select.addEventListener('change',()=>{field.value=select.value;sync(row);});
   job?.addEventListener('change',()=>{refresh(row);field.value=select.value;});
   row.querySelector('.description')?.addEventListener('change',()=>{if(!select.value){refresh(row);field.value=select.value;}});
   row.querySelector('.osl-master')?.addEventListener('change',()=>{
      const opt=row.querySelector('.osl-master')?.selectedOptions[0];
      if(opt?.dataset.name)row.querySelector('.description').value=opt.dataset.name;
      if(!select.value){refresh(row);field.value=select.value;}
   });
   refresh(row);field.value=select.value;
 }
 const observer=new MutationObserver(()=>existing().forEach(decorate));
 observer.observe(body,{childList:true});
 existing().forEach(decorate);
 btn.addEventListener('click',async()=>{
   const row=existing().find(r=>r.contains(document.activeElement))||existing()[0];
   if(!row)return;
   const desc=row.querySelector('.description');
   const name=(desc?.value||'').trim()||prompt('New OSL Master name (e.g. SILENCER WELDING):')||'';
   if(name.trim().length<3){alert('Enter OSL Master name (minimum 3 characters).');return;}
   btn.disabled=true;
   try{
     const res=await fetch(createUrl,{method:'POST',credentials:'same-origin',headers:{
        'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf
     },body:JSON.stringify({name})});
     const data=await res.json();
     if(!res.ok||!data.ok)throw Error(data?.errors?.name?.[0]||data?.message||('HTTP '+res.status));
     existing().forEach(r=>{
        const master=r.querySelector('.osl-master');
        if(!Array.from(master.options).some(o=>String(o.value)===String(data.id))){
           const opt=document.createElement('option');opt.value=data.id;opt.dataset.name=data.name;opt.textContent=data.code+' — '+data.name;master.appendChild(opt);
        }
     });
     row.querySelector('.osl-master').value=data.id;desc.value=data.name;
     refresh(row);row.querySelector('.am146-jc-link').dispatchEvent(new Event('change'));
     alert('OSL Master created and selected: '+data.name);
   }catch(ex){alert('Master not saved: '+(ex.message||ex));}
   finally{btn.disabled=false;}
 });
})();
</script>
'''
# The end of purchase Blade is exactly one bottom inline JS close+section close.
once(pv,"</script>\n@endsection","</script>\n"+pui+"\n@endsection",'purchase_ui')
# Validate generated code paths and prevent accidental changes to other modules.
if not all(tag in s[jc] for tag in ['AM146_OSL_MASTER','am146-osl-calculations','discount_amount']):
 raise SystemExit('AM146_JC_PATCH_INCOMPLETE')
if 'AM146_OSL_MASTER' not in s[pv] or 'labour_line_id' not in s[pc] or 'OSL_EXCLUDE_ESTIMATE_AM146' not in s[oc]:
 raise SystemExit('AM146_BACKEND_PATCH_INCOMPLETE')
preview={n:txt.encode('utf8','surrogateescape') for n,txt in s.items()}
preview['app/Http/Controllers/Web/AdminOslQuickMasterController.php']=controllerSource.read_bytes()
print('AM146_PATCH_FILES='+','.join(preview.keys()))
for n in preview:
 if n in original:print('AM146_CHANGE='+n+' '+hashlib.sha256(original[n]).hexdigest()+' => '+hashlib.sha256(preview[n]).hexdigest())
if mode=='check':
 output=os.environ.get('AM146_PREVIEW_DIR','')
 if output:
  out=Path(output);out.mkdir(parents=True,exist_ok=True)
  for n,b in preview.items():
   p=out/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
 print('AM146_PREFLIGHT_PASS_NO_WRITES')
 sys.exit(0)
# Atomic rollback sources on error. Backups outside public_html with checksums.
if any(paths[n].read_bytes()!=original[n] for n in names):raise SystemExit('AM146_CONCURRENT_EDIT')
backups=root/'storage/app'/('am146_osl_backups_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
for n,b in original.items():
 p=backups/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
 if p.read_bytes()!=b:raise SystemExit('AM146_BACKUP_FAIL_'+n)
dest={n:root/n for n in preview}
installed=[]
try:
 for n,b in preview.items():
  p=dest[n]
  if p.exists() and n not in original:raise SystemExit('AM146_CONTROLLER_ALREADY_EXISTS')
  fd,tmp=tempfile.mkstemp(prefix='.am146_',dir=p.parent)
  with os.fdopen(fd,'wb') as h:h.write(b)
  os.chmod(tmp,p.stat().st_mode & 0o777 if p.exists() else 0o644)
  if n in original and p.read_bytes()!=original[n]:raise SystemExit('AM146_CONCURRENT_EDIT_'+n)
  os.replace(tmp,p);installed.append(n)
 print('AM146_APPLIED_BACKUP='+str(backups))
 print('AM146_STAGING_SOURCES_APPLIED_NO_DATABASE_MIGRATIONS')
except Exception:
 for n in reversed(installed):
  p=dest[n]
  if n in original:p.write_bytes(original[n])
  elif p.exists():p.unlink()
 raise
