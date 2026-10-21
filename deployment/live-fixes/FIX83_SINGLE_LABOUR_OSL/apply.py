#!/usr/bin/env python3
"""FIX83: one Labour Item Selection workspace with inline extra ROT and OSL.
Presentation + read-only JC linked OSL data only. Original estimate release,
direct ROT add, assignment, staff timing and billing endpoints unchanged.
"""
from pathlib import Path
import sys,os,hashlib,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
files={
 'view':root/'resources/views/job-cards/show.blade.php',
 'controller':root/'app/Http/Controllers/Web/AdminJobCardPageController.php',
}
expected={
 'view':'cc418f920608aac9e63064a6be7ed7033772648f482efb6ec277c300964f0c34',
 'controller':'d6d7cab124151ad1695d1a08317cfd6e9d8ab08fb7dd12404df99793489008c9',
}
raw={k:p.read_bytes() for k,p in files.items()}
data={k:v.decode('utf8',errors='surrogateescape') for k,v in raw.items()}
marker='FIX83_SINGLE_LABOUR_OSL_WORKSPACE'
if marker in data['view'] or marker in data['controller']:
 if all(marker in x for x in data.values()):
  print('FIX83_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX83_PARTIAL_PATCH_ABORT')
for k,v in raw.items():
 h=hashlib.sha256(v).hexdigest()
 print('FIX83_SOURCE_SHA',k,h)
 if h!=expected[k]:raise SystemExit('FIX83_CONCURRENT_EDIT_ABORT_'+k)
def one(s,old,new,name):
 n=s.count(old)
 print('FIX83_ANCHOR',name,n)
 if n!=1:raise SystemExit('FIX83_ANCHOR_FAILED_'+name)
 return s.replace(old,new,1)
v=data['view']
startToken='{{-- FIX82_JC_SINGLE_ENTRY_PANEL: ROT creation lives only inside Labour/ROT. --}}'
endToken='  <div class="section-head"><div><h2 class="section-title">Labour / ROT — Entry, Mechanic, Work Control &amp; Customer Billing</h2>'
assert v.count(startToken)==1 and v.count(endToken)==1
a=v.index(startToken);b=v.index(endToken,a)
add=v[a:b]
assert 'route(\'erp.job-cards.labour.direct\',$job->id)' in add
assert 'name="labour_master_id"' in add
assert add.count('<section class="am82-entry-block"')==1
assert add.count('</section>')==1
add=add.replace(startToken,'{{-- FIX83_SINGLE_LABOUR_OSL_WORKSPACE: Direct extra ROT form inside single Labour workspace. --}}',1)
add=add.replace('<section class="am82-entry-block" aria-label="Add ROT to this Job Card">',
                '<div class="am83-inline-add" aria-label="Add extra ROT to this Job Card">',1)
add=add.replace('</section>','</div>',1)
add=add.replace('<h3>+ Add New Labour / ROT</h3>',
 '''<h3>+ Add Additional ROT / Labour</h3>''',1)
add=add.replace('ROT Master selection first, then assign mechanic on the new row. Operational controls and customer billing are together below.',
                'Approved Estimate se transferred ROTs niche dikhenge. Extra ROT ke liye yahi search use karein; Add ke baad isi row mein mechanic assign aur work Start/Pause/Complete karein.',1)
v=v[:a]+v[b:]
header='''  <div class="section-head"><div><h2 class="section-title">Labour Item Selection</h2><p class="muted">ONE WORKSPACE · Approved Estimate → SEND TO JOB CARD → Planned ROT · Additional ROT as required in WIP · Admin + Staff share original session records.</p></div></div>'''
v=one(v,
 '''  <div class="section-head"><div><h2 class="section-title">Labour / ROT — Entry, Mechanic, Work Control &amp; Customer Billing</h2><p class="muted">Same ROT for Admin and Staff: select, assign, Start/Pause/Resume/Complete. Mechanic, actual time and standard hours are operational fields; billing is edited independently.</p></div></div>''',
 header,'single_header')
oslFilter=r'''  @php
    // FIX83_SINGLE_LABOUR_OSL_WORKSPACE — OSL is outside labour and must never
    // become a mechanic ROT session or appear twice in billed ROT totals.
    $am83IsOsl=fn($l)=>strtoupper(trim((string)($l->mechanic_name??'')))==='OSL'
       || strtoupper(trim((string)($l->rot_code??'')))==='OSL';
    $am83RotLabour=$labour->reject($am83IsOsl)->values();
    $am83OslLabour=$labour->filter($am83IsOsl)->values();
    $am83FromEstimate=$am83RotLabour->filter(fn($l)=>!empty($l->source_estimate_item_id))->count();
    $am83AddedDirect=$am83RotLabour->count()-$am83FromEstimate;
  @endphp
'''
v=one(v,
 '  <div class="am82-rot-inline" id="am65-rot-controls">',
 oslFilter+'  <div class="am82-rot-inline am83-workspace" id="am65-rot-controls">',
 'unified_line_classification')
