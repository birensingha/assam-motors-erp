  /* AM248_PARTS_ONE_JC_PICKER: one visible search with an accessible JC list.
     Keep the native select fully populated and named for server/financial validation. */
  function wireJobFilter(row) {
    const filter = row.querySelector('.job-filter');
    const select = row.querySelector('.job-select');
    const wrap = row.querySelector('.job-filter-wrap');
    if (!filter || !select || !wrap || wrap.querySelector('.am248-job-menu')) return;
    // Never mutate the select's full option list while searching.
    const source = [...select.options].filter(o => o.value)
      .map(o => ({value:o.value, text:String(o.textContent || '').trim()}));
    const normalize = s => String(s || '').normalize('NFKC')
      .toLocaleLowerCase('en-IN').replace(/\s+/g,' ').trim();
    const compact = s => normalize(s).replace(/[^a-z0-9]/g,'');
    const menu = document.createElement('div');
    menu.className='am248-job-menu';menu.hidden=true;menu.setAttribute('role','listbox');
    const status = document.createElement('span');
    status.className='am248-sr-only';status.setAttribute('role','status');
    wrap.append(menu,status);
    filter.setAttribute('role','combobox');
    filter.setAttribute('aria-autocomplete','list');
    filter.setAttribute('aria-expanded','false');
    filter.setAttribute('aria-label','Search and select Job Card / Vehicle');
    filter.placeholder='Select JC / Vehicle (type to search)';
    let active=0;
    function close(){menu.hidden=true;filter.setAttribute('aria-expanded','false');}
    function sync(){
      const chosen=source.find(item=>item.value===select.value);
      if(chosen){
        filter.value=chosen.text;filter.dataset.selectedLabel=chosen.text;
        filter.classList.remove('invalid');
      }else{
        filter.dataset.selectedLabel='';
        if(!select.value)filter.value='';
      }
    }
    function choose(item){
      select.value=item.value;
      filter.value=item.text;
      filter.dataset.selectedLabel=item.text;
      filter.classList.remove('invalid');
      select.dispatchEvent(new Event('change',{bubbles:true}));
      close();
    }
    function results(){
      const query=normalize(filter.value),q=compact(filter.value);
      // When focus is on already-selected JC, show all choices for changing it.
      const exact=normalize(filter.value)===normalize(filter.dataset.selectedLabel||'');
      return source.filter(item=>exact||!query||normalize(item.text).includes(query) ||
        (q && compact(item.text).includes(q))).slice(0,60);
    }
    function show(){
      if(filter.disabled){close();return;}
      const matches=results();
      menu.replaceChildren();active=0;
      if(!matches.length){
        const empty=document.createElement('p');empty.className='am248-job-empty';
        empty.textContent='No matching Job Card / Vehicle';
        menu.append(empty);
      }else{
        matches.forEach((item,index)=>{
          const button=document.createElement('button');
          button.type='button';button.className='am248-job-option';
          button.setAttribute('role','option');
          button.setAttribute('aria-selected',index===0?'true':'false');
          button.textContent=item.text;
          // The click is the only selection event: pointerdown only keeps input focus.
          button.addEventListener('pointerdown',e=>e.preventDefault());
          button.addEventListener('click',()=>choose(item));
          menu.append(button);
        });
      }
      menu.hidden=false;filter.setAttribute('aria-expanded','true');
      status.textContent=matches.length+' matching Job Cards';
    }
    filter.addEventListener('focus',show);
    filter.addEventListener('click',show);
    filter.addEventListener('input',()=>{
      // A changed query never leaves behind a stale hidden job_card_id.
      if(normalize(filter.value)!==normalize(filter.dataset.selectedLabel||'')){
        select.value='';
        filter.dataset.selectedLabel='';
      }
      filter.classList.remove('invalid');
      show();
    });
    filter.addEventListener('keydown',e=>{
      if(e.key==='Escape'){e.preventDefault();close();return;}
      if(e.key==='ArrowDown'||e.key==='ArrowUp'){
        e.preventDefault();if(menu.hidden)show();
        const buttons=[...menu.querySelectorAll('button.am248-job-option')];
        if(!buttons.length)return;
        active=(active+(e.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length;
        buttons.forEach((b,i)=>{
          b.classList.toggle('active',i===active);
          b.setAttribute('aria-selected',i===active?'true':'false');
        });
        buttons[active]?.scrollIntoView({block:'nearest'});
      }
      if(e.key==='Enter'){
        e.preventDefault();
        if(menu.hidden)show();
        const buttons=menu.querySelectorAll('button.am248-job-option');
        if(buttons[active])buttons[active].click();
        else filter.classList.add('invalid');
      }
    });
    filter.addEventListener('blur',()=>{
      // Blur never implicitly picks a JC: only an explicit suggestion is valid.
      if(!select.value && filter.value.trim())filter.classList.add('invalid');
      // Delay not needed: pointerdown prevents blur during clicking suggestions.
      close();
    });
    select.addEventListener('change',sync);
    document.addEventListener('pointerdown',e=>{if(!wrap.contains(e.target))close();});
    sync();
  }
