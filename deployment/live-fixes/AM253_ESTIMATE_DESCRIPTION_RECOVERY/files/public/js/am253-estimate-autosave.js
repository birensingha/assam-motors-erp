// AM253_ESTIMATE_AUTOSAVE: per-tab 8h recovery for a failed Estimate draft.
(() => {
  'use strict';
  const form = document.getElementById('estimate-form');
  const body = document.getElementById('estimate-lines');
  const addBtn = document.getElementById('add-estimate-line');
  if (!form || !body || !addBtn) return;
  const ttl = 8 * 60 * 60 * 1000;
  const estimateId = form.querySelector('[name="estimate_id"]')?.value || '';
  const key = 'am253:estimate:' + location.pathname + ':draft:' + estimateId;
  const fields = ['estimate_date','estimate_kind','valid_until','customer_note','note','internal_note'];
  let previous = null;
  try {
    const parsed = JSON.parse(sessionStorage.getItem(key) || 'null');
    if (parsed && Date.now() - Number(parsed.when || 0) < ttl &&
        parsed.estimateId === estimateId && Array.isArray(parsed.items)) {
      previous = parsed;
    } else if (parsed) {
      sessionStorage.removeItem(key);
    }
  } catch (_) { /* session storage may be disabled */ }

  const rows = () => Array.from(body.querySelectorAll('tr'));
  const val = (tr, selector) => tr.querySelector(selector)?.value ?? '';
  function snapshot() {
    const header = {};
    for (const name of fields) header[name] = form.querySelector('[name="' + name + '"]')?.value ?? '';
    return {
      when: Date.now(), estimateId, header,
      items: rows().map(tr => ({
        item_type: val(tr,'.e-type'),
        reference_id: val(tr,'.e-ref-id'),
        reference_label: val(tr,'.e-ref-search'),
        item_code: val(tr,'.e-code'),
        hsn_sac: val(tr,'.e-hsn'),
        description: val(tr,'.e-desc'),
        qty: val(tr,'.e-qty'),
        unit: val(tr,'.e-unit'),
        rate: val(tr,'.e-rate'),
        discount_percent: val(tr,'.e-disc'),
        tax_percent: val(tr,'.e-tax'),
        line_note: val(tr,'[name$="[line_note]"]')
      }))
    };
  }
  let pending = null;
  function persist() {
    if (pending) clearTimeout(pending);
    pending = null;
    try { sessionStorage.setItem(key, JSON.stringify(snapshot())); }
    catch (_) { /* no tracking, no network, no form blocking */ }
  }
  function changed() {
    if (pending) clearTimeout(pending);
    pending = setTimeout(persist,250);
  }
  form.addEventListener('input', changed, true);
  form.addEventListener('change', changed, true);
  form.addEventListener('click', e => {
    if (e.target.closest('.e-remove,#add-estimate-line,.estimate-ref-option')) {
      setTimeout(changed,60);
    }
  }, true);
  form.addEventListener('submit', persist, true);
  window.addEventListener('pagehide', persist);

  function restore(saved) {
    if (!saved?.items?.length || saved.estimateId !== estimateId) return false;
    const items = saved.items;
    while (rows().length < items.length) addBtn.click();
    // Never drop lines that may have been restored from Laravel old input:
    // the user confirms before replacing all visible rows.
    while (rows().length > items.length && rows().length > 1) {
      rows().at(-1).querySelector('.e-remove')?.click();
    }
    rows().forEach((tr,index) => {
      const r = items[index] || {};
      const type = tr.querySelector('.e-type');
      if (type) {
        type.value = String(r.item_type || 'PART');
        type.dispatchEvent(new Event('change',{bubbles:true}));
      }
      const pairs = [
        ['.e-ref-id','reference_id'],['.e-ref-search','reference_label'],
        ['.e-code','item_code'],['.e-hsn','hsn_sac'],['.e-desc','description'],
        ['.e-qty','qty'],['.e-unit','unit'],['.e-rate','rate'],
        ['.e-disc','discount_percent'],['.e-tax','tax_percent'],
        ['[name$="[line_note]"]','line_note']
      ];
      for (const [selector,name] of pairs) {
        const input = tr.querySelector(selector);
        if (input) input.value = String(r[name] ?? '');
      }
      tr.querySelector('.e-rate')?.dispatchEvent(new Event('input',{bubbles:true}));
    });
    for (const name of fields) {
      const input=form.querySelector('[name="' + name + '"]');
      if (input && Object.prototype.hasOwnProperty.call(saved.header || {},name)) {
        input.value = String(saved.header[name] ?? '');
      }
    }
    persist();
    return true;
  }

  if (previous && previous.items.some(r=>String(r.description||'').trim())) {
    const notice=document.createElement('div');
    notice.id='am253-draft-restore';
    notice.style.cssText='background:#fff7e5;border:1px solid #d7b96f;padding:10px;border-radius:8px;margin:8px 0;color:#473820;font:13px Arial,sans-serif';
    const label=document.createElement('span');
    label.textContent='Unsaved Estimate backup found in this browser tab ('+
      previous.items.length+' lines). ';
    const btn=document.createElement('button');
    btn.type='button';btn.className='btn small';btn.textContent='Restore Previous Entered Lines';
    btn.addEventListener('click', () => {
      if (!confirm('Replace the currently visible Estimate entries with the previous tab backup?')) return;
      if (restore(previous)) {
        notice.textContent='Previous Estimate lines, descriptions, quantities, rates and notes restored. Review before saving.';
      }
    });
    notice.append(label,btn);
    const table=body.closest('.estimate-lines-area') || body.parentElement;
    table?.parentElement?.insertBefore(notice,table);
  }
})();
