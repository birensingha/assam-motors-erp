#!/usr/bin/env python3
"""Show safely linked vehicle-master fields in BOTH staging invoice print modes.
Only resources/views/invoices/print.blade.php is modified. No DB writes.
"""
import hashlib
import os
import re
import shutil
import stat
import sys
import time
from pathlib import Path

root=Path(sys.argv[1]).resolve()
p=root/'resources/views/invoices/print.blade.php'
if not (root/'artisan').is_file() or not p.is_file():
    raise SystemExit('SAFE_STOP: Laravel root or invoice print file not found')
s=p.read_text(encoding='utf-8')
prefix=hashlib.sha256(s.encode()).hexdigest()[:16]
print('BEFORE_PRINT_SHA',prefix)
if 'AM_INVOICE_VEHICLE_MASTER_V1' in s:
    print('INVOICE_VEHICLE_MASTER_ALREADY_INSTALLED')
    raise SystemExit(0)
if prefix!='a7dc750636679a43' or 'AM_HSN_HEIGHT_NOTE_V1' not in s:
    raise SystemExit('SAFE_STOP: live print file no longer matches the audited HSN layout')

load = r"""
@php
// AM_INVOICE_VEHICLE_MASTER_V1 — render-only. Never change saved invoice or vehicle records.
// Vehicle must match Job Card's ID, customer and registration to prevent cross-customer data.
$amVehicle=null;
$amCatalog=null;
$amRegNormalize=static fn($value)=>strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)$value));
$amReg=$amRegNormalize($inv->vehicle_reg_no??$job->vehicle_reg_no??'');
$amVId=(int)($job->vehicle_id??$inv->vehicle_id??0);
$amCId=(int)($job->customer_id??$inv->customer_id??0);
if($amVId>0 && $amCId>0 && $amReg!=='' && \Illuminate\Support\Facades\Schema::hasTable('am_vehicle_master')){
    $amCandidate=\Illuminate\Support\Facades\DB::table('am_vehicle_master')
        ->where('id',$amVId)->where('customer_id',$amCId)->first();
    if($amCandidate && $amRegNormalize($amCandidate->registration_no??'')===$amReg){
        $amVehicle=$amCandidate;
    }
}
$amMake=trim((string)($amVehicle->make_name??$job->vehicle_make??''));
$amModel=trim((string)($amVehicle->model_name??$job->vehicle_model??''));
$amYear=trim((string)($amVehicle->model_year??$job->vehicle_year??''));
$amVariant=trim((string)($amVehicle->variant_name??$job->vehicle_variant??''));
$amFuel=trim((string)($amVehicle->fuel_type??$job->fuel_type??''));
$amChassis=trim((string)($amVehicle->chassis_no??$job->vehicle_chassis_no??''));
$amEngineNo=trim((string)($amVehicle->engine_no??$job->vehicle_engine_no??''));
$amMileage=$amVehicle->odometer_km??$job->odometer_km??null;
$amRegDate=trim((string)($amVehicle->registration_date??''));
$amNickname=trim((string)($amVehicle->nickname??''));
// Engine CC/code/transmission are not columns in am_vehicle_master.
// Only pull these from a UNIQUE, exact variant + fuel + model year catalog match.
if($amMake!=='' && $amModel!=='' && $amVariant!=='' && $amFuel!=='' &&
   preg_match('/^(19|20)\d{2}$/',$amYear) &&
   \Illuminate\Support\Facades\Schema::hasTable('am_vehicle_catalog_master')){
    $amMatches=\Illuminate\Support\Facades\DB::table('am_vehicle_catalog_master')
      ->whereRaw('UPPER(TRIM(make_name))=?',[strtoupper($amMake)])
      ->whereRaw('UPPER(TRIM(model_name))=?',[strtoupper($amModel)])
      ->whereRaw('UPPER(TRIM(variant_name))=?',[strtoupper($amVariant)])
      ->whereRaw('UPPER(TRIM(fuel_type))=?',[strtoupper($amFuel)])
      ->where('year_from','<=',(int)$amYear)
      ->where('year_to','>=',(int)$amYear)
      ->where('active',1)
      ->limit(2)->get();
    if($amMatches->count()===1) $amCatalog=$amMatches->first();
}
$amCC=trim((string)($amCatalog->engine_cc??''));
$amEngineCode=trim((string)($amCatalog->engine_code??''));
$amTransmission=trim((string)($amCatalog->transmission??''));
$amGeneration=trim((string)($amCatalog->generation_name??''));
@endphp
"""
if s.count('<section class="info-panels">')!=1:
    raise SystemExit('SAFE_STOP: info panel anchor mismatch')
