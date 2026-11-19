#!/usr/bin/env python3
"""AM276: scoped vehicle catalog in customer modal; Add Parts-style Configuration form.
Safe source anchors, PHP Blade compilation, backups, atomic writes.
"""
import sys,re,subprocess,tempfile,os,shutil
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: patch.py check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
files={'customer':root/'resources/views/admin/native/customer-detail.blade.php',
       'vehicle':root/'resources/views/admin/native/vehicles.blade.php'}
if not (root/'artisan').is_file() or not all(x.is_file() for x in files.values()):
 raise SystemExit('AM276_STOP_WRONG_APP_ROOT')
data={k:v.read_text() for k,v in files.items()}
if 'AM276_SCOPED_VARIANTS' in data['customer']:
 print('AM276_ALREADY_APPLIED');sys.exit(0)
def once(k,old,new,label):
 n=data[k].count(old)
 print('AM276_ANCHOR_'+label+'_'+str(n))
 if n!=1:raise SystemExit('AM276_STOP_SOURCE_DRIFT_'+label)
 data[k]=data[k].replace(old,new,1)
# Customer's current four global datalists display unrelated models/variants.
# Preserve vehicle master entries in an escaped JSON collection and populate
# only the input's relevant make+model+year+fuel options within the active modal.
before='<datalist id="amVehicleMakes">@foreach($vehicleCatalog->pluck(\'make_name\')->filter()->unique() as $x)<option value="{{ $x }}">@endforeach</datalist>'
after='''{{-- AM276_SCOPED_VARIANTS: fixed modal-local linked Make/Model/Year/Variant/Fuel choices. --}}
<datalist id="amVehicleMakes">@foreach($vehicleCatalog->pluck('make_name')->filter()->unique() as $x)<option value="{{ $x }}">@endforeach</datalist>'''
once('customer',before,after,'CUSTOMER_MASTER_MARKER')
before='<script>\nfunction amOpen(id)'
after='''<style>
/* AM276: compact Add Parts-like modal; list remains a popup, never changes modal height. */
#amAddVehicle .am-modal-box,.am-modal[id^="amEditVehicle"] .am-modal-box{max-width:900px;width:min(96vw,900px)}
#amAddVehicle .am-modal-body,.am-modal[id^="amEditVehicle"] .am-modal-body{padding:14px 18px}
#amAddVehicle .am-form,.am-modal[id^="amEditVehicle"] .am-form{gap:9px 12px}
#amAddVehicle .am-form label,.am-modal[id^="amEditVehicle"] .am-form label{font-size:11px;font-weight:700}
#amAddVehicle .am-form input,.am-modal[id^="amEditVehicle"] .am-form input{min-height:35px}
</style>
<script id="am276-dependent-vehicle-catalog">
(function(){
'use strict';
const records=(@json($vehicleCatalog)).map(r=>({
  make:r.make_name||'',model:r.model_name||'',
  variant:r.variant_name||'',fuel:r.fuel_type||'',
  yearFrom:Number(r.year_from||0),yearTo:Number(r.year_to||0)
}));
const norm=s=>String(s||'').trim().toLocaleLowerCase();
const unique=(items)=>[...new Set(items.filter(Boolean))].sort((a,b)=>a.localeCompare(b));
const formSelector='.am-modal form';
function fill(id,items){
 const list=document.getElementById(id);if(!list)return;
 list.replaceChildren();
 for(const value of unique(items)){
  const opt=document.createElement('option');opt.value=value;list.append(opt);
 }
}
function focusForm(form,clear){
 if(!form)return;
 const make=form.querySelector('[name="make"]'),
 model=form.querySelector('[name="model"]'),
 year=form.querySelector('[name="year"]'),
 variant=form.querySelector('[name="variant"]'),
 fuel=form.querySelector('[name="fuel"]');
 if(!make||!model||!variant||!fuel)return;
 const matches=records.filter(r=>norm(r.make)===norm(make.value));
 const models=unique(matches.map(r=>r.model));
 const linked=matches.filter(r=>norm(r.model)===norm(model.value));
 const number=Number(year?.value||0);
 const byYear=linked.filter(r=>!number||(!r.yearFrom||number>=r.yearFrom)&&(!r.yearTo||number<=r.yearTo));
 const yearRows=byYear.length?byYear:linked;
 const withFuel=yearRows.filter(r=>norm(r.fuel)===norm(fuel.value));
 const relevant=(fuel.value&&withFuel.length)?withFuel:yearRows;
 fill('amVehicleModels',models);
 fill('amVehicleVariants',relevant.map(r=>r.variant));
 fill('amVehicleFuels',unique(yearRows.map(r=>r.fuel)));
 if(clear==='make'){model.value='';variant.value='';fuel.value='';}
 else if(clear==='model'||clear==='year'){variant.value='';}
 else if(clear==='fuel'){variant.value='';}
 variant.placeholder=relevant.some(r=>r.variant)?'Select matching variant':'No matching variant in Vehicle Master';
 variant.title='Only variants linked to the selected Make / Model should be entered. Add missing variant in Configuration → Vehicle Master.';
}
document.querySelectorAll(formSelector).forEach(form=>{
 const make=form.querySelector('[name="make"]'),
 model=form.querySelector('[name="model"]'),
 year=form.querySelector('[name="year"]'),
 variant=form.querySelector('[name="variant"]'),
 fuel=form.querySelector('[name="fuel"]');
 if(!make||!model||!variant||!fuel)return;
 for(const [el,type] of [[make,'make'],[model,'model'],[year,'year'],[fuel,'fuel']]){
  if(!el)continue;
  el.addEventListener('change',()=>focusForm(form,type));
  if(type==='make'||type==='model')el.addEventListener('input',()=>focusForm(form,type));
 }
 for(const el of [make,model,year,variant,fuel])
  el.addEventListener('focus',()=>focusForm(form,''));
 form.addEventListener('submit',ev=>{
  const candidates=records.filter(r=>norm(r.make)===norm(make.value)&&norm(r.model)===norm(model.value));
  const chosen=candidates.filter(r=>norm(r.variant)===norm(variant.value));
  if(!chosen.length){ev.preventDefault();variant.setCustomValidity('Variant does not belong to the selected Make and Model. Add it in Configuration → Vehicle Master.');variant.reportValidity();return;}
  variant.setCustomValidity('');
 });
 variant.addEventListener('input',()=>variant.setCustomValidity(''));
});
const oldOpen=window.amOpen;
document.addEventListener('click',e=>{
 const b=e.target.closest('[onclick*="amOpen"]');
 if(!b)return;
 queueMicrotask(()=>{
  const form=document.querySelector('.am-modal.open form');
  focusForm(form,'');
 });
},true);
})();
</script>
<script>
function amOpen(id)'''
once('customer',before,after,'CUSTOMER_SCRIPT')
# Original vehicle form lives in a details card but shows rows like an unstyled block.
# Reuse existing Add Part grid and panel form styling, only adjust structural CSS.
before='<details id="vehicleEditPanel" class="card pro-create-panel">'
after='''<style id="am276-vehicle-master-parts-style">
/* AM276: align Configuration Vehicle Master form with Add Parts design. */
#vehicleEditPanel:not([open]){display:none!important}
#vehicleEditPanel{border:1px solid #cbd9e6;border-radius:10px;padding:14px 16px;margin:12px 0;background:#fff;box-shadow:0 7px 24px rgba(20,56,98,.08)}
#vehicleEditPanel>summary{font-size:15px;font-weight:800;color:#193f70;margin-bottom:12px}
#vehicleEditPanel form.vehicle-create-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 12px}
#vehicleEditPanel form.vehicle-create-grid label{min-width:0;font-size:11px;font-weight:750}
#vehicleEditPanel form.vehicle-create-grid input:not([type=checkbox]){width:100%;min-width:0;box-sizing:border-box;height:35px;border-radius:6px}
@media(max-width:820px){#vehicleEditPanel form.vehicle-create-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){#vehicleEditPanel form.vehicle-create-grid{grid-template-columns:1fr}}
</style>
<details id="vehicleEditPanel" class="card pro-create-panel">'''
once('vehicle',before,after,'CONFIG_ADD_PARTS_STYLE')
with tempfile.TemporaryDirectory(prefix='am276_') as t:
 for k in data:
  target=Path(t)/(k+'.blade.php');target.write_text(data[k])
  cmd=['php','-r',"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));""",str(target),str(Path(t)/(k+'.compiled.php'))]
  r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=60)
  if r.returncode:raise SystemExit('AM276_STOP_BLADE_COMPILE_'+k+':'+r.stderr[-300:])
  r=subprocess.run(['php','-l',str(Path(t)/(k+'.compiled.php'))],cwd=root,text=True,capture_output=True)
  if r.returncode:raise SystemExit('AM276_STOP_BLADE_PHP_'+k+':'+r.stderr[-300:])
print('AM276_PRECHECK_PASS')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups/AM276_VEHICLE_VARIANT'
if backup.exists():raise SystemExit('AM276_STOP_BACKUP_ALREADY_EXISTS')
backup.mkdir(parents=True)
for k,p in files.items():shutil.copy2(p,backup/(k+'.before'))
def atomic(p,value):
 fd,name=tempfile.mkstemp(dir=p.parent,prefix='.am276_')
 with os.fdopen(fd,'w') as out:out.write(value)
 os.chmod(name,p.stat().st_mode&0o777);os.replace(name,p)
for k,p in files.items():atomic(p,data[k])
print('AM276_PATCH_APPLIED_BACKUP='+str(backup))
