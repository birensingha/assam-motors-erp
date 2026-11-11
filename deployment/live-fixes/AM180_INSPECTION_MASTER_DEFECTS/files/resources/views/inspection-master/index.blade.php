<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Inspection Master | Assam Motors</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#173754;font-family:Arial,sans-serif;font-size:14px}
.header{background:#10395f;color:white;padding:18px 24px}.header h1{margin:0 0 4px;font-size:24px}.header small{color:#b9cfdf}
.wrap{max-width:1200px;padding:18px;margin:auto}.card{background:white;border:1px solid #d6e1eb;border-radius:13px;padding:16px;margin-bottom:14px;box-shadow:0 4px 16px #17375408}
.row{display:grid;grid-template-columns:1fr 170px 100px 100px 74px 190px 72px;gap:9px;align-items:center;margin-bottom:9px;padding:10px;background:#f7fafd;border:1px solid #e0e9f1;border-radius:10px}
label{font-size:11px;font-weight:bold;color:#5d7183;display:block}input,select{display:block;width:100%;font:inherit;padding:9px;margin-top:4px;border:1px solid #c1d2df;border-radius:7px;background:white}
input[type=checkbox]{width:auto;display:inline-block;margin:0} .tick{text-align:center}.tick input{margin-top:13px}
.badge{font-size:12px;color:#1d5970}.btn{border:0;border-radius:8px;padding:11px 15px;background:#0f4f7b;color:white;font-weight:700;cursor:pointer}
.btn.secondary{background:#e5edf6;color:#174869}.btn.danger{background:#fce9e8;color:#a43c36}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.note{background:#fff8e6;border-left:4px solid #d9a032;padding:11px;margin:12px 0}
.error{color:#a12a2a;padding:10px;border:1px solid #e3b2b2;background:#fff0f0;border-radius:8px}
.success{color:#1e6c56;background:#e8f5ee;padding:10px;border-radius:8px}
@media(max-width:950px){.row{grid-template-columns:repeat(2,minmax(0,1fr))}.row>div:first-child{grid-column:1/-1}}
</style></head><body>
<div class="header"><h1>ASSAM MOTORS · Inspection Master</h1><small>Admin-only Checklist Configuration | Active Version: {{ $version ?? 'Default 30-point template (not published)' }}</small></div>
<main class="wrap">
<section class="card">
<h2 style="margin-top:0">Vehicle Inspection Checklist</h2>
<p>Edit points, change selection type and defect conditions, then publish a <b>new version</b>. Removing a point here only affects future Job Cards; completed reports and existing job snapshots are preserved.</p>
<div class="note"><b>Important:</b> Select <b>Yes = defect</b> for warning lights/leaks (presence is faulty). Select <b>No = defect</b> for functioning lights/horn. Fuel and odometer are readings, not Good/Fair/Poor scoring. Fault findings can be submitted, but require observation and later verified repair for QC clearance.</div>
@if(session('success'))<p class="success">{{ session('success') }}</p>@endif
@if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
<form id="masterForm" action="{{ route('erp.inspection-master.publish') }}" method="post">
@csrf
<input type="hidden" name="points_json" id="pointsJson">
<div id="rows" role="group" aria-label="Inspection points"></div>
<div class="actions">
<button type="button" class="btn secondary" id="addPoint">+ Add Point</button>
<button type="button" class="btn secondary" id="resetDefault">Reset to current published version</button>
<button type="submit" class="btn" id="publish">Publish New Inspection Master Version</button>
</div></form>
<p class="badge" id="countSummary" aria-live="polite"></p>
</section>
</main>
<script>
(function(){
'use strict';
const types=@json($types);
const initial=@json($points);
const list=document.getElementById('rows');
const form=document.getElementById('masterForm');
const hidden=document.getElementById('pointsJson');
const count=document.getElementById('countSummary');
let items=JSON.parse(JSON.stringify(initial));
function el(tag,text,attrs={}){
 const n=document.createElement(tag);
 if(text!==null&&text!==undefined)n.textContent=String(text);
 Object.entries(attrs).forEach(([k,v])=>n.setAttribute(k,v));
 return n;
}
function group(title,control){
 const wrap=el('div');
 wrap.appendChild(el('label',title));wrap.appendChild(control);return wrap;
}
function mkSelect(opts,value,onChange){
 const s=el('select');
 opts.forEach(([v,t])=>{let o=el('option',t);o.value=v;s.appendChild(o);});
 s.value=value;
 s.addEventListener('change',()=>onChange(s.value));
 return s;
}
function draw(){
 list.replaceChildren();
 items.forEach((p,i)=>{
  const row=el('div',null,{class:'row'});
  const name=el('input',null,{maxlength:'160',required:'required'});
  name.value=p.label;
  name.addEventListener('input',()=>p.label=name.value);
  row.appendChild(group((i+1)+'. Point name',name));
  const type=mkSelect(Object.keys(types).map(k=>[k,k.replaceAll('_',' ').toUpperCase()]),p.type,v=>{
    p.type=v;p.defect_values=[];draw();
  });
  row.appendChild(group('Answer type',type));
  const required=el('input',null,{type:'checkbox'});
  required.checked=!!p.required;
  required.addEventListener('change',()=>p.required=required.checked);
  row.appendChild(group('Required',required));
  const critical=el('input',null,{type:'checkbox'});
  critical.checked=!!p.critical;
  critical.addEventListener('change',()=>{p.critical=critical.checked;p.weight=p.critical?2:1;draw();});
  row.appendChild(group('Safety critical',critical));
  const weight=el('input',null,{type:'number',min:'1',max:'10',required:'required'});
  weight.value=p.weight;
  weight.addEventListener('input',()=>p.weight=Number(weight.value));
  row.appendChild(group('Weight',weight));
  const defects=el('div');
  (types[p.type]||[]).forEach(value=>{
   let label=el('label');
   let cb=el('input',null,{type:'checkbox'});
   cb.checked=p.defect_values.includes(value);
   cb.addEventListener('change',()=>{
      const values=new Set(p.defect_values);
      if(cb.checked)values.add(value);else values.delete(value);
      p.defect_values=[...values];
   });
   label.appendChild(cb);label.appendChild(document.createTextNode(' '+value.replaceAll('_',' ')+' '));
   defects.appendChild(label);
  });
  if(!types[p.type].length)defects.textContent='Reading only';
  row.appendChild(group('Answers indicating defect',defects));
  const remove=el('button','Remove',{type:'button',class:'btn danger'});
  remove.setAttribute('aria-label','Remove '+p.label);
  remove.addEventListener('click',()=>{
     if(!confirm('Remove this point from the NEXT checklist version? Historical versions remain unchanged.'))return;
     items.splice(i,1);draw();
  });
  row.appendChild(remove);
  list.appendChild(row);
 });
 count.textContent=items.length+' active inspection point(s). Version is not published until you save.';
}
document.getElementById('addPoint').addEventListener('click',()=>{
  const existing=new Set(items.map(p=>p.key));
  let num=1;while(existing.has('custom'+num))num++;
  items.push({key:'custom'+num,label:'New Inspection Check',type:'condition',required:true,critical:false,weight:1,defect_values:['fair','poor']});
  draw();list.lastElementChild?.scrollIntoView({block:'nearest'});
});
document.getElementById('resetDefault').addEventListener('click',()=>{
 if(!confirm('Discard unsaved changes and reload the active checklist?'))return;
 items=JSON.parse(JSON.stringify(initial));draw();
});
form.addEventListener('submit',e=>{
 if(items.length<1){e.preventDefault();alert('At least one inspection point is required.');return;}
 hidden.value=JSON.stringify(items);
 if(!confirm('Publish '+items.length+' inspection points as a NEW version? Existing Job Cards will keep their snapshots.'))e.preventDefault();
});
draw();
})();
</script></body></html>
