/* AM235 compact single searchable Vendor Master field.
 * Keeps the original vendor_id native select for all posting,
 * including AM226 multi-bill vendor selection and AM230 source-key autofill.
 * UI-only: no financial posting or ledger changes.
 */
(function(){
'use strict';
function init(){
 const form=document.getElementById('payment-form');
 const slot=document.getElementById('am230-vendor-slot');
 const vendor=document.getElementById('payment-vendor');
 const category=document.getElementById('am230-payee-type');
 if(!form||!slot||!vendor||!category)return;
 const search=slot.querySelector('input[aria-label="Filter Vendor Master"]');
 const label=vendor.closest('label');
 if(!search||!label||!label.contains(search))return;
 if(document.getElementById('am235-vendor-options'))return;

 const items=Array.from(vendor.options)
   .filter(o=>o.value&&o.value.trim())
   .map(o=>({value:String(o.value),label:(o.textContent||'').trim(),element:o.cloneNode(true)}));
 // The first option, "No Vendor / Other Payee", remains a blank backend
 // sentinel but is not a selectable Vendor within the UI.
 const originals=Array.from(vendor.options).map(o=>o.cloneNode(true));
 const wrapper=document.createElement('div');
 wrapper.id='am235-combobox';wrapper.className='am235-combobox';
 const list=document.createElement('div');
 list.id='am235-vendor-options';list.className='am235-vendor-options';
 list.setAttribute('role','listbox');list.hidden=true;
 wrapper.appendChild(search);wrapper.appendChild(list);
 label.insertBefore(wrapper,vendor);
 const textNode=Array.from(label.childNodes).find(n=>n.nodeType===3&&n.nodeValue.trim());
 if(textNode)textNode.nodeValue='Vendor Name *';
 else label.insertBefore(document.createTextNode('Vendor Name *'),wrapper);
 search.placeholder='Type vendor name or code, then select';
 search.setAttribute('aria-label','Search and select Vendor Master');
 search.setAttribute('role','combobox');
 search.setAttribute('aria-autocomplete','list');
 search.setAttribute('aria-controls','am235-vendor-options');
 search.setAttribute('aria-expanded','false');
 search.setAttribute('autocomplete','off');
 vendor.classList.add('am235-source-select');
 form.classList.add('am235-compact-payee');
 let visible=[];let focusAt=-1;

 function isVendor(){return category.value==='VENDOR';}
 function byId(id){return items.find(x=>x.value===String(id));}
 function normal(value){return String(value||'').trim().toLocaleLowerCase();}
 function hide(){
   list.hidden=true;search.setAttribute('aria-expanded','false');
   focusAt=-1;search.removeAttribute('aria-activedescendant');
 }
 function choose(item){
   if(!item)return;
   // AM230 filtered the original select's <option> children while typing.
   // Restore full genuine options before preserving the original vendor_id.
   vendor.replaceChildren(...originals.map(o=>o.cloneNode(true)));
   vendor.value=item.value;
   search.value=item.label;
   vendor.dispatchEvent(new Event('change',{bubbles:true}));
   search.dispatchEvent(new Event('am235-selected',{bubbles:true}));
   hide();
 }
 function render(){
   if(!isVendor()){hide();return;}
   let q=normal(search.value);
   visible=items.filter(item=>!q||normal(item.label).includes(q)).slice(0,40);
   list.replaceChildren();
   focusAt=-1;
   if(!visible.length){
     let item=document.createElement('div');
     item.className='am235-no-match';
     item.textContent='No matching Vendor found in Master';
     list.appendChild(item);
   }else{
     visible.forEach((item,index)=>{
       let node=document.createElement('button');
       node.type='button';node.className='am235-option';
       node.setAttribute('role','option');
       node.id='am235-vendor-option-'+index;
       node.setAttribute('aria-selected',item.value===vendor.value?'true':'false');
       node.textContent=item.label;
       node.addEventListener('pointerdown',event=>event.preventDefault());
       node.addEventListener('click',()=>choose(item));
       list.appendChild(node);
     });
   }
   list.hidden=false;search.setAttribute('aria-expanded','true');
 }
 function highlight(index){
   if(!visible.length)return;
   focusAt=(index+visible.length)%visible.length;
   Array.from(list.querySelectorAll('.am235-option')).forEach((node,i)=>{
     node.classList.toggle('am235-focused',i===focusAt);
     if(i===focusAt){
       search.setAttribute('aria-activedescendant',node.id);
       node.scrollIntoView({block:'nearest'});
     }
   });
 }
 function synchronize(){
   const active=byId(vendor.value);
   if(active&&document.activeElement!==search){
     search.value=active.label;
   }else if(!active&&document.activeElement!==search){
     search.value='';
   }
 }
 search.addEventListener('focus',render);
 search.addEventListener('click',render);
 search.addEventListener('input',()=>{
   const active=byId(vendor.value);
   if(!active||normal(active.label)!==normal(search.value)){
     // Don't silently pay the previously selected Vendor after search text changes.
     if(vendor.value){
       vendor.value='';
       vendor.dispatchEvent(new Event('change',{bubbles:true}));
     }
   }
   render();
 });
 search.addEventListener('keydown',event=>{
   if(event.key==='Escape'){hide();return;}
   if(event.key==='ArrowDown'){
     event.preventDefault();
     if(list.hidden)render();
     highlight(focusAt+1);
   }
   if(event.key==='ArrowUp'){
     event.preventDefault();
     if(list.hidden)render();
     highlight(focusAt<0?visible.length-1:focusAt-1);
   }
   if(event.key==='Enter'&&!list.hidden&&visible.length){
     event.preventDefault();
     choose(visible[focusAt>=0?focusAt:0]);
   }
 });
 document.addEventListener('pointerdown',event=>{
   if(!wrapper.contains(event.target))hide();
 });
 // When AM230 automatically detects the Vendor from an outstanding Bill
 // (source-key), mirror that selected ID back into this one visual input.
 vendor.addEventListener('change',synchronize);
 const sourceKey=document.getElementById('source-key');
 if(sourceKey)sourceKey.addEventListener('change',()=>{
   const active=byId(vendor.value);
   search.value=active?active.label:'';
   hide();
 });
 category.addEventListener('change',()=>{
   hide();
   if(isVendor())synchronize();
 });
 // Fail closed: any typed but unselected vendor must never be submitted
 // via a stale previous select value. Existing validation remains intact.
 form.addEventListener('submit',event=>{
   if(!isVendor())return;
   if(search.value.trim()&&!byId(vendor.value)){
     event.preventDefault();event.stopImmediatePropagation();
     search.setCustomValidity('Select a vendor from the matching list.');
     search.reportValidity();
     search.setCustomValidity('');
     render();search.focus();
   }
 },true);
 synchronize();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();