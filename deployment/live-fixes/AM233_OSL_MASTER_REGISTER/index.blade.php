@extends('layouts.erp')
@section('title','OSL Master Register · ASSAM MOTORS')
@section('content')
{{-- AM233_OSL_MASTER_REGISTER: unified reusable OSL service catalog. Vendor purchases and customer billing remain separate. --}}
<style>
.am233-wrap{max-width:1400px;margin:auto;color:#173b5d}
.am233-header{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;margin-bottom:15px}
.am233-header h1{font-size:25px;margin:0;color:#123c67}
.am233-header p{margin:5px 0;color:#5b7187;font-size:12px}
.am233-card{background:#fff;border:1px solid #d7e6f2;border-radius:12px;padding:15px;margin:14px 0}
.am233-search{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.am233-search input,.am233-grid input,.am233-new input{min-height:37px;padding:8px;border:1px solid #a9c6dd;border-radius:7px;font-size:12px}
.am233-search input{flex:1 1 240px}
.am233-new{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.am233-new input{flex:1 1 250px}
.am233-table{width:100%;border-collapse:collapse;min-width:900px}
.am233-table th,.am233-table td{padding:8px;border-bottom:1px solid #e6eff6;font-size:12px;text-align:left}
.am233-table th{background:#eaf4fd;font-size:11px;color:#235777}
.am233-table input{width:100%;max-width:100%;box-sizing:border-box}
.am233-table .am233-code{font-family:monospace;font-size:11px}
.am233-action{display:flex;gap:6px;align-items:center}
.am233-action input{width:145px}
.am233-table select{height:37px;border-radius:6px;border:1px solid #acc8dd}
.am233-scroll{overflow-x:auto}
.am233-note{font-size:11px;color:#51708b}
.am233-feedback{margin:8px 0;font-size:12px}
</style>
<div class="am233-wrap">
  <div class="am233-header"><div><h1>OSL Master Register</h1><p>Outside Labour / Service catalog shared by Estimates and OSL Purchases. Master does not itself create a purchase, job card bill or payment.</p></div>
    <a class="btn" href="{{ route('erp.purchases.osl.create') }}">← OSL Purchase</a>
  </div>
  @if(session('success'))<div class="am233-card">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="am233-card" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  <div class="am233-card">
    <form id="am233-create" class="am233-new">
      <label for="am233-name"><strong>+ New OSL Service</strong></label>
      <input id="am233-name" name="name" minlength="3" maxlength="180" placeholder="e.g. SINGLE PANEL PAINT – BUMPER" required>
      <button type="submit" class="btn primary">Create OSL Master</button>
      <span id="am233-feedback" class="am233-feedback" role="status"></span>
    </form>
    <p class="am233-note">Duplicates are blocked by the existing OSL Master service. Existing invoice and job card lines are not altered by master changes.</p>
  </div>
  <div class="am233-card">
    <form method="get" action="{{ route('erp.osl-masters.index') }}" class="am233-search">
      <input name="q" value="{{ $q }}" placeholder="Search OSL code / name">
      <button class="btn">Search</button><a class="btn ghost" href="{{ route('erp.osl-masters.index') }}">Reset</a>
    </form>
  </div>
  <div class="am233-card am233-scroll">
    <table class="am233-table"><thead><tr><th>OSL Code</th><th>Service Name</th><th>Category</th><th>Default Cost ₹</th><th>GST %</th><th>Active</th><th>Update / Audit Reason</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
    <tr>
      <td class="am233-code">{{ $row->osl_code }}</td>
      <td><input form="am233-row-{{ $row->id }}" name="name" maxlength="180" required value="{{ $row->osl_name }}"></td>
      <td><input form="am233-row-{{ $row->id }}" name="category" maxlength="100" value="{{ $row->category }}"></td>
      <td><input form="am233-row-{{ $row->id }}" type="number" min="0" step="0.01" name="default_cost" value="{{ $row->default_cost }}"></td>
      <td><input form="am233-row-{{ $row->id }}" type="number" min="0" max="100" step="0.01" name="gst_percent" value="{{ $row->gst_percent }}"></td>
      <td><select form="am233-row-{{ $row->id }}" name="active"><option value="1" @selected((int)$row->active===1)>Active</option><option value="0" @selected((int)$row->active===0)>Inactive</option></select></td>
      <td><form id="am233-row-{{ $row->id }}" method="post" action="{{ route('erp.osl-masters.update',$row->id) }}" class="am233-action">@csrf
         <input name="reason" minlength="4" maxlength="250" placeholder="Reason for change" required>
         <button type="submit" class="btn small">Save</button>
      </form></td>
    </tr>
    @empty
    <tr><td colspan="7">No matching OSL Master entries. Create a service above.</td></tr>
    @endforelse
    </tbody></table>
    <p class="am233-note">Showing up to 1,000 OSL services. Inactive items remain visible for historical reference but are not offered in new Estimate/Purchase selection.</p>
  </div>
</div>
<script>
(function(){
'use strict';
const form=document.getElementById('am233-create'),feedback=document.getElementById('am233-feedback');
if(!form||!feedback)return;
const url=@json(route('erp.osl-masters.quick-create'));
const csrf=@json(csrf_token());
form.addEventListener('submit',async e=>{
  e.preventDefault();
  const name=String(form.querySelector('[name=name]').value||'').trim();
  if(name.length<3)return;
  const button=form.querySelector('button[type=submit]');
  button.disabled=true;feedback.textContent='Saving OSL Master...';
  try{
    const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({name})});
    const result=await response.json();
    if(!response.ok||!result.ok)throw Error(result.message||'Unable to create OSL Master');
    feedback.textContent='Created '+result.code+' — '+result.name;
    window.location.reload();
  }catch(ex){feedback.textContent='Not saved: '+String(ex.message||ex)}
  finally{button.disabled=false}
});
})();
</script>
@endsection
