
{{-- AM217_EDIT_MASTER_MODALS: preserves existing edit form & POST route, changes only presentation. --}}
<style>
  body.am217-edit-open{overflow:hidden!important}
  #am217-edit-shade{position:fixed;inset:0;z-index:1300;background:rgba(5,22,48,.58);display:none}
  #am217-edit-shade[aria-hidden="false"]{display:block}
  #am217-edit-popup{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:1350;
    width:min(890px,calc(100vw - 28px));max-height:90dvh;display:none;flex-direction:column;
    overflow:hidden;border:1px solid #b4c9df;border-radius:14px;background:white;
    box-shadow:0 22px 70px rgba(5,26,54,.38);color:#173955}
  #am217-edit-popup[aria-hidden="false"]{display:flex}
  #am217-edit-popup .am217-titlebar{display:flex;align-items:center;gap:14px;
    padding:17px 19px;background:linear-gradient(100deg,#122d50,#213e87);color:white;flex-shrink:0}
  #am217-edit-popup .am217-titlebar h2{margin:0;color:white!important;font-size:17px;font-weight:850}
  #am217-edit-popup .am217-titlebar p{margin:4px 0 0;color:#dbe9fc;font-size:11px}
  #am217-edit-popup .am217-close{margin-left:auto;border:1px solid #7d9bc5;
    background:rgba(255,255,255,.12);border-radius:9px;color:white;font-size:12px;
    padding:9px 12px;cursor:pointer;white-space:nowrap}
  #am217-edit-popup .am217-close:focus-visible{outline:2px solid white;outline-offset:2px}
  #am217-edit-popup .am217-content{overflow-y:auto;overscroll-behavior:contain;
    padding:19px 20px 22px;min-height:0}
  #am217-edit-popup form.inline-edit-card{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;
    align-items:start;gap:13px 16px;box-sizing:border-box;
    background:#fff!important;border:0!important;box-shadow:none!important;
    margin:0!important;padding:0!important;max-width:none!important;position:static!important}
  #am217-edit-popup form.inline-edit-card label{display:flex;flex-direction:column;min-width:0;
    gap:5px;font-size:11px;font-weight:750;color:#3d5570}
  #am217-edit-popup form.inline-edit-card input,
  #am217-edit-popup form.inline-edit-card select,
  #am217-edit-popup form.inline-edit-card textarea{
    box-sizing:border-box;min-width:0;width:100%;max-width:100%;
    border:1px solid #c6d6e6;border-radius:8px;color:#183851;
    background:white;font-size:13px;padding:8px 10px;min-height:36px}
  #am217-edit-popup form.inline-edit-card label.span-2,
  #am217-edit-popup form.inline-edit-card .span-4,
  #am217-edit-popup form.inline-edit-card label.am212-work-procedure{grid-column:1/-1!important}
  #am217-edit-popup form.inline-edit-card .actions{display:flex!important;
    flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:9px;
    padding:14px 0 0;border-top:1px solid #e5edf5}
  #am217-edit-popup form.inline-edit-card .actions button{min-height:35px}
  @media(max-width:620px){
    #am217-edit-popup{width:calc(100vw - 18px);max-height:94dvh}
    #am217-edit-popup .am217-content{padding:12px}
    #am217-edit-popup form.inline-edit-card{grid-template-columns:1fr!important}
    #am217-edit-popup form.inline-edit-card label.span-2,
    #am217-edit-popup form.inline-edit-card .span-4,
    #am217-edit-popup form.inline-edit-card label.am212-work-procedure{grid-column:1!important}
  }
