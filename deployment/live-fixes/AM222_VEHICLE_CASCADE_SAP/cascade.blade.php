
{{-- AM222_VEHICLE_CASCADE_SAP: Make -> Model -> Variant, independent accurate catalogue choices. --}}
@php
  $am222Catalog=[];
  if(\Illuminate\Support\Facades\Schema::hasTable('am_vehicle_catalog_master')){
    $am222Catalog=\Illuminate\Support\Facades\DB::table('am_vehicle_catalog_master')
      ->where('active',1)->orderBy('make_name')->orderBy('model_name')
      ->limit(12000)->get(['make_name','model_name','variant_name','fuel_type','year_from','year_to'])
      ->map(fn($r)=>[
        'make'=>trim((string)$r->make_name),'model'=>trim((string)$r->model_name),
        'variant'=>trim((string)$r->variant_name),
        'fuel'=>trim((string)($r->fuel_type??'')),
        'from'=>(int)($r->year_from??0),'to'=>(int)($r->year_to??0)
      ])->all();
  }
  if(\Illuminate\Support\Facades\Schema::hasTable('vehicle_makes')
     &&\Illuminate\Support\Facades\Schema::hasTable('vehicle_models')
     &&\Illuminate\Support\Facades\Schema::hasTable('vehicle_variants')){
    foreach(\Illuminate\Support\Facades\DB::table('vehicle_makes as k')
      ->join('vehicle_models as m','m.make_id','=','k.id')
      ->leftJoin('vehicle_variants as v','v.model_id','=','m.id')
      ->where('k.active',1)->where('m.active',1)
      ->orderBy('k.name')->orderBy('m.name')->limit(12000)
      ->get(['k.name as make','m.name as model','v.name as variant','v.fuel_type as fuel','v.year_from as start_year','v.year_to as end_year','v.active as variant_active']) as $am222Linked){
      if($am222Linked->variant!==null && !(bool)$am222Linked->variant_active)continue;
      $am222Catalog[]=[
        'make'=>trim((string)$am222Linked->make),
        'model'=>trim((string)$am222Linked->model),
        'variant'=>trim((string)($am222Linked->variant??'')),
        'fuel'=>trim((string)($am222Linked->fuel??'')),
        'from'=>(int)($am222Linked->start_year??0),'to'=>(int)($am222Linked->end_year??0)
      ];
    }
  }
@endphp
<style>
  /* SAP-inspired, compact native overlay: no page/form height change */
  .am222-field{position:relative!important;min-width:0}
  .am222-field>input{padding-right:40px!important}
  .am222-open{position:absolute;right:7px;bottom:6px;width:29px;height:29px;
    display:grid;place-items:center;border:1px solid #c0d1e1;border-radius:6px;
    color:#275277;background:#f5f9fc;cursor:pointer;font-weight:850}
  .am222-open:hover,.am222-open:focus-visible{background:#dcecf9;outline:2px solid #8dbbe0}
  #am222-popup{position:fixed;z-index:99999;display:none;min-width:210px;
    max-height:min(330px,55dvh);overflow:hidden;background:#fff;color:#183952;
    border:1px solid #97afc8;border-radius:10px;
    box-shadow:0 14px 38px rgba(9,39,75,.25);font:inherit}
  #am222-popup .am222-heading{padding:10px 12px 8px;
    display:flex;align-items:center;justify-content:space-between;gap:8px;
    font-weight:800;font-size:12px;border-bottom:1px solid #e0eaf3;background:#eff5fa}
  #am222-popup .am222-list{max-height:245px;overflow-y:auto;overscroll-behavior:contain;padding:4px}
  #am222-popup button.am222-choice{display:block;width:100%;text-align:left;
    background:white;border:0;border-radius:6px;padding:9px 10px;
    color:#204562;cursor:pointer;line-height:1.35;font-size:13px}
  #am222-popup button.am222-choice:hover,
  #am222-popup button.am222-choice[aria-selected="true"]{background:#deedfb;color:#0b4380}
  #am222-popup .am222-sub{display:block;font-size:10px;color:#677d91;padding-top:3px}
  #am222-popup .am222-nomatch{font-size:12px;color:#5f738a;padding:12px 10px}
  #am222-popup .am222-create{color:#126098!important;border-top:1px solid #e0e8f0!important;
    font-weight:800}
  #am222-popup .am222-hint{border-top:1px solid #e2ebf5;padding:6px 10px;
    font-size:10px;color:#63798b;background:#f8fafc}
