
{{-- AM214_NATIVE_MASTER_MODAL_SELECT: native ERP only; no old API, no master data writes. --}}
@php
    $am214Choices=[];
    $am214Pairs=[];
    $am214Table='__AM214_MASTER_TABLE__';
    $am214Fields=($am214Table==='labour_master')
      ? ['category']
      : ['category','sub_category','unit','vehicle_make','vehicle_model','gst_percent'];
    if(!\Illuminate\Support\Facades\Schema::hasTable($am214Table))$am214Table=null;
    foreach($am214Fields as $am214Field){
        $am214Choices[$am214Field]=[];
        if($am214Table && \Illuminate\Support\Facades\Schema::hasColumn($am214Table,$am214Field)){
            $am214Choices[$am214Field]=\Illuminate\Support\Facades\DB::table($am214Table)
                ->whereNotNull($am214Field)->where($am214Field,'<>','')
                ->distinct()->orderBy($am214Field)->limit(800)->pluck($am214Field)->all();
        }
    }
    if(\Illuminate\Support\Facades\Schema::hasTable('vehicle_makes')){
        foreach(\Illuminate\Support\Facades\DB::table('vehicle_makes')->where('active',1)->orderBy('name')->limit(800)->pluck('name') as $am214Make){
            $am214Choices['vehicle_make'][]=$am214Make;
        }
    }
    if(\Illuminate\Support\Facades\Schema::hasTable('vehicle_models') && \Illuminate\Support\Facades\Schema::hasTable('vehicle_makes')){
        $am214Pairs=\Illuminate\Support\Facades\DB::table('vehicle_models as m')
           ->join('vehicle_makes as k','k.id','=','m.make_id')
           ->where('m.active',1)->where('k.active',1)
           ->orderBy('k.name')->orderBy('m.name')->limit(1800)
           ->get(['k.name as make','m.name as model'])->map(fn($r)=>['make'=>$r->make,'model'=>$r->model])->all();
    }
    if(\Illuminate\Support\Facades\Schema::hasTable('am_vehicle_catalog_master')){
        foreach(\Illuminate\Support\Facades\DB::table('am_vehicle_catalog_master')
           ->whereNotNull('make_name')->whereNotNull('model_name')
           ->distinct()->limit(1500)->get(['make_name','model_name']) as $am214Vehicle){
           $am214Pairs[]=['make'=>$am214Vehicle->make_name,'model'=>$am214Vehicle->model_name];
           $am214Choices['vehicle_make'][]=$am214Vehicle->make_name;
        }
    }
    $am214Choices['gst_percent']=array_merge(['0','5','12','18','28'],$am214Choices['gst_percent']??[]);
