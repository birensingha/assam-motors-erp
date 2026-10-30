<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Assam Motors · Inspection Master</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f6fa;color:#193753;font:13px Arial,sans-serif}
header{background:#12385e;color:#fff;padding:15px 22px}
header h1{font-size:20px;margin:0 0 6px}
header p{font-size:12px;color:#ccdeee;margin:0}
main{max-width:1320px;margin:auto;padding:16px}
.panel{border:1px solid #d5e1ed;border-radius:11px;background:#fff;padding:15px;margin:0 0 12px}
.rowhead{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
h2{font-size:16px;margin:0 0 9px}h3{font-size:13px;margin:0}
.muted{color:#607990;font-size:12px;line-height:1.6}
.warning{background:#fff8e7;border-left:4px solid #e4ae3c;padding:11px;border-radius:4px;line-height:1.5}
.good{background:#e7f5ec;padding:12px;border-radius:7px;color:#236d48}
.error{background:#fff0ee;padding:12px;border-radius:7px;color:#9c2727}
.action{border:0;border-radius:7px;padding:9px 13px;color:#fff;background:#155989;font-weight:700;cursor:pointer;font-size:12px}
.action.alt{background:#e6eef6;color:#164b75}.action.warn{background:#ffedf0;color:#9b283e}
.action:focus-visible,select:focus-visible,input:focus-visible{outline:2px solid #277dc6;outline-offset:2px}
.toolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
.point{background:#f9fbfd;border:1px solid #d9e3ed;border-radius:9px;padding:11px;margin:9px 0}
.pointtop{display:grid;grid-template-columns:minmax(240px,2.2fr) minmax(160px,1fr) 110px 110px 92px;align-items:end;gap:10px}
label{font-size:11px;font-weight:700;color:#59718b;display:block}
input[type=text],input[type=number],select{margin-top:5px;width:100%;min-width:0;padding:8px 9px;border:1px solid #bdcede;border-radius:6px;font:inherit;background:#fff}
input[type=checkbox]{width:16px;height:16px;vertical-align:middle}
.tick{display:flex;align-items:center;gap:7px;padding-bottom:10px}
.options{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-top:10px}
.option{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#334f67;background:#e8eef4;border-radius:6px;padding:6px 9px}
.pos-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
.pos-actions button{border:1px solid #bfd1df;border-radius:5px;background:#fff;color:#164a73;cursor:pointer;font-size:12px;padding:6px 10px}
.pos-actions button.delete{color:#a32929}
.small{font-size:11px}
@media(max-width:1050px){.pointtop{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.pointtop{grid-template-columns:1fr}.tick{padding-bottom:0}header{padding:13px}main{padding:10px}}
</style>
</head>
<body>
<header><h1>ASSAM MOTORS · Vehicle Inspection Master</h1>
<p>Admin configuration · Published Version {{ $version ?? 'Not Published' }} · Standard and Job-specific form configuration</p></header>
<main>
<div class="panel rowhead">
  <div>
    <h2>Inspection Form Configuration</h2>
    <p class="muted">Add, rename, reorder or remove points. Choose answer types, required fields, and which answer counts as a defect.</p>
  </div>
  <a class="action alt" style="text-decoration:none" href="{{ route('erp.job-cards.index') }}">Back to Job Cards</a>
</div>
<div class="panel warning"><strong>Stage 1 — Configuration only:</strong> You can save and publish Inspection Master versions, but existing Staff App submissions still use the original fixed 30-point form. Publishing a new version <strong>does not</strong> change any already assigned inspection or automatically send a new form. Direct Send / Customize & Send will be enabled only after the backend and Android submission are integrated.</div>
@if(session('success'))<div class="panel good">{{ session('success') }}</div>@endif
@if($errors->any())<div class="panel error" role="alert">{{ $errors->first() }}</div>@endif
<div class="panel">
  <div class="rowhead">
    <h2>Inspection Points <span id="pointsCount" class="muted"></span></h2>
    <div class="toolbar">
      <button class="action alt" id="addPoint" type="button">+ Add Point</button>
      <button class="action alt" id="resetPoints" type="button">Reset Unsaved Edits</button>
    </div>
  </div>
  <form id="masterForm" method="post" action="{{ route('erp.inspection-master.am206.publish') }}">
    @csrf
    <input type="hidden" name="points_json" id="pointsJson">
    <div id="pointRows"></div>
    <div class="toolbar" style="padding-top:10px">
      <button class="action" type="submit">Publish New Master Version</button>
      <span class="muted">Saved versions do not overwrite historical vehicle inspections.</span>
    </div>
  </form>
</div>
<div class="panel">
  <h2>Version History</h2>
  @forelse($history as $versionRow)
  <p class="small">Version {{ $versionRow->version }} — {{ $versionRow->status }} — {{ $versionRow->published_at }}</p>
  @empty
  <p class="muted">No published version yet. The editor is prefilled with the existing official 30-point inspection names.</p>
  @endforelse
</div>
</main>
<script>
(function(){
  'use strict';
  const initial=@json($points);
  const types=@json($types);
  const rows=document.getElementById('pointRows');
  const form=document.getElementById('masterForm');
  const hidden=document.getElementById('pointsJson');
  const countEl=document.getElementById('pointsCount');
  let points=JSON.parse(JSON.stringify(initial));
  function node(tag,txt,cls){
    const n=document.createElement(tag);
    if(txt!==null && txt!==undefined)n.textContent=String(txt);
    if(cls)n.className=cls;
    return n;
  }
  function wrap(label,el){
    const box=node('div');
    const title=node('label',label);
    box.append(title,el);
    return box;
  }
  function draw(){
    rows.replaceChildren();
    countEl.textContent='('+points.length+' points)';
    points.forEach((p,i)=>{
      const card=node('div',null,'point');
      const grid=node('div',null,'pointtop');
      const name=node('input');
      name.type='text';name.maxLength=100;name.required=true;name.value=p.label;
      name.oninput=()=>{p.label=name.value;};
      grid.append(wrap((i+1)+'. Inspection Point',name));
      const type=node('select');
      Object.keys(types).forEach(key=>{
        const option=node('option',key.replaceAll('_',' ').toUpperCase());
        option.value=key;type.append(option);
      });
      type.value=p.type;type.onchange=()=>{
        p.type=type.value;p.defect_values=[];draw();
      };
      grid.append(wrap('Answer Type',type));
      for(const [field,title] of [['required','Required'],['critical','Safety Critical']]){
        const c=node('input');c.type='checkbox';c.checked=Boolean(p[field]);
        c.onchange=()=>{p[field]=c.checked;};
        grid.append(wrap(title,node('div',null,'tick')));
        grid.lastElementChild.lastElementChild.append(c,node('span',p[field]?'Yes':'Optional','small'));
        c.onchange=()=>{p[field]=c.checked;draw();};
      }
      const weight=node('input');
      weight.type='number';weight.min='1';weight.max='10';weight.value=p.weight||1;
      weight.onchange=()=>p.weight=Number(weight.value);
      grid.append(wrap('Health Weight',weight));
      card.append(grid);
      const optionGroup=node('div',null,'options');
      optionGroup.append(node('strong','Defect when answer is:','small'));
      if(!types[p.type].length)optionGroup.append(node('span','Reading only (no default defect)','muted'));
      (types[p.type]||[]).forEach(value=>{
        const label=node('label',null,'option');
        const cb=node('input');
        cb.type='checkbox';cb.checked=(p.defect_values||[]).includes(value);
        cb.onchange=()=>{
          const selected=new Set(p.defect_values||[]);
          if(cb.checked)selected.add(value);else selected.delete(value);
          p.defect_values=Array.from(selected);
        };
        label.append(cb,node('span',value.replaceAll('_',' ')));
        optionGroup.append(label);
      });
      card.append(optionGroup);
      const actions=node('div',null,'pos-actions');
      for(const [textName,operation] of [['↑ Move Up','up'],['↓ Move Down','down'],['Remove','remove']]){
        const b=node('button',textName,operation==='remove'?'delete':'');
        b.type='button';b.disabled=operation==='up'?i===0:operation==='down'?i===points.length-1:false;
        b.onclick=()=>{
          if(operation==='remove'){
            if(!window.confirm('Remove this point from the NEXT checklist version?'))return;
            points.splice(i,1);
          } else {
            const j=operation==='up'?i-1:i+1;
            [points[i],points[j]]=[points[j],points[i]];
          }
          draw();
        };
        actions.append(b);
      }
      card.append(actions);rows.append(card);
    });
  }
  document.getElementById('addPoint').onclick=()=>{
    if(points.length>=100){alert('Maximum 100 points allowed.');return;}
    let i=1;const used=new Set(points.map(p=>p.key));
    while(used.has('custom'+i))i++;
    points.push({
      key:'custom'+i,label:'New Inspection Point',
      type:'condition',required:true,critical:false,
      weight:1,defect_values:['fair','poor']
    });
    draw();rows.lastElementChild?.scrollIntoView({block:'nearest'});
  };
  document.getElementById('resetPoints').onclick=()=>{
    if(!window.confirm('Discard unsaved form changes?'))return;
    points=JSON.parse(JSON.stringify(initial));draw();
  };
  form.onsubmit=(e)=>{
    if(points.length<1){e.preventDefault();alert('At least 1 point required.');return;}
    if(points.some(x=>!x.label.trim())){e.preventDefault();alert('Every point needs a name.');return;}
    hidden.value=JSON.stringify(points);
    if(!window.confirm('Publish '+points.length+' points as a new Inspection Master version? This does NOT send forms to the Staff App yet.'))e.preventDefault();
  };
  draw();
})();
</script>
</body>
</html>