</style>
<script>
(function(){
  'use strict';
  const data={{ \Illuminate\Support\Js::from($am222Catalog) }};
  const fields=[
    {id:'vmMake',key:'make',title:'Vehicle Make'},
    {id:'vmModel',key:'model',title:'Vehicle Model'},
    {id:'vmVariant',key:'variant',title:'Vehicle Variant'}
  ];
  const normalize=s=>String(s||'').trim().replace(/\s+/g,' ').toLocaleLowerCase();
  const $=id=>document.getElementById(id);
  const controls={};
  fields.forEach(f=>{controls[f.key]=$(f.id)});
  if(!controls.make||!controls.model||!controls.variant)return;
  const list=(Array.isArray(data)?data:[]).filter(r=>r&&r.make&&r.model);
  const popup=document.createElement('div');
  popup.id='am222-popup';
  popup.setAttribute('role','listbox');
  popup.innerHTML='<div class="am222-heading"><span></span><span class="am222-count"></span></div>'+
    '<div class="am222-list"></div><div class="am222-hint">Type to search · ↑ ↓ Enter to select · Esc to close</div>';
  document.body.appendChild(popup);
  const heading=popup.querySelector('.am222-heading span');
  const count=popup.querySelector('.am222-count');
  const body=popup.querySelector('.am222-list');
  let current=null,active=0,options=[];
  const uniq=(rows,key)=>Array.from(new Map(rows.filter(r=>r[key]).map(r=>[normalize(r[key]),r])).values());
  function scoped(key){
    let entries=list;
    if(key==='model'||key==='variant'){
      const make=normalize(controls.make.value);
      if(!make)return [];
      entries=entries.filter(r=>normalize(r.make)===make);
    }
    if(key==='variant'){
      const model=normalize(controls.model.value);
      if(!model)return [];
      entries=entries.filter(r=>normalize(r.model)===model);
    }
    return uniq(entries,key).sort((a,b)=>String(a[key]).localeCompare(String(b[key]),undefined,{sensitivity:'base',numeric:true}));
  }
  function position(){
    if(!current)return;
    const r=current.input.getBoundingClientRect(),max=window.innerHeight;
    const width=Math.max(230,Math.min(510,r.width));
    popup.style.width=width+'px';
    popup.style.left=Math.max(6,Math.min(r.left,window.innerWidth-width-6))+'px';
    const high=Math.min(330,Math.round(max*.55));
    popup.style.top=(max-r.bottom<high&&r.top>high?r.top-high-4:r.bottom+4)+'px';
  }
  function close(){
    if(current)current.input.setAttribute('aria-expanded','false');
    current=null;popup.style.display='none';options=[];active=0;
  }
  function apply(value){
    if(!current)return;
    const field=current.key,input=current.input;
    const changed=normalize(input.value)!==normalize(value);
    input.value=value;
    input.dispatchEvent(new Event('input',{bubbles:true}));
    input.dispatchEvent(new Event('change',{bubbles:true}));
    if(changed){
      if(field==='make'){controls.model.value='';controls.variant.value='';}
      if(field==='model')controls.variant.value='';
    }
    close();
    input.focus();
  }
  function render(input,key,all){
    if(current&&current.input!==input)close();
    current={input,key};
    input.setAttribute('aria-expanded','true');
    heading.textContent=fields.find(f=>f.key===key).title;
    body.replaceChildren();
    const filter=all?'':normalize(input.value);
    const available=scoped(key);
    const filtered=available.filter(r=>normalize(r[key]).includes(filter));
    const subset=filtered.slice(0,85);
    options=[];
    count.textContent=filtered.length+' match';
    subset.forEach((item,index)=>{
      const button=document.createElement('button');
      button.type='button';button.className='am222-choice';
      button.setAttribute('role','option');
      button.setAttribute('aria-selected',index===0?'true':'false');
      button.textContent=item[key];button.dataset.value=item[key];
      if(key==='variant'&&(item.fuel||item.from)){
        const sub=document.createElement('span');
        sub.className='am222-sub';
        sub.textContent=[item.fuel,item.from?(item.from+(item.to&&item.to!==item.from?'–'+item.to:'')):''].filter(Boolean).join(' · ');
        button.appendChild(sub);
      }
      button.addEventListener('pointerdown',e=>{e.preventDefault();apply(item[key])});
      body.appendChild(button);options.push(button);
    });
    if(!subset.length){
      const notice=document.createElement('div');notice.className='am222-nomatch';
      notice.textContent=key==='model'&&!normalize(controls.make.value)?'Select a Make first':
        key==='variant'&&(!normalize(controls.make.value)||!normalize(controls.model.value))?
        'Select a Make and Model first':'No existing match. You may create a new value.';
      body.appendChild(notice);
    }
    if(filter&&!available.some(r=>normalize(r[key])===normalize(input.value))){
      const create=document.createElement('button');
      create.type='button';create.className='am222-choice am222-create';
      create.textContent='＋ Use new '+key+': '+input.value.trim();
      create.addEventListener('pointerdown',e=>{e.preventDefault();apply(input.value.trim())});
      body.appendChild(create);
    }
    active=0;
    position();popup.style.display='block';
  }
  function keydown(event){
    if(!current||current.input!==event.target)return;
    if(event.key==='Escape'){event.preventDefault();close();return}
    if(event.key==='ArrowDown'||event.key==='ArrowUp'){
      event.preventDefault();
      if(popup.style.display!=='block'){render(current.input,current.key,true);return}
      if(!options.length)return;
      active=(active+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;
      options.forEach((b,i)=>b.setAttribute('aria-selected',i===active?'true':'false'));
      options[active].scrollIntoView({block:'nearest'});
    }else if(event.key==='Enter'&&options.length&&popup.style.display==='block'){
      event.preventDefault();apply(options[active].dataset.value||'');
    }
  }
  fields.forEach(f=>{
    const input=controls[f.key],label=input.closest('label');
    if(!label)return;
    label.classList.add('am222-field');
    input.setAttribute('autocomplete','off');
    input.setAttribute('role','combobox');
    input.setAttribute('aria-autocomplete','list');
    input.setAttribute('aria-controls','am222-popup');
    input.setAttribute('aria-expanded','false');
    const button=document.createElement('button');
    button.type='button';button.className='am222-open';
    button.textContent='▾';button.setAttribute('aria-label','Open '+f.title+' choices');
    label.appendChild(button);
    button.addEventListener('click',()=>{input.focus();render(input,f.key,true)});
    input.addEventListener('focus',()=>render(input,f.key,true));
    input.addEventListener('input',()=>render(input,f.key,false));
    input.addEventListener('keydown',keydown);
    // Manual typing after explicit focus also invalidates downstream selections.
    let lastValue=normalize(input.value);
    input.addEventListener('change',()=>{
      const next=normalize(input.value);
      if(lastValue!==next){
        if(f.key==='make'){controls.model.value='';controls.variant.value='';}
        if(f.key==='model')controls.variant.value='';
        lastValue=next;
      }
    });
  });
  document.addEventListener('pointerdown',event=>{
    if(current&&!popup.contains(event.target)&&event.target!==current.input
       &&!event.target.closest('.am222-open'))close();
  });
  document.addEventListener('keydown',event=>{if(event.key==='Escape')close()});
  window.addEventListener('resize',()=>{if(current)position()});
  window.addEventListener('scroll',()=>{if(current)position()},true);
  // Original New/Edit controller is unchanged. When editing a saved row,
  // programmatic make/model/variant values remain intact and scoped options
  // are calculated when each input is opened.
  console.info('AM222_VEHICLE_CASCADE_SAP loaded');
})();
</script>