s=s.replace('<section class="info-panels">',load+'\n<section class="info-panels">',1)

vehicle_job = r"""<div class="info"><h3>Vehicle / Job</h3><dl>
  <dt>Job Card</dt><dd>{{ $job->job_no }}</dd>
  <dt>Registration</dt><dd>{{ $inv->vehicle_reg_no ?? $job->vehicle_reg_no ?? '—' }}</dd>
  <dt>Make</dt><dd>{{ $amMake!==''?$amMake:'—' }}</dd>
  <dt>Model</dt><dd>{{ $amModel!==''?$amModel:'—' }}</dd>
  <dt>Model Year</dt><dd>{{ $amYear!==''?$amYear:'—' }}</dd>
  <dt>Variant</dt><dd>{{ $amVariant!==''?$amVariant:'—' }}</dd>
  <dt>Fuel Type</dt><dd>{{ $amFuel!==''?$amFuel:'—' }}</dd>
  <dt>Odometer</dt><dd>{{ $amMileage!==null&&$amMileage!==''?number_format((float)$amMileage,0).' km':'—' }}</dd>
</dl></div>"""

identity = r"""<div class="info"><h3>Vehicle Identity / Specifications</h3><dl>
  <dt>Chassis / VIN</dt><dd>{{ $amChassis!==''?$amChassis:'—' }}</dd>
  <dt>Engine No.</dt><dd>{{ $amEngineNo!==''?$amEngineNo:'—' }}</dd>
  <dt>Engine CC</dt><dd>{{ $amCC!==''?(preg_match('/cc/i',$amCC)?$amCC:$amCC.' cc'):'—' }}</dd>
  <dt>Engine Code</dt><dd>{{ $amEngineCode!==''?$amEngineCode:'—' }}</dd>
  <dt>Transmission</dt><dd>{{ $amTransmission!==''?$amTransmission:'—' }}</dd>
  <dt>Generation</dt><dd>{{ $amGeneration!==''?$amGeneration:'—' }}</dd>
  <dt>Registration Date</dt><dd>{{ $amRegDate!==''?(strtotime($amRegDate)?date('d M Y',strtotime($amRegDate)):$amRegDate):'—' }}</dd>
  <dt>Nickname</dt><dd>{{ $amNickname!==''?$amNickname:'—' }}</dd>
  <dt>Service</dt><dd>{{ $job->service_type??'—' }}</dd>
</dl></div>"""

def replace_panel(heading_pattern,new_markup):
    global s
    ptn=re.compile(r'<div class="info"><h3>'+heading_pattern+r'</h3><dl>.*?</dl></div>',re.I|re.S)
    s,n=ptn.subn(lambda _:new_markup,s)
    if n!=1: raise SystemExit('SAFE_STOP: panel count for '+heading_pattern+' was '+str(n))
replace_panel('Vehicle / Job',vehicle_job)
replace_panel('Vehicle Identity',identity)

checks=['AM_INVOICE_VEHICLE_MASTER_V1','am_vehicle_master','am_vehicle_catalog_master',
        'Engine CC','Engine Code','Transmission','Generation','Model Year','Variant','Fuel Type',
        'Odometer','Vehicle Identity / Specifications']
for x in checks:
    if x not in s: raise SystemExit('SAFE_STOP: expected vehicle field missing: '+x)
if s.count('<section class="info-panels">')!=1 or s.count('class="amounts"')!=1 or s.count('HSN / SAC-wise Tax Summary')!=1:
    raise SystemExit('SAFE_STOP: unrelated invoice sections changed')
backup=p.with_name(p.name+'.before-vehicle-master-print-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(p,backup)
tmp=p.with_name(p.name+'.vehicle-master-temp')
tmp.write_text(s,encoding='utf-8')
os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
os.replace(tmp,p)
print('INVOICE_VEHICLE_MASTER_APPLIED','BACKUP='+backup.name,'AFTER_SHA='+hashlib.sha256(s.encode()).hexdigest()[:16])
