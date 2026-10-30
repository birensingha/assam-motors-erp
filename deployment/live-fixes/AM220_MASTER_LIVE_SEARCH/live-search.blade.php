
{{-- AM220_MASTER_LIVE_SEARCH: live server-side matching on existing Parts/ROT index GET endpoint. --}}
<style>
.am220-search-status{display:flex;align-items:center;gap:7px;margin:0 0 8px 0;padding:0 3px;
  min-height:19px;font-size:11px;color:#526f87}
.am220-search-status[data-loading="1"]{color:#125b99}
.am220-search-status[data-error="1"]{color:#a34a3c}
.am220-search-status::before{content:"";display:inline-block;width:6px;height:6px;border-radius:50%;
  background:#93acc2;flex-shrink:0}
.am220-search-status[data-loading="1"]::before{background:#2778c2;animation:am220pulse .8s infinite alternate}
.am220-search-status[data-error="1"]::before{background:#bf554b}
@keyframes am220pulse{to{opacity:.3}}
</style>
<script>
(function(){
  'use strict';
  // Keep native GET search and Search button as no-JS / connection fallback.
  const marker='AM220_MASTER_LIVE_SEARCH';
  const setup=function(){
    const form=document.querySelector('form.filterbar.pro-filter[method="get"]');
    const input=form&&form.querySelector('input[name="q"]');
    const table=document.querySelector('.card.table-card');
    if(!form||!input||!table||input.dataset.am220Bound==='1')return;
    input.dataset.am220Bound='1';
    input.setAttribute('autocomplete','off');
    input.setAttribute('aria-label','Live search across all master records');
    const status=document.createElement('div');
    status.className='am220-search-status';
    status.setAttribute('role','status');
    status.setAttribute('aria-live','polite');
    status.textContent='Type Part / ROT name or code to filter results automatically';
    form.insertAdjacentElement('afterend',status);
    let timer=null, sequence=0, controller=null;
    const records=html=>Array.from(html.querySelectorAll('tbody tr')).filter(
      r=>!r.classList.contains('inline-edit-row') && !r.querySelector('.empty')
    ).length;
    const msg=(text,loading,error)=>{
      status.textContent=text;
      status.dataset.loading=loading?'1':'0';
      status.dataset.error=error?'1':'0';
      form.setAttribute('aria-busy',loading?'true':'false');
    };
    function request(){
      const value=input.value.trim();
      const mySequence=++sequence;
      if(controller)controller.abort();
      controller=new AbortController();
      const url=new URL(form.getAttribute('action')||location.pathname,location.href);
      const original=new URL(location.href);
      original.searchParams.forEach((v,k)=>{
        if(k!=='page'&&k!=='q')url.searchParams.set(k,v);
      });
      if(value)url.searchParams.set('q',value);
      else url.searchParams.delete('q');
      url.searchParams.delete('page');
      msg(value?'Matching “'+value+'”...':'Showing all master records...',true,false);
      fetch(url.toString(),{
        method:'GET',
        credentials:'same-origin',
        cache:'no-store',
        headers:{'Accept':'text/html','X-Requested-With':'AM220-Live-Master-Search'},
        signal:controller.signal
      }).then(async(response)=>{
        if(!response.ok || response.redirected && !response.url.startsWith(location.origin)){
          throw new Error('HTTP '+response.status);
        }
        const html=await response.text();
        if(mySequence!==sequence)return;
        const doc=new DOMParser().parseFromString(html,'text/html');
        const match=doc.querySelector('.card.table-card');
        if(!match||!match.querySelector('table.data-table tbody')){
          throw new Error('Non-master response (session or route)');
        }
        // Do not remove unsaved Edit form that AM217 temporarily moves into a modal.
        if(document.body.classList.contains('am217-edit-open')){
          msg('Close the Edit popup to update search results',false,false);
          return;
        }
        table.innerHTML=match.innerHTML;
        // Original AM217 amToggle wrapper remains global; AM214 rebinds dropdowns.
        document.dispatchEvent(new Event('am220:rows-updated'));
        const count=records(match);
        msg(value?(count===0?'No matching master records':'Showing '+count+' matching records'):'Showing '+count+' master records',false,false);
        const historyUrl=new URL(location.href);
        historyUrl.searchParams.delete('page');
        if(value)historyUrl.searchParams.set('q',value);
        else historyUrl.searchParams.delete('q');
        history.replaceState(history.state,'',historyUrl.pathname+historyUrl.search+historyUrl.hash);
      }).catch((error)=>{
        if(error.name==='AbortError'||mySequence!==sequence)return;
        msg('Live Search connection problem — press Search or Enter to retry',false,true);
      });
    }
    function schedule(){
      if(timer)clearTimeout(timer);
      ++sequence;
      if(controller)controller.abort();
      msg('Searching as you type...',true,false);
      timer=setTimeout(request,280);
    }
    input.addEventListener('input',schedule);
    input.addEventListener('search',schedule);
    form.addEventListener('submit',function(event){
      if(event.submitter&&event.submitter.getAttribute('data-am220-original-submit')==='1')return;
      event.preventDefault();
      if(timer)clearTimeout(timer);
      request();
    });
    const reset=form.querySelector('a[href]');
    if(reset){
      reset.addEventListener('click',function(event){
        event.preventDefault();
        input.value='';
        schedule();
        input.focus();
      });
    }
    window.addEventListener('popstate',function(){
      input.value=new URL(location.href).searchParams.get('q')||'';
      schedule();
    });
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup,{once:true});
  else setup();
})();
</script>
