#!/usr/bin/env python3
"""FIX70: tax mode for EACH OSL supplier line: EXTRA / INCLUDED / NONE.
No retroactive recalculation; prior OSL rows read as EXTRA if gst_mode is null.
"""
from pathlib import Path
import sys,hashlib,re,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):raise SystemExit('Usage: patch.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();pkg=Path(__file__).resolve().parent
assert (root/'artisan').is_file()
files={
 'form':root/'resources/views/purchases/osl-create.blade.php',
 'controller':root/'app/Http/Controllers/Web/AdminPurchasePageController.php',
 'service':root/'app/Services/PurchaseService.php',
 'edit':root/'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
}
hashes={
 'form':'108fb4e12e916abb39c1c51c39b803d9d7c9f3051f05283e3cf871bf4fa8fb97',
 'controller':'1045cb8231b6ec319294c95aadd2790ac011de87cf4646d233ef19de173fbc04',
 'service':'e8c4e4f571c27f3f949e8fb42f427d569508d7f2a1569496027a6651bb0db415',
 'edit':'c5af09b79c4a06a69ef0b151c3c0e364bc3f8de535fb59647e039535c2747a24',
}
marker='FIX70_OSL_GST_BASIS'
original={};src={}
for key,p in files.items():
 data=p.read_bytes()
 print('FIX70_SOURCE',key,hashlib.sha256(data).hexdigest())
 original[key]=data
 src[key]=data.decode('utf8',errors='surrogateescape')
 if marker in src[key]:
  if all(marker in x for x in src.values()):print('FIX70_ALREADY_APPLIED');sys.exit(0)
  raise SystemExit('FIX70_PARTIAL_PATCH_ABORT')
 if hashlib.sha256(data).hexdigest()!=hashes[key]:raise SystemExit('FIX70_UNEXPECTED_SOURCE_'+key)
def one(s,a,b,name):
 count=s.count(a);print('FIX70_ANCHOR',name,count)
 if count!=1:raise SystemExit('FIX70_ANCHOR_MISMATCH_'+name)
 return s.replace(a,b,1)
form=src['form']
form=one(form,'<th>Disc %</th><th>GST %</th>',
         '<th>Disc %</th><th>Supplier GST Basis</th><th>GST %</th>','form_tax_header')
form=one(form,'  <td><input type="number" min="0" max="100" step="0.01" name="lines[__INDEX__][gst_percent]" class="gst" value="0"></td>',
         '''  <td><select name="lines[__INDEX__][gst_mode]" class="gst-mode" aria-label="Supplier GST Mode" required>
      <option value="EXTRA">GST Extra (+)</option>
      <option value="INCLUDED">GST Included (in Rate)</option>
      <option value="NONE">GST Not Applicable</option>
    </select></td>
  <td><input type="number" min="0" max="100" step="0.01" name="lines[__INDEX__][gst_percent]" class="gst" value="0"></td>''','form_mode_control')
form=one(form,'.osl-form .line-table{min-width:1420px}', '.osl-form .line-table{min-width:1660px}', 'form_width')
form=one(form,"  const money = v => '₹' + (Number.isFinite(v) ? v : 0).toFixed(2);",
         "  // FIX70_OSL_GST_BASIS: backend remains authoritative; this is a live preview.\n  const money = v => '₹' + (Number.isFinite(v) ? v : 0).toFixed(2);", 'form_marker')
oldcalc='''       const g = qty * rate;
       const d = g * disc / 100;
       const t = g - d;
       const ga = t * gst / 100;
       const total = t + ga;'''
newcalc='''       const basis=row.querySelector('.gst-mode')?.value||'EXTRA';
       const gstInput=row.querySelector('.gst');
       if(gstInput){
         if(basis==='NONE'){gstInput.value='0';gstInput.readOnly=true;}
         else gstInput.readOnly=false;
       }
       const actualGst=basis==='NONE'?0:Math.max(0,num(row,'.gst'));
       const r2=x=>Math.round((x+Number.EPSILON)*100)/100;
       const g = r2(qty * rate);
       const d = r2(g * disc / 100);
       const net = r2(g - d);
       const t = basis==='INCLUDED' && actualGst>0 ? r2(net/(1+actualGst/100)) : net;
       const ga = basis==='INCLUDED' ? r2(net-t) : (basis==='EXTRA'?r2(t*actualGst/100):0);
       const total = r2(t+ga);'''
pattern=re.compile(r'(?m)^[ \t]*const g = qty \* rate;\n[ \t]*const d = g \* disc / 100;\n[ \t]*const t = g - d;\n[ \t]*const ga = t \* gst / 100;\n[ \t]*const total = t \+ ga;')
form,count=pattern.subn(lambda m:newcalc,form)
print('FIX70_ANCHOR preview_calculator',count)
if count!=1:raise SystemExit('FIX70_ANCHOR_MISMATCH_preview_calculator')
form=one(form,"     set('.gst', data.gst_percent ?? 0);", "     set('.gst', data.gst_percent ?? 0);", 'noopcheck') if False else form
form=one(form,"set('.gst', data.gst_percent ?? 0); set('.customer-rate'",
         "set('.gst-mode', data.gst_mode ?? 'EXTRA'); set('.gst', data.gst_percent ?? 0); set('.customer-rate'",'old_lines_mode')
form=one(form,
"  <th>#</th><th>Job Card / Vehicle *</th>",
"  <th>#</th><th>Job Card / Vehicle *</th>",'noop') if False else form
form=one(form,"</div>\n<div class=\"osl-toolbar\">",
'''</div>
<div class="osl-help" style="border-left:4px solid #178356">
<strong>Supplier GST Mode, per line:</strong> <b>Extra</b> = GST added to taxable; <b>Included</b> = entered Vendor Rate already includes GST; <b>Not Applicable</b> = 0 GST.
Customer Rate &amp; Customer GST are separate JC billing settings. Server will recalculate before save.
</div>
<div class="osl-toolbar">''','tax_helper')
service=src['service']
head="    public function saveOsl(array $data, string $actor): array"
tail="    public function delete(int $id, string $reason, string $actor): array"
if service.count(head)!=1 or service.count(tail)!=1:raise SystemExit('FIX70_OSL_METHOD_SCOPE_MISMATCH')
a=service.index(head);b=service.index(tail,a);segment=service[a:b]
segment=one(segment,
"            $gst = max(0, (float)($data['gst_percent'] ?? 0));",
'''            // FIX70_OSL_GST_BASIS — preserve vendor rate interpretation.
            $gst = max(0, (float)($data['gst_percent'] ?? 0));
            $gstMode=strtoupper(trim((string)($data['gst_mode'] ?? 'EXTRA')));
            if(!in_array($gstMode,['EXTRA','INCLUDED','NONE'],true)){
                throw ValidationException::withMessages(['gst_mode'=>'Invalid supplier GST mode.']);
            }''','service_mode')
segment=one(segment,"            $money = $this->calculate($qty, $cost, $discount, $gst);",
             "            $money = \\App\\Services\\OslPurchaseTax::quote($qty, $cost, $discount, $gst, $gstMode);",'service_tax_formula')
segment=one(segment,"            $this->audit($purchase, 'CREATE_OSL_PURCHASE', null, $purchase->toArray(), $actor);",
'''            if(!\\Illuminate\\Support\\Facades\\Schema::hasColumn('workshop_purchases','gst_mode')){
                throw ValidationException::withMessages(['gst_mode'=>'OSL supplier tax mode schema not installed. Purchase not saved.']);
            }
            $purchase->forceFill(['gst_mode'=>$gstMode])->save();
            $this->audit($purchase, 'CREATE_OSL_PURCHASE', null, $purchase->toArray(), $actor);''','service_mode_persist')
service=service[:a]+segment+service[b:]
controller=src['controller']
pattern=re.compile(r"(?m)^(\s*'lines\.\*\.gst_percent'\s*=>\s*\[[^\n]+\],\s*)$")
m=pattern.search(controller)
print('FIX70_CONTROLLER_FIELD_FOUND',bool(m))
if m:
 controller=controller[:m.end()]+"\n            // FIX70_OSL_GST_BASIS\n            'lines.*.gst_mode'=>['nullable','in:EXTRA,INCLUDED,NONE'],"+controller[m.end():]
else:
 # Independently validated in the service as well. Controller block safely
 # inserts alongside the validated 'lines' payload rather than guessing.
 token="'lines' => $lines,"
 controller=one(controller,token,token+"\n            // FIX70_OSL_GST_BASIS: each line carries independent supplier GST basis.",'controller_marker')
if marker not in controller:
 controller=one(controller,"    public function storeOsl(Request $request, PurchaseService $service): RedirectResponse",
'''    // FIX70_OSL_GST_BASIS validated by PurchaseService::saveOsl().
    public function storeOsl(Request $request, PurchaseService $service): RedirectResponse''','controller_scope')
edit=src['edit']
oldQ="$q=$this->quoteFromLine((float)$data['quantity'],(float)$data['vendor_rate'],(float)$data['discount_percent'],(float)$data['gst_percent']);"
newQ="""// FIX70_OSL_GST_BASIS — preserve original OSL tax basis on edit.
            $existingMode=strtoupper(trim((string)($row->gst_mode??'EXTRA')));
            $amCost=\\App\\Services\\OslPurchaseTax::quote(
                (float)$data['quantity'],(float)$data['vendor_rate'],
                (float)$data['discount_percent'],(float)$data['gst_percent'],$existingMode);
            $q=['discountAmount'=>$amCost['discount'],'taxable'=>$amCost['taxable'],
                'gstAmount'=>$amCost['gst'],'final'=>$amCost['total']];"""
edit=one(edit,oldQ,newQ,'edit_tax_basis')
if mode=='check':
 print('FIX70_PREFLIGHT_PASS_NO_FINANCIAL_WRITES');sys.exit(0)
def atomic(p,s):
 fd,tmp=tempfile.mkstemp(prefix='.fix70_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
for key,s in [('form',form),('controller',controller),('service',service),('edit',edit)]:atomic(files[key],s)
newService=root/'app/Services/OslPurchaseTax.php'
if newService.exists():raise SystemExit('FIX70_HELPER_EXISTS_MANUAL_REVIEW')
fd,tmp=tempfile.mkstemp(prefix='.fix70_',dir=newService.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8') as f:f.write((pkg/'OslPurchaseTax.php').read_text(encoding='utf8'))
 os.chmod(tmp,0o644);os.replace(tmp,newService)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX70_OSL_GST_MODE_APPLIED_NO_EXISTING_PURCHASE_ROWS_CHANGED')
