#!/usr/bin/env python3
"""FIX76 exact-source Labour Master SAC/HSN field: Create, Edit, search and list.
No other services, JC, invoice or ledger source is altered.
"""
from pathlib import Path
import sys,hashlib,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):raise SystemExit('Usage: patch.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assert (root/'artisan').is_file()
view=root/'resources/views/admin/native/labour.blade.php'
controller=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
expected={'view':'9b9bb2e1baa3e4a03336c1322440bacf150d1c4182ada6bcdcc910f791438cc8',
          'controller':'a5f44101bf76dee1140ec202cc5c4584cf42cc74980fccc335d90b59db5f946c'}
raw={'view':view.read_bytes(),'controller':controller.read_bytes()}
sources={k:b.decode('utf8',errors='surrogateescape') for k,b in raw.items()}
marker='FIX76_ROT_SAC_EDITABLE_MASTER'
if marker in sources['view'] or marker in sources['controller']:
 if all(marker in v for v in sources.values()):
  print('FIX76_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX76_PARTIAL_PATCH_ABORT')
for k,b in raw.items():
 h=hashlib.sha256(b).hexdigest();print('FIX76_SOURCE_SHA',k,h)
 if h!=expected[k]:raise SystemExit('FIX76_SOURCE_CHANGED_'+k)
def one(s,old,new,label):
 n=s.count(old);print('FIX76_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX76_AMBIGUOUS_'+label)
 return s.replace(old,new,1)
v=sources['view']
v=one(v,
 '<p>Add, edit and delete ROT master entries with standard time, labour rate and GST.</p>',
 '<p>Add, edit and delete ROT master entries with standard time, labour rate, SAC/HSN and GST.</p>',
 'helper_header')
v=one(v,
 '<label>ROT Code<input name="rot_code" placeholder="Blank = auto"></label>',
 '''<label>ROT Code<input name="rot_code" placeholder="Blank = auto"></label>
    {{-- FIX76_ROT_SAC_EDITABLE_MASTER --}}
    <label>SAC / HSN *<input name="sac_code" value="{{ old('sac_code','998714') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" title="Six-digit service SAC, e.g. 998714" required autocomplete="off"></label>''',
 'create_form')
v=one(v,
 '<label>ROT Code<input name="rot_code" value="{{ $r->rot_code }}" required></label>',
 '''<label>ROT Code<input name="rot_code" value="{{ $r->rot_code }}" required></label>
    <label>SAC / HSN *<input name="sac_code" value="{{ old('sac_code',$r->sac_code??'998714') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" title="Six-digit service SAC, e.g. 998714" required autocomplete="off"></label>''',
 'edit_form')
v=one(v,
 '<th>ROT Code</th><th>Description</th><th>Category</th>',
 '<th>ROT Code</th><th>Description</th><th>SAC / HSN</th><th>Category</th>',
 'master_heading')
v=one(v,
 '<td>{{ $r->rot_description }}</td><td>{{ $r->category ?:',
 '''<td>{{ $r->rot_description }}</td><td><strong>{{ $r->sac_code ?: 'NOT SET' }}</strong></td><td>{{ $r->category ?:''',
 'table_sac_value')
v=one(v,
 '<td colspan="8">','<td colspan="9">','inline_edit_colspan')
v=one(v,
 '@empty<tr><td colspan="8"','@empty<tr><td colspan="9"','empty_colspan')
v=one(v,
 'placeholder="🔎 Search ROT code / description / category"',
 'placeholder="🔎 Search ROT code / description / category / SAC"',
 'search_placeholder')
v=one(v,
 '<script>function amToggle(id)',
 '''<div class="muted" style="margin:7px 0 12px;font-size:11px">
  SAC 998714 = Motor vehicle maintenance &amp; repair, including routine vehicle wash, body work and associated diagnosis.
  For separately classified non-repair services, edit the appropriate six-digit SAC. GST % remains a separate setting.
</div>
<script>function amToggle(id)''',
 'classification_guidance')
ctrl=sources['controller']
needle="->orWhere('category','like',$like);"
replacement="->orWhere('category','like',$like)->orWhere('sac_code','like',$like);"
ctrl=one(ctrl,needle,replacement,'master_sac_search')
ctrl=one(ctrl,
 "'gst_percent'=>['required','numeric','between:0,100']]);",
 "'gst_percent'=>['required','numeric','between:0,100'],'sac_code'=>['required','regex:/^[0-9]{6}$/']]);",
 'create_sac_validation')
ctrl=one(ctrl,
 "'gst_percent'=>$data['gst_percent'],'active'=>'Yes'",
 "'gst_percent'=>$data['gst_percent'],'sac_code'=>$data['sac_code'],'active'=>'Yes'",
 'create_sac_write')
ctrl=one(ctrl,
 "'gst_percent'=>['required','numeric','between:0,100'],'active'=>['required','in:Yes,No']]);",
 "'gst_percent'=>['required','numeric','between:0,100'],'sac_code'=>['required','regex:/^[0-9]{6}$/'],'active'=>['required','in:Yes,No']]);",
 'update_sac_validation')
ctrl=one(ctrl,
 '    public function createLabour(Request $request): RedirectResponse',
 '    // FIX76_ROT_SAC_EDITABLE_MASTER: a service SAC is required when saving any ROT.\n    public function createLabour(Request $request): RedirectResponse',
 'controller_marker')
for source,k in [(v,'view'),(ctrl,'controller')]:
 if source.count(marker)!=1:raise SystemExit('FIX76_MARKER_MISSING_'+k)
for tok in ["route('erp.masters.labour.create')","route('erp.masters.labour.update',$r->id)",
            'name="sac_code"','SAC / HSN']:
 if tok not in v:raise SystemExit('FIX76_VIEW_CONTRACT_MISSING_'+tok)
for tok in ['sac_code','DB::transaction(function()use($labour,$existing,$data)']:
 if tok not in ctrl:raise SystemExit('FIX76_CONTROLLER_CONTRACT_MISSING_'+tok)
if mode=='check':
 print('FIX76_PREFLIGHT_PASS_MASTER_ONLY');sys.exit(0)
def write(p,s):
 fd,temp=tempfile.mkstemp(prefix='.fix76_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(temp,p.stat().st_mode&0o777)
  os.replace(temp,p)
 finally:
  if os.path.exists(temp):os.unlink(temp)
write(view,v)
write(controller,ctrl)
print('FIX76_SAC_MASTER_UI_AND_CONTROLLER_DEPLOYED')