v=one(v,
 '    <div class="am82-rot-title">ROT Selection · Mechanic · Live Work Control · Customer Billing</div>',
 '''    <div class="am83-workflow-strip">
      <strong>ROT / Labour Search → Mechanic → Start / Pause / Resume / Complete → Billing</strong>
      <span>Estimate release: {{ $am83FromEstimate }} ROT · Additional Direct ROT: {{ $am83AddedDirect }}</span>
    </div>
'''+add+'''
    <div class="am83-grid-header"><span>LABOUR / WORK + SOURCE</span><span>MECHANIC · ROT STATUS · ACTUAL TIME</span><span>STD HRS (LOCKED)</span><span>RATE / DISC / GST / CUSTOMER TOTAL</span></div>''',
 'inline_add_no_separate_box')
v=one(v,'   @forelse($labour as $line)','   @forelse($am83RotLabour as $line)','regular_rot_only')
v=one(v,
 '<div><div class="rot-code">{{ $line->rot_code ?: \'LABOUR\' }}</div><h3>{{ $line->description }}</h3>',
 '''<div><div class="rot-code">{{ $line->rot_code ?: 'LABOUR' }}
          <span class="am83-source {{ $line->source_estimate_item_id ? 'estimate' : 'extra' }}">{{ $line->source_estimate_item_id ? 'FROM APPROVED ESTIMATE' : 'ADDITIONAL / DIRECT' }}</span>
        </div><h3>{{ $line->description }}</h3>''',
 'source_indicator_per_rot')