@endphp
<style>
  /* AM214 corporate modal forms; original POST actions and validation untouched. */
  body.am214-modal-open{overflow:hidden}
  body.am214-modal-open::before{content:"";position:fixed;inset:0;background:rgba(8,25,46,.56);z-index:1190;pointer-events:auto}
  body.am214-modal-open #partCreatePanel[open],
  body.am214-modal-open #newRotPanel.am214-modal-panel{
    display:block!important;position:fixed!important;inset:50% auto auto 50%!important;
    transform:translate(-50%,-50%)!important;z-index:1195!important;
    width:min(980px,calc(100vw - 28px))!important;max-height:min(89dvh,900px)!important;
    overflow-y:auto!important;overscroll-behavior:contain!important;
    background:#fff!important;border:1px solid #b9cde0!important;
    border-radius:13px!important;box-shadow:0 24px 72px rgba(0,23,60,.3)!important;
    padding:0 19px 22px!important;color:#183a5a!important
  }
  body.am214-modal-open #partCreatePanel>summary,
  body.am214-modal-open #newRotPanel .section-head{
    display:flex!important;align-items:center;min-height:57px!important;
    margin:0 -19px 16px!important;padding:12px 19px!important;
    position:sticky;top:0;z-index:1;background:#f4f8fc!important;
    border-bottom:1px solid #d6e3ef!important;font-size:15px;font-weight:800
  }
  #partCreatePanel .am214-close,#newRotPanel .am214-close{margin-left:auto;cursor:pointer;
    padding:7px 12px;background:#fff;border:1px solid #bbccdd;border-radius:7px;
    font:inherit;font-size:12px;color:#234b6d;white-space:nowrap}
  body.am214-modal-open #partCreatePanel form,
  body.am214-modal-open #newRotPanel form{display:grid!important;
    grid-template-columns:repeat(2,minmax(0,1fr))!important;
    gap:12px 16px!important;align-items:start!important;padding:0!important;
    box-shadow:none!important;border:0!important}
  body.am214-modal-open #partCreatePanel form label,
  body.am214-modal-open #newRotPanel form label{min-width:0!important}
  body.am214-modal-open #partCreatePanel form .span-2,
  body.am214-modal-open #newRotPanel form .span-2,
  body.am214-modal-open #newRotPanel form .am212-work-procedure{grid-column:span 2}
  .am214-select-field{position:relative!important}
  .am214-select-field input{padding-right:34px!important;box-sizing:border-box!important;width:100%}
  .am214-select-arrow{position:absolute;right:5px;bottom:4px;min-width:28px;height:29px;
    border:none;background:transparent;cursor:pointer;color:#315d7c;font-size:13px;
    display:flex;align-items:center;justify-content:center;border-radius:5px}
  .am214-select-arrow:hover,.am214-select-arrow:focus-visible{background:#e5f0fa;outline:1px solid #9fc1df}
  .am214-option-list{position:fixed;z-index:1400;display:none;overflow-y:auto;
    max-height:235px;padding:5px;border:1px solid #9dbbd5;border-radius:8px;
    background:white;box-shadow:0 12px 32px rgba(23,54,88,.24);min-width:120px}
  .am214-option-list button{width:100%;display:block;border:0;border-radius:4px;
    text-align:left;background:white;color:#1f405d;font-size:13px;line-height:1.4;
    padding:9px 10px;cursor:pointer;white-space:normal;word-break:break-word}
  .am214-option-list button:hover,.am214-option-list button[aria-selected="true"]{
    background:#dfedfa;color:#10365a}
  .am214-option-list .am214-empty{padding:10px;font-size:12px;color:#6b8195}
  .am214-helperline{font-size:11px;color:#5a738b;margin:7px 0}
  @media(max-width:680px){
    body.am214-modal-open #partCreatePanel form,
    body.am214-modal-open #newRotPanel form{grid-template-columns:1fr!important}
    body.am214-modal-open #partCreatePanel form .span-2,
    body.am214-modal-open #newRotPanel form .span-2,
    body.am214-modal-open #newRotPanel form .am212-work-procedure{grid-column:span 1}
    body.am214-modal-open #partCreatePanel[open],
    body.am214-modal-open #newRotPanel.am214-modal-panel{padding:0 12px 15px!important}
  }
</style>
<script>
(function(){
  "use strict";
  var AM214_NAME=__AM214_PAGE__;
  var seed=__AM214_LEGACY__;
  var stored={{ \Illuminate\Support\Js::from($am214Choices) }};
  var pairs={{ \Illuminate\Support\Js::from($am214Pairs) }};
  if(!Array.isArray(pairs))pairs=[];
  var relevant=AM214_NAME==="parts"
    ?["category","sub_category","unit","vehicle_make","vehicle_model","gst_percent"]
    :["category"];
  var values={};
  relevant.forEach(function(k){
    var raw=[].concat(seed[k]||[],stored[k]||[]);
    values[k]=Array.from(new Set(raw.map(function(v){return String(v).trim()}).filter(Boolean)))
      .sort(function(a,b){return a.localeCompare(b,undefined,{numeric:true,sensitivity:"base"})});
  });
  var dropdown=document.createElement("div");
  dropdown.className="am214-option-list";
  dropdown.setAttribute("role","listbox");
  dropdown.setAttribute("aria-label","Matching master field values");
  document.body.appendChild(dropdown);
  var current=null,activeIndex=0;
  function optionsFor(input){
    var opts=values[input.name]||[];
    if(input.name!=="vehicle_model")return opts;
    var form=input.form;
    var make=form&&form.querySelector('[name="vehicle_make"]');
    var selected=make?make.value.trim().toLowerCase():"";
    if(!selected)return opts;
    var linked=pairs.filter(function(x){return String(x.make||"").toLowerCase()===selected})
                    .map(function(x){return String(x.model||"")});
    return Array.from(new Set(linked.concat(opts.filter(function(m){
      return pairs.every(function(p){return String(p.model||"").toLowerCase()!==m.toLowerCase()})})
    ))).sort(function(a,b){return a.localeCompare(b)});
  }
  function hide(){dropdown.style.display="none";current=null}
  function locate(){
    if(!current)return;
    var r=current.getBoundingClientRect();
    var bottom=window.innerHeight-r.bottom;
    var height=235;
    dropdown.style.width=Math.max(150,r.width)+"px";
    dropdown.style.left=Math.max(6,Math.min(r.left,window.innerWidth-r.width-8))+"px";
    dropdown.style.top=(bottom<height && r.top>height ? Math.max(6,r.top-height-6):r.bottom+3)+"px";
  }
  function selected(button){
    if(!current)return;
    var input=current;
    input.value=button.getAttribute("data-value")||"";
    input.dispatchEvent(new Event("input",{bubbles:true}));
    input.dispatchEvent(new Event("change",{bubbles:true}));
    hide();input.focus();
  }
  function render(input,showAll){
    current=input;activeIndex=0;
    dropdown.innerHTML="";
    var needle=(showAll?"":input.value).trim().toLowerCase();
    var matches=optionsFor(input).filter(function(v){return v.toLowerCase().includes(needle)}).slice(0,65);
    if(!matches.length){
      var empty=document.createElement("div");empty.className="am214-empty";
      empty.textContent="No matching option. You can enter a new value.";
      dropdown.appendChild(empty);
    }
    matches.forEach(function(value,index){
      var b=document.createElement("button");
      b.type="button";b.setAttribute("role","option");
      b.setAttribute("data-value",value);b.textContent=value;
      b.setAttribute("aria-selected",index===0?"true":"false");
      b.addEventListener("pointerdown",function(e){e.preventDefault();selected(b)});
      dropdown.appendChild(b);
    });
    locate();dropdown.style.display="block";
  }
  function updateActive(index){
    var buttons=Array.from(dropdown.querySelectorAll("button[role=option]"));
    if(!buttons.length)return;
    activeIndex=(index+buttons.length)%buttons.length;
    buttons.forEach(function(b,i){b.setAttribute("aria-selected",i===activeIndex?"true":"false")});
    buttons[activeIndex].scrollIntoView({block:"nearest"});
  }
  function attach(input){
    if(input.dataset.am214Bound)return;
    input.dataset.am214Bound="1";
    // Suppress older AM213 native datalist so it does not overlap this menu.
    input.removeAttribute("list");
    input.setAttribute("autocomplete","off");
    input.setAttribute("aria-autocomplete","list");
    var label=input.closest("label");
    if(!label)return;
    label.classList.add("am214-select-field");
    var arrow=document.createElement("button");
    arrow.type="button";arrow.className="am214-select-arrow";arrow.textContent="▾";
    arrow.setAttribute("aria-label","Open "+input.name.replace(/_/g," ")+" dropdown");
    label.appendChild(arrow);
    arrow.addEventListener("click",function(){input.focus();render(input,true)});
    input.addEventListener("focus",function(){render(input,true)});
    input.addEventListener("click",function(){render(input,true)});
    input.addEventListener("input",function(){render(input,false)});
    input.addEventListener("keydown",function(e){
      if(e.key==="Escape"){hide();return}
      if(e.key==="ArrowDown" || e.key==="ArrowUp"){
        e.preventDefault();
        if(dropdown.style.display!=="block")render(input,true);
        else updateActive(activeIndex+(e.key==="ArrowDown"?1:-1));
      } else if(e.key==="Enter"&&dropdown.style.display==="block"){
        var selectedOption=dropdown.querySelector('button[aria-selected="true"]');
        if(selectedOption){e.preventDefault();selected(selectedOption)}
      }
    });
    if(input.name==="vehicle_make"){
      input.addEventListener("change",function(){
        var model=input.form&&input.form.querySelector('[name="vehicle_model"]');
        if(!model||!model.value.trim())return;
        var available=optionsFor(model);
        var belongs=available.some(function(v){return v.toLowerCase()===model.value.trim().toLowerCase()});
        if(!belongs){model.value="";model.dispatchEvent(new Event("change",{bubbles:true}))}
      });
    }
  }
  function init(){
    document.querySelectorAll('form input[name]').forEach(function(input){
      if(relevant.indexOf(input.name)>=0)attach(input);
    });
  }
  document.addEventListener("pointerdown",function(e){
    if(!dropdown.contains(e.target)&&!(current&&e.target===current)
       &&!e.target.closest(".am214-select-arrow"))hide();
  });
  window.addEventListener("resize",function(){if(current)locate()});
  window.addEventListener("scroll",function(){if(current)locate()},true);
  function setupModal(){
    var panel=AM214_NAME==="parts"?document.getElementById("partCreatePanel"):document.getElementById("newRotPanel");
    if(!panel)return;
    function close(){
      if(AM214_NAME==="parts")panel.open=false;
      else {panel.style.display="none";panel.classList.remove("am214-modal-panel")}
      document.body.classList.remove("am214-modal-open");hide();
    }
    var closeButton=document.createElement("button");
    closeButton.type="button";closeButton.className="am214-close";
    closeButton.textContent="✕ Close";closeButton.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();close()});
    if(AM214_NAME==="parts"){
      var summary=panel.querySelector("summary");
      if(summary)summary.appendChild(closeButton);
      panel.addEventListener("toggle",function(){
        document.body.classList.toggle("am214-modal-open",panel.open);
      });
    }else{
      var head=panel.querySelector(".section-head");
      if(head)head.appendChild(closeButton);
      var observer=new MutationObserver(function(){
        var visible=panel.style.display!=="none" && getComputedStyle(panel).display!=="none";
        document.body.classList.toggle("am214-modal-open",visible);
        panel.classList.toggle("am214-modal-panel",visible);
      });
      observer.observe(panel,{attributes:true,attributeFilter:["style","class"]});
    }
    document.addEventListener("keydown",function(e){if(e.key==="Escape")close()});
    document.addEventListener("pointerdown",function(e){
      if(!document.body.classList.contains("am214-modal-open"))return;
      if(!panel.contains(e.target) && !dropdown.contains(e.target)) {
        // Do not close on clicks elsewhere, preventing accidental form data loss.
        hide();
      }
    });
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init();setupModal()});
  else {init();setupModal()}
})();
</script>
