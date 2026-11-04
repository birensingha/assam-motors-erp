  /* AM234_PURCHASE_JC_PICKER_V2
     Uses authoritative existing Laravel-rendered job option IDs.
     No datalist, no synthetic job IDs, no automatic selection. */
  function wireJobFilter(row) {
    const input = row.querySelector('.job-filter');
    const select = row.querySelector('.job-select');
    const allocation = row.querySelector('.allocation');
    const host = row.querySelector('.job-filter-wrap');
    if (!input || !select || !allocation || !host || host.dataset.am234Ready === '1') return;
    host.dataset.am234Ready = '1';
    input.removeAttribute('list');
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-label', 'Search Job Card, Vehicle Number or Customer');
    input.placeholder = 'Search JC / Vehicle / Customer...';
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.style.setProperty('display', 'none', 'important');
    const jobs = Array.from(select.options).filter(o => o.value.trim() !== '')
      .map(o => ({id: String(o.value), label: (o.textContent || '').trim()}));
    const compact = s => String(s || '').normalize('NFKC')
      .toLocaleUpperCase('en-IN').replace(/[^A-Z0-9]/g, '');
    const match = (label, query) => !query || compact(label).includes(compact(query));
    const menu = document.createElement('div');
    menu.className = 'am234-jc-menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    document.body.appendChild(menu);
    let active = 0;
    let visible = [];
    let selectedLabel = '';
    function close() {
      menu.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
    function position() {
      if (menu.hidden) return;
      const r = input.getBoundingClientRect();
      if (!input.isConnected || r.width === 0) { close(); return; }
      const roomBelow = window.innerHeight - r.bottom;
      const showAbove = roomBelow < 165 && r.top > roomBelow;
      const maxHeight = Math.min(240, Math.max(95, showAbove ? r.top - 12 : roomBelow - 10));
      menu.style.position = 'fixed';
      menu.style.left = Math.max(4, r.left) + 'px';
      menu.style.width = Math.max(r.width, 280) + 'px';
      menu.style.maxHeight = maxHeight + 'px';
      menu.style.top = (showAbove ? Math.max(4, r.top - maxHeight - 4) : r.bottom + 3) + 'px';
    }
    function choose(item) {
      select.value = item.id;
      if (select.value !== item.id) { input.setCustomValidity('Invalid Job Card'); return; }
      input.value = item.label;
      selectedLabel = item.label;
      input.dataset.selectedLabel = item.label;
      input.setCustomValidity('');
      input.classList.remove('invalid');
      close();
      select.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function show() {
      if (input.disabled || allocation.value !== 'JOB') { close(); return; }
      const query = input.value === selectedLabel ? '' : input.value;
      visible = jobs.filter(item => match(item.label, query)).slice(0, 60);
      menu.replaceChildren();
      active = 0;
      if (!visible.length) {
        const empty = document.createElement('div');
        empty.className = 'am234-jc-empty';
        empty.textContent = jobs.length ? 'No matching Job Card / Vehicle' : 'Job Card list unavailable';
        menu.appendChild(empty);
      }
      visible.forEach((item, idx) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'am234-jc-option';
        button.setAttribute('role','option');
        button.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
        button.textContent = item.label;
        button.addEventListener('pointerdown', e => { e.preventDefault(); choose(item); });
        button.addEventListener('click', () => choose(item));
        menu.appendChild(button);
      });
      menu.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      position();
    }
    function refreshActive() {
      const buttons = Array.from(menu.querySelectorAll('.am234-jc-option'));
      buttons.forEach((button, idx) => {
        button.classList.toggle('active',idx === active);
        button.setAttribute('aria-selected', idx === active ? 'true' : 'false');
      });
      if (buttons[active]) buttons[active].scrollIntoView({block:'nearest'});
    }
    function sync() {
      input.disabled = allocation.value !== 'JOB';
      select.disabled = allocation.value !== 'JOB';
      if (allocation.value !== 'JOB') {
        select.value = '';
        input.value = '';
        selectedLabel = '';
        input.dataset.selectedLabel = '';
        input.setCustomValidity('');
        close();
        return;
      }
      const item = jobs.find(job => job.id === select.value);
      if (item) {
        input.value = item.label;
        selectedLabel = item.label;
        input.dataset.selectedLabel = item.label;
      }
    }
    input.addEventListener('focus', show);
    input.addEventListener('click', show);
    input.addEventListener('input', () => {
      if (input.value !== selectedLabel) {
        select.value = '';
        selectedLabel = '';
        input.dataset.selectedLabel = '';
      }
      input.setCustomValidity('');
      input.classList.remove('invalid');
      show();
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (menu.hidden) show();
        if (visible.length) {
          active = (active + (e.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
          refreshActive();
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (menu.hidden) show();
        if (visible[active]) choose(visible[active]);
        else { input.classList.add('invalid'); input.setCustomValidity('Select an existing Job Card'); }
      }
    });
    input.addEventListener('blur', () => {
      if (allocation.value === 'JOB' && input.value.trim() && !select.value)
        input.classList.add('invalid');
      window.setTimeout(close, 120);
    });
    select.addEventListener('change', sync);
    allocation.addEventListener('change', () => {
      sync();
      if (allocation.value === 'JOB') window.setTimeout(() => {input.focus(); show();}, 0);
    });
    document.addEventListener('pointerdown', e => {
      if (!host.contains(e.target) && !menu.contains(e.target)) close();
      if (!input.isConnected) menu.remove();
    });
    window.addEventListener('resize', position);
    document.addEventListener('scroll', position, true);
    sync();
  }