osl=r'''
    {{-- FIX83 OSL within SAME Labour workspace; supplier purchase NOT added again to JC billing. --}}
    <div class="am83-osl" id="jc-osl">
      <div class="am83-osl-head">
        <div><strong>OSL Jobs / Outside Services</strong>
          <small>JC linked OSL purchases and OSL billing lines; supplier cost and customer charge are separate.</small></div>
        <div class="am83-osl-actions">
          @if(!in_array(strtolower(trim((string)$job->status)),['closed','invoiced','cancelled'],true) && empty($job->invoice_id))
            <a class="btn small primary" href="{{ route('erp.purchases.osl.create') }}">+ New OSL Purchase</a>
          @endif
          <a class="btn small ghost" href="{{ route('erp.fix48.osl-index') }}">OSL Register</a>
        </div>
      </div>
      <div class="am83-osl-scroll">
       <table class="data-table am83-osl-table">
        <thead><tr><th>OSL Service / Source</th><th>Vendor / Purchase</th><th>Supplier Total</th><th>Customer Charge</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @forelse(($am83OslPurchases ?? collect()) as $p)
          <tr>
            <td><b>{{ $p->description ?: 'Outside Service' }}</b><small>Purchase linked by Job Card {{ $job->job_no }}</small></td>
            <td>{{ $p->supplier_name ?: 'Vendor not named' }}<small>{{ $p->purchase_no ?: ('Purchase #'.$p->id) }}</small></td>
            <td class="num">₹{{ number_format((float)($p->total_amount??0),2) }}</td>
            <td class="num">₹{{ number_format((float)($p->customer_amount??0),2) }}<small>Customer GST: {{ number_format((float)($p->customer_gst_percent??0),2) }}%</small></td>
            <td>{{ $p->customer_billing_status ?: ($p->billing_line_created ? 'Imported in JC' : 'Pending JC billing') }}</td>
            <td><a class="btn small" href="{{ route('erp.fix48.osl-edit',$p->id) }}">View / Edit</a></td>
          </tr>
          @empty
          <tr><td colspan="6" class="empty">No linked OSL supplier purchase yet. Enter an OSL Purchase with this Job Card number.</td></tr>
          @endforelse
        </tbody>
       </table>
      </div>
      @if($am83OslLabour->isNotEmpty())
        <div class="am83-osl-jc-lines">
         <strong>OSL billed / planned on this Job Card ({{ $am83OslLabour->count() }})</strong>
         @foreach($am83OslLabour as $l)
          <div><span>{{ $l->description ?: 'OSL Service' }} · {{ $l->source_estimate_item_id ? 'FROM ESTIMATE' : 'DIRECT / PURCHASE' }}</span>
           <span>Taxable ₹{{ number_format((float)($l->total??0),2) }} · GST {{ number_format((float)($l->gst_percent??0),2) }}% · <b>₹{{ number_format((float)($l->total??0)+round((float)($l->total??0)*(float)($l->gst_percent??0)/100,2),2) }}</b></span></div>
         @endforeach
        </div>
      @endif
      <p class="am83-osl-note">Supplier Purchase is a cost/reference. Customer OSL amount is billed only through its authorised Job Card billing line—never automatically added a second time. Select the same JC when entering OSL Purchase.</p>
    </div>
'''
end='''  @empty<div class="empty">No Labour/ROT lines on this Job Card.</div>@endforelse
  </div>
  </div>
</div>'''
assert v.count(end)==1
v=one(v,end,'''  @empty<div class="empty">No Labour/ROT lines on this Job Card.</div>@endforelse
  </div>
'''+osl+'''  </div>
</div>''','osl_beneath_rot_inside_workspace')
css=r'''
<style id="FIX83_SINGLE_LABOUR_OSL_WORKSPACE">
/* Single green workshop ROT section, followed by its amber OSL subsection. */
.jc-labour-block{border:1px solid #b8e7d2!important;border-top:4px solid #188960!important;background:#fcfffe!important}
.jc-labour-block>.section-head{background:#f0fbf6!important;border-bottom:1px solid #bbe6d2!important}
.jc-labour-block>.section-head .section-title{color:#096846!important;font-size:15px!important}
.am83-workspace{margin:0!important;padding:10px 12px 12px!important}
.am83-workflow-strip{display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;
  background:#f3fbf7;border:1px solid #c6e9da;border-radius:7px;padding:10px 12px;color:#075c3f;font-size:11px}
.am83-workflow-strip strong{font-size:12px;color:#0a6547}
.am83-workflow-strip span{background:#fff;border:1px solid #d2e6de;padding:5px 8px;border-radius:8px;color:#355c50}
.jc-labour-block .am83-inline-add{border:0!important;border-bottom:1px solid #d7e9e2!important;
 background:transparent!important;margin:0!important;padding:13px 8px 16px!important;border-radius:0!important}
.am83-inline-add h3{font-size:12px;color:#0f6448;margin:0 0 4px}
.am83-inline-add p{font-size:11px;margin:0 0 9px}
.am83-inline-add .jc-entry-form{display:grid;grid-template-columns:2fr 2fr 1fr 1fr 1fr 1fr;align-items:end;gap:8px}
.am83-inline-add .jc-entry-form>label{min-width:0;font-size:10px;font-weight:800;color:#2d4d5e}
.am83-inline-add .jc-entry-form>label.wide{grid-column:span 2}
.am83-inline-add .jc-entry-form input,.am83-inline-add .jc-entry-form select{width:100%;min-height:35px}
.am83-inline-add .jc-entry-form>.actions{grid-column:1/-1;justify-content:flex-end}
.am83-grid-header{display:grid;grid-template-columns:minmax(190px,2fr) minmax(210px,2fr) minmax(95px,0.75fr) minmax(300px,3fr);
 gap:8px;background:#e6f5ed;border:1px solid #c9e7d9;padding:9px 12px;
 border-radius:7px;font-size:10px;font-weight:900;color:#145d45;margin:12px 0 6px}
.am83-workspace .rot-list{padding:0}
.am83-workspace .rot-card{border:1px solid #cbdfe2!important;border-left:4px solid #31956f!important;
 border-radius:9px!important;margin:8px 0 12px!important;padding:12px!important;background:white!important}
.am83-workspace .rot-card .rot-main{margin-bottom:7px}
.am83-source{display:inline-block;font-size:9px;font-weight:850;letter-spacing:.02em;
 border-radius:5px;padding:3px 6px;margin-left:5px}
.am83-source.estimate{background:#dff4e8;color:#09683f}
.am83-source.extra{background:#e9f0ff;color:#2050a4}
.am83-osl{border:1px solid #ffc67d;background:#fffdf8;border-radius:9px;margin:15px 0 2px;overflow:hidden}
.am83-osl-head{display:flex;align-items:center;justify-content:space-between;gap:10px;
 padding:12px 14px;background:#fff1d5;border-bottom:1px solid #f9ce8d;flex-wrap:wrap}
.am83-osl-head strong{color:#9a5906;display:block;font-size:13px}
.am83-osl-head small{display:block;color:#916b38;font-size:10px;margin-top:4px}
.am83-osl-actions{display:flex;gap:7px;flex-wrap:wrap}
.am83-osl-scroll{overflow-x:auto;padding:8px}
.am83-osl-table{width:100%;min-width:820px;border-collapse:collapse;font-size:11px}
.am83-osl-table th{background:#fff3df;color:#8c5d20;font-size:10px}
.am83-osl-table td{padding:9px}
.am83-osl-table small{display:block;color:#8c7552;margin-top:4px}
.am83-osl-jc-lines{margin:10px 12px;padding:10px;background:white;border:1px solid #f5dcb6;border-radius:7px;font-size:11px}
.am83-osl-jc-lines>strong{color:#965808}
.am83-osl-jc-lines>div{display:flex;justify-content:space-between;gap:10px;padding:7px 0;
 border-top:1px solid #f6eee1;flex-wrap:wrap}
.am83-osl-note{margin:8px 13px 13px;font-size:10px;color:#82633b;line-height:1.5}
@media(max-width:1050px){
 .am83-grid-header{display:none}
 .am83-inline-add .jc-entry-form{grid-template-columns:repeat(3,minmax(120px,1fr))}
}
@media(max-width:600px){
 .am83-inline-add .jc-entry-form{grid-template-columns:repeat(2,minmax(110px,1fr))}
 .am83-inline-add .jc-entry-form>label.wide{grid-column:span 2}
}
</style>
'''
v=one(v, '</style>\n<script>\n(function(){\n  \'use strict\';\n  // FIX82B_DOM_READY_SEARCH_GST',
 '</style>\n'+css+'<script>\n(function(){\n  \'use strict\';\n  // FIX82B_DOM_READY_SEARCH_GST',
 'inline_compact_style')
