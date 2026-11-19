#!/usr/bin/env python3
"""AM277: Make/Model metadata appears only inside transient Parts options.
Financial item labels, selected values and saved descriptions are unchanged.
"""
import sys,subprocess,tempfile,shutil,os
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
files={
 'purchase_controller':root/'app/Http/Controllers/Web/AdminPurchasePageController.php',
 'purchase_create_js':root/'public/js/purchase-entry.js',
 'purchase_edit_js':root/'public/js/purchase-edit.js',
 'estimate_controller':root/'app/Http/Controllers/Web/AdminEstimatePageController.php',
 'estimate_view':root/'resources/views/estimates/create.blade.php',
 'counter_controller':root/'app/Http/Controllers/Web/CounterPartsInvoiceController.php',
 'counter_view':root/'resources/views/counter-sales/create.blade.php',
}
if not (root/'artisan').is_file() or not all(p.is_file() for p in files.values()):
 raise SystemExit('AM277_STOP_APP_ROOT_OR_SOURCE_MISSING')
data={k:p.read_text('utf8',errors='surrogateescape') for k,p in files.items()}
marker='AM277_PARTS_CONTEXT_ONLY'
if any(marker in s for s in data.values()):
 print('AM277_ALREADY_APPLIED');sys.exit(0)
def edit(k,old,new,label):
 n=data[k].count(old);print('AM277_ANCHOR_'+label+'='+str(n))
 if n!=1:raise SystemExit('AM277_STOP_SOURCE_DRIFT_'+label)
 data[k]=data[k].replace(old,new,1)

# Append API metadata separately. The part 'name' remains untouched.
edit('purchase_controller',"'purchase_price','gst_percent','hsn_code'])",
 "'purchase_price','gst_percent','hsn_code','vehicle_make','vehicle_model'])",'SEARCH_SELECT')
edit('purchase_controller',"'name' => (string)$p->part_name,",
 """'name' => (string)$p->part_name,
                // AM277_PARTS_CONTEXT_ONLY, not included in selected part label.
                'vehicle_make' => trim((string)($p->vehicle_make ?? '')),
                'vehicle_model' => trim((string)($p->vehicle_model ?? '')),""",'SEARCH_JSON')

# Use additional <small> only in the dropdown. The selection function
# still writes exactly the original partLabel(p) to the focused input.
for k,el in [('purchase_create_js','btn'),('purchase_edit_js','b')]:
 old=el+'.append(title, meta);'
 new="""// AM277_PARTS_CONTEXT_ONLY: Make / Model shown only in suggestions.
        const vehicle=[p.vehicle_make,p.vehicle_model]
          .map(v=>String(v||'').trim()).filter(Boolean).join(' · ');
        if(vehicle){
          const vehicleText=document.createElement('small');
          vehicleText.className='am277-part-vehicle';
          vehicleText.textContent='Vehicle: '+vehicle;
          EL.append(title,vehicleText,meta);
        }else{EL.append(title,meta);}
""".replace('EL',el)
 edit(k,old,new,'DROPDOWN_'+k)
 if 'search.value = partLabel(p)' not in data[k]:
  raise SystemExit('AM277_STOP_PART_NAME_SELECTION_DRIFT_'+k)

edit('estimate_controller',"get(['id','part_code','part_name','hsn_code','unit','selling_price','gst_percent'])",
 "get(['id','part_code','part_name','hsn_code','unit','selling_price','gst_percent','vehicle_make','vehicle_model'])",
 'ESTIMATE_MASTER_SELECT')
edit('estimate_view',"'description' => $p->part_name,",
 """'description' => $p->part_name,
            'vehicle_make' => trim((string)($p->vehicle_make??'')),
            'vehicle_model' => trim((string)($p->vehicle_model??'')),""",'ESTIMATE_JSON_SEPARATE')
edit('counter_controller',"get(['id','part_code','part_name','hsn_code','selling_price','gst_percent','unit','stock_qty'])",
 "get(['id','part_code','part_name','hsn_code','selling_price','gst_percent','unit','stock_qty','vehicle_make','vehicle_model'])",
 'COUNTER_MASTER_SELECT')