</style>
<script>
(function(){
  "use strict";
  var page=__AM217_PAGE__;
  var prefix=page==="parts"?"partEdit":"rotEdit";
  var previous=window.amToggle;
  if(typeof previous!=="function"){console.warn("AM217 Edit controls unavailable");return;}
  var overlay=document.createElement("div");
  overlay.id="am217-edit-shade";overlay.setAttribute("aria-hidden","true");
  var dialog=document.createElement("section");
  dialog.id="am217-edit-popup";dialog.setAttribute("role","dialog");
  dialog.setAttribute("aria-modal","true");dialog.setAttribute("aria-hidden","true");
  dialog.setAttribute("aria-labelledby","am217-edit-title");
  dialog.innerHTML='<div class="am217-titlebar"><div><h2 id="am217-edit-title"></h2>'
    +'<p>Edit existing master record · Save changes to the same record</p></div>'
    +'<button type="button" class="am217-close" aria-label="Close Edit popup">✕ Close</button></div>'
    +'<div class="am217-content"></div>';
  document.body.appendChild(overlay);
  document.body.appendChild(dialog);
  var heading=dialog.querySelector("#am217-edit-title");
  var body=dialog.querySelector(".am217-content");
  var active=null;
  function restore(){
    if(!active)return;
    var old=active;
    active=null;
    var parent=old.parent;
    if(parent&&parent.isConnected){
      if(old.next&&old.next.parentNode===parent)parent.insertBefore(old.form,old.next);
      else parent.appendChild(old.form);
    }
    if(old.row)old.row.style.display="none";
    dialog.setAttribute("aria-hidden","true");
    overlay.setAttribute("aria-hidden","true");
    document.body.classList.remove("am217-edit-open");
    if(document.activeElement&&dialog.contains(document.activeElement))
      dialog.querySelector(".am217-close").blur();
    if(old.trigger&&old.trigger.isConnected)old.trigger.focus({preventScroll:true});
  }
  function open(id){
    var row=document.getElementById(id);
    if(!row||row.tagName!=="TR"||!row.classList.contains("inline-edit-row"))return false;
    var form=row.querySelector("form.inline-edit-card");
    if(!form||!form.querySelector('input[name="category"]'))return false;
    if(active&&active.row===row){restore();return true}
    if(active)restore();
    var trigger=document.activeElement;
    active={row:row,form:form,parent:form.parentNode,next:form.nextSibling,trigger:trigger};
    // Move the ORIGINAL POST form: no clone, no loss of saved values,
    // backend update route, CSRF or original client-side dropdown handlers.
    body.appendChild(form);
    row.style.display="none";
    heading.textContent=page==="parts"?"Edit Part":"Edit Labour / ROT";
    overlay.setAttribute("aria-hidden","false");
    dialog.setAttribute("aria-hidden","false");
    document.body.classList.add("am217-edit-open");
    dialog.querySelector(".am217-close").focus({preventScroll:true});
    return true;
  }
  // Existing table's Edit and Cancel buttons call amToggle('partEdit<ID>')
  // or amToggle('rotEdit<ID>'). Intercept ONLY those, leave New Create,
  // search, delete, master listing and unrelated toggles exactly unchanged.
  window.amToggle=function(id){
    if(typeof id==="string" && id.startsWith(prefix) && /^\d+$/.test(id.slice(prefix.length))){
      if(open(id))return;
    }
    return previous.apply(this,arguments);
  };
  overlay.addEventListener("click",function(){restore()});
  dialog.querySelector(".am217-close").addEventListener("click",function(){restore()});
  document.addEventListener("keydown",function(ev){
    if(!active)return;
    if(ev.key==="Escape"){ev.preventDefault();ev.stopImmediatePropagation();restore();return}
    if(ev.key==="Tab"){
      var focusable=Array.from(dialog.querySelectorAll(
        'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])'
      )).filter(function(el){return el.getClientRects().length});
      if(!focusable.length)return;
      var first=focusable[0],last=focusable[focusable.length-1];
      if(ev.shiftKey&&document.activeElement===first){ev.preventDefault();last.focus()}
      else if(!ev.shiftKey&&document.activeElement===last){ev.preventDefault();first.focus()}
    }
  },true);
})();
</script>