# Controller retrieves only purchase metadata belonging to the JC, no write.
c=data['controller']
ctrlOld="""        $data['labourMaster']=Schema::hasTable('labour_master')
            ? DB::table('labour_master')->orderBy('rot_code')->limit(5000)->get()
            : collect();
        return view('job-cards.show',$data);"""
ctrlNew="""        $data['labourMaster']=Schema::hasTable('labour_master')
            ? DB::table('labour_master')->orderBy('rot_code')->limit(5000)->get()
            : collect();
        // FIX83_SINGLE_LABOUR_OSL_WORKSPACE: read-only JC-linked OSL cost register.
        $data['am83OslPurchases']=Schema::hasTable('workshop_purchases')
            ? DB::table('workshop_purchases')->where('job_card_id',$job)
                ->whereRaw("UPPER(COALESCE(purchase_type,''))='OSL'")
                ->whereNull('deleted_at')->orderByDesc('id')->limit(200)->get()
            : collect();
        return view('job-cards.show',$data);"""
c=one(c,ctrlOld,ctrlNew,'osl_purchases_read_only')
for token in ["route('erp.job-cards.labour.direct',$job->id)",
              "route('erp.job-cards.labour.assign',[$job->id,$line->id])",
              "route('erp.rot.start',$rot->id)","route('erp.rot.pause',$rot->id)",
              "route('erp.rot.resume',$rot->id)","route('erp.rot.complete',$rot->id)",
              "route('erp.fix48.job-line',[$job->id,'labour',$line->id])",
              "route('erp.purchases.osl.create')",
              "route('erp.fix48.osl-edit',$p->id)",
              "route('erp.estimates.send-to-job',$latestEstimate->id)"]:
 if token not in v:raise SystemExit('FIX83_EXISTING_WORKFLOW_LOST_'+token)
if v.count('name="labour_master_id"')<1 or v.count('id="am82-rot-search"')!=1:
 raise SystemExit('FIX83_EXTRA_ROT_SEARCH_MISSING')
for token in ['<section class="am82-entry-block" aria-label="Add ROT','Labour / ROT — Entry, Mechanic, Work Control']:
 if token in v:raise SystemExit('FIX83_DUPLICATE_PANEL_REMAINS')
if v.count('FIX83_SINGLE_LABOUR_OSL_WORKSPACE')<3 or c.count('FIX83_SINGLE_LABOUR_OSL_WORKSPACE')!=1:
 raise SystemExit('FIX83_INVALID_MARKER')
if mode=='check':
 print('FIX83_SINGLE_LABOUR_OSL_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
def write(p,t):
 fd,temp=tempfile.mkstemp(prefix='.fix83_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(t)
  os.chmod(temp,p.stat().st_mode&0o777);os.replace(temp,p)
 finally:
  if os.path.exists(temp):os.unlink(temp)
write(files['view'],v);write(files['controller'],c)
print('FIX83_ONE_ROT_WORKSPACE_PLUS_OSL_DEPLOYED_NO_TRANSACTION_OR_TAX_CHANGES')