edit('counter_view','data-stock="{{ $p->stock_qty }}">{{ $p->part_code }} · {{ $p->part_name }}</option>',
 'data-stock="{{ $p->stock_qty }}" data-vehicle-make="{{ $p->vehicle_make }}" data-vehicle-model="{{ $p->vehicle_model }}">{{ $p->part_code }} · {{ $p->part_name }}</option>',
 'COUNTER_OPTION_METADATA')
counter_script="""
<script id="am277-counter-parts-context">
(function(){
'use strict';
// AM277_PARTS_CONTEXT_ONLY: display metadata while a native selector is open.
// Selected option returns to its original part code/name on change or blur.
const isPart=el=>el instanceof HTMLSelectElement && el.matches('[data-part]');
const show=sel=>Array.from(sel.options).forEach(o=>{
  const vehicle=[o.dataset.vehicleMake,o.dataset.vehicleModel]
    .map(v=>String(v||'').trim()).filter(Boolean).join(' · ');
  if(!vehicle)return;
  if(!Object.prototype.hasOwnProperty.call(o.dataset,'am277Base')){
    o.dataset.am277Base=o.textContent;
  }
  o.textContent=o.dataset.am277Base+' — '+vehicle;
});
const hide=sel=>Array.from(sel.options).forEach(o=>{
  if(Object.prototype.hasOwnProperty.call(o.dataset,'am277Base')){
    o.textContent=o.dataset.am277Base;
  }
});
document.addEventListener('focusin',e=>{if(isPart(e.target))show(e.target);});
document.addEventListener('change',e=>{if(isPart(e.target))hide(e.target);});
document.addEventListener('focusout',e=>{if(isPart(e.target))hide(e.target);});
})();
</script>
"""
edit('counter_view',"<script>\n(function(){\n'use strict';",
 counter_script+"<script>\n(function(){\n'use strict';",'COUNTER_PICKER_TRANSIENT_CONTEXT')

with tempfile.TemporaryDirectory(prefix='am277_preflight_') as folder:
 t=Path(folder)
 for k in ('purchase_controller','estimate_controller','counter_controller'):
  p=t/(k+'.php');p.write_text(data[k])
  out=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=30)
  if out.returncode:raise SystemExit('AM277_STOP_PHP_LINT_'+k+out.stderr[-300:])
 for k in ('purchase_create_js','purchase_edit_js'):
  p=t/(k+'.js');p.write_text(data[k])
  if shutil.which('node'):
   out=subprocess.run(['node','--check',str(p)],cwd=root,capture_output=True,text=True,timeout=30)
   if out.returncode:raise SystemExit('AM277_STOP_JS_LINT_'+k+out.stderr[-300:])
  else:print('AM277_NODE_LINT_NOT_AVAILABLE_ON_HOST_'+k)
 for k in ('estimate_view','counter_view'):
  p=t/(k+'.blade.php');p.write_text(data[k])
  compiled=t/(k+'.compiled.php')
  compiler=r"require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"
  out=subprocess.run(['php','-r',compiler,str(p),str(compiled)],cwd=root,capture_output=True,text=True,timeout=40)
  if out.returncode:raise SystemExit('AM277_STOP_BLADE_COMPILE_'+k+out.stderr[-300:])
  out=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=30)
  if out.returncode:raise SystemExit('AM277_STOP_BLADE_PHP_'+k+out.stderr[-300:])

print('AM277_PRECHECK_PASS_NO_TRANSACTION_OR_PRINT_MUTATIONS')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups/AM277_PARTS_CONTEXT_ONLY'
if backup.exists():raise SystemExit('AM277_STOP_BACKUP_ALREADY_EXISTS')
backup.mkdir(parents=True)
for k,p in files.items():shutil.copy2(p,backup/(k+'.before'))
for k,p in files.items():
 fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am277_')
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(data[k])
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
print('AM277_APPLIED_BACKUP='+str(backup))
