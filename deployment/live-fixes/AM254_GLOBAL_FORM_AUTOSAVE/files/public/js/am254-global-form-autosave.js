/* AM254_GLOBAL_FORM_AUTOSAVE
 * Reusable, safe browser-local draft recovery. NEVER posts, approves, changes
 * accounts, or saves business transactions. Excludes credentials and forms
 * that merely trigger status/destructive actions.
 */
(function () {
  'use strict';
  if (window.__am254DraftModule) return;
  window.__am254DraftModule = true;
  var script = document.getElementById('am254-global-draft-script');
  var account = String(script && script.getAttribute('data-user') || '').trim();
  var guard = String(script && script.getAttribute('data-guard') || 'erp');
  if (!account || account === '0') return;
  var prefix = 'AM254:draft:v1:' + guard + ':' + account + ':';
  var maxAge = 24 * 60 * 60 * 1000;
  var maxBytes = 260000;
  var formsSeen = new WeakSet();
  var disallowAction = /(?:^|\/)(?:logout|login|sign-in|sign-out|password|forgot-password|reset-password|otp|2fa|delete|destroy|void|approve|reject|complete|cancel|reverse|reopen|start|pause|resume|check-in|check-out|qc-result|status|send-to-job-card|send|close|submit-result)(?:\/|$)/i;
  var sensitive = /(?:password|passwd|secret|token|csrf|pin(?:_code)?|otp|cvv|cvc|security_code|private_key|session_id|api_key|latitude|longitude|gps|geo(?:location)?|device_id|biometric|imei|account_number|bank_account|ifsc)/i;
  var elementSelector = 'input,textarea,select';
  var statusNode = null;
  var statusTimeout = 0;
  var activeStores = new Map();

  function storage() {
    try {
      var test = prefix + '__available';
      localStorage.setItem(test,'1');
      localStorage.removeItem(test);
      return localStorage;
    } catch (_) {
      try { return sessionStorage; } catch (_e) { return null; }
    }
  }
  var store = storage();
  if (!store) return;

  function now() { return Date.now(); }
  function textContent(x) { return String(x == null ? '' : x); }
  function skipControl(el) {
    if (!el || !el.name || el.disabled || el.closest('[data-no-autosave]')) return true;
    if (!/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) return true;
    var type = String(el.type || '').toLowerCase();
    if (['password','file','button','submit','reset','image','color'].indexOf(type) >= 0) return true;
    if (sensitive.test(el.name) || sensitive.test(el.id || '')) return true;
    if (el.hasAttribute('data-sensitive') || el.autocomplete === 'one-time-code') return true;
    return false;
  }
  function values(form) {
    var seen = Object.create(null);
    return Array.from(form.querySelectorAll(elementSelector)).filter(function (el) {
      return !skipControl(el);
    }).map(function (el) {
      var identifier = el.name;
      var pos = seen[identifier] || 0;
      seen[identifier] = pos + 1;
      var type = String(el.type || '').toLowerCase();
      return {
        n: identifier,
        p: pos,
        t: type,
        v: type === 'checkbox' || type === 'radio' ? !!el.checked :
          el.multiple ? Array.from(el.selectedOptions).map(function (o) { return o.value; }) :
          textContent(el.value)
      };
    });
  }
  function relevant(form) {
    if (!form || form.matches('[data-no-autosave]') || form.id === 'estimate-form') return false;
    if (form.closest('[data-no-autosave]')) return false;
    var action = '';
    try { action = new URL(form.getAttribute('action') || location.href,location.href).pathname; }
    catch (_) { return false; }
    if (disallowAction.test(action)) return false;
    if (/\/(?:login|sign-in|password|reset-password|otp|2fa)(?:\/|$)/i.test(location.pathname)) return false;
    var method = String(form.getAttribute('method') || 'GET').toUpperCase();
    if (method === 'GET' || method === 'DIALOG') return false;
    if (form.querySelector('input[type=password]')) return false;
    var fields = values(form);
    // Ignore one-button confirmation, search and read-only forms.
    return fields.length >= 1 && fields.some(function (f) {
      return f.t !== 'checkbox' && f.t !== 'radio' && (typeof f.v === 'string' || Array.isArray(f.v));
    });
  }
  function key(form) {
    var formId = form.id || form.getAttribute('name') || '';
    var method = String(form.method || 'POST').toUpperCase();
    var action = String(form.getAttribute('action') || '');
    var position = Array.prototype.indexOf.call(document.forms,form);
    return prefix + JSON.stringify([location.pathname,location.search,action,formId,method,position]);
  }
  function read(k) {
    try {
      var v = JSON.parse(store.getItem(k) || 'null');
      if (!v || v.version !== 1 || !Array.isArray(v.fields) ||
          v.user !== account || v.guard !== guard) return null;
      if (now() - Number(v.time || 0) > maxAge) { store.removeItem(k); return null; }
      return v;
    } catch (_) { return null; }
  }
  function meaningful(fields) {
    return fields.some(function (f) {
      if (typeof f.v === 'string') return !!f.v.trim();
      if (Array.isArray(f.v)) return f.v.length > 0;
      return f.v === true;
    });
  }
  function digestFields(fields) {
    return JSON.stringify(fields);
  }
  function notify(message) {
    try {
      if (!statusNode) {
        statusNode = document.createElement('div');
        statusNode.setAttribute('role','status');
        statusNode.id = 'am254-draft-status';
        statusNode.style.cssText = 'position:fixed;bottom:16px;right:16px;z-index:12000;background:#243c50;color:white;padding:8px 11px;border-radius:8px;box-shadow:0 4px 18px #1113;font:12px Arial,sans-serif;max-width:320px';
        document.body.appendChild(statusNode);
      }
      statusNode.textContent = message;
      statusNode.hidden = false;
      clearTimeout(statusTimeout);
      statusTimeout = setTimeout(function () { statusNode.hidden = true; }, 3200);
    } catch (_) { /* draft must never block a form */ }
  }
  function removeBanner(form) {
    var b = form.previousElementSibling;
    if (b && b.classList && b.classList.contains('am254-restore-banner')) b.remove();
  }
  function showBanner(form,meta,draft,k) {
    if (!form || form.previousElementSibling && form.previousElementSibling.classList &&
        form.previousElementSibling.classList.contains('am254-restore-banner')) return;
    var banner = document.createElement('div');
    banner.className = 'am254-restore-banner';
    banner.setAttribute('role','status');
    banner.style.cssText = 'padding:10px 12px;margin:9px 0;border:1px solid #cbd9e8;border-radius:8px;background:#f2f7fc;color:#173c59;font:12px/1.45 Arial,sans-serif;display:flex;gap:10px;align-items:center;flex-wrap:wrap';
    var label = document.createElement('span');
    var stamp = new Date(draft.time);
    label.textContent = 'Unsaved browser draft from ' + stamp.toLocaleString() + ' found. No transaction was posted. ';
    banner.appendChild(label);
    function makeButton(labelText,fn) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = labelText;
      button.style.cssText = 'padding:5px 10px;background:#fff;border:1px solid #9cb6cb;border-radius:5px;color:#173c59;font-weight:700;cursor:pointer';
      button.addEventListener('click',fn);
      banner.appendChild(button);
    }
    makeButton('Restore Draft',function () {
      if (!window.confirm('Restore the earlier browser draft into this form? Current visible values will be replaced.')) return;
      var result = restore(form,draft);
      if (result.missing) {
        // Preserve original complete snapshot; do not overwrite with a partial form.
        meta.blockWrites = true;
        notify('Draft restored where fields exist. ' + result.missing + ' fields need their rows. Add them and click Restore Draft again.');
        showMissingNotice(form,result.missing);
      } else {
        meta.blockWrites = false;
        banner.remove();
        notify('Draft restored. Review and use the normal Save button.');
        meta.dirty = true;
      }
    });
    makeButton('Discard Draft',function () {
      if (!window.confirm('Discard this browser-only draft?')) return;
      try { store.removeItem(k); } catch (_) {}
      meta.dirty = false;
      meta.blockWrites = false;
      banner.remove();
      notify('Browser draft discarded.');
    });
    if (form.parentNode) form.parentNode.insertBefore(banner,form);
  }
  function showMissingNotice(form,num) {
    var msg = document.createElement('div');
    msg.style.cssText = 'border:1px solid #e6c388;border-radius:7px;padding:8px;background:#fff8e8;color:#80530e;margin:8px 0;font:12px Arial,sans-serif';
    msg.textContent = num + ' saved fields belong to rows not currently rendered. Add those rows with the form controls and use Restore Draft again. Do not submit until reviewed.';
    if (form.parentNode) form.parentNode.insertBefore(msg,form);
  }
  function restore(form,draft) {
    var groups = Object.create(null);
    Array.from(form.querySelectorAll(elementSelector)).forEach(function (el) {
      if (!skipControl(el)) (groups[el.name] || (groups[el.name] = [])).push(el);
    });
    var missing=0;
    draft.fields.forEach(function (f) {
      var el = groups[f.n] && groups[f.n][f.p];
      if (!el) { missing++; return; }
      var type = String(el.type || '').toLowerCase();
      if (type === 'checkbox' || type === 'radio') {
        el.checked = f.v === true;
      } else if (el.multiple && Array.isArray(f.v)) {
        Array.from(el.options).forEach(function(o) {o.selected=f.v.indexOf(o.value)>=0;});
      } else if (typeof f.v === 'string' && type !== 'file') {
        el.value = f.v;
      }
      // Dispatch input so existing calculations/validation update naturally.
      try {
        el.dispatchEvent(new Event('input',{bubbles:true}));
        el.dispatchEvent(new Event('change',{bubbles:true}));
      } catch (_) {}
    });
    return {missing:missing};
  }
  function bind(form) {
    if (formsSeen.has(form) || !relevant(form)) return;
    formsSeen.add(form);
    var k = key(form), initial = read(k);
    var meta = {key:k,dirty:false,blockWrites:!!(initial && meaningful(initial.fields)),timer:0,last:null};
    activeStores.set(form,meta);
    if (initial && meaningful(initial.fields)) showBanner(form,meta,initial,k);
    function persist() {
      clearTimeout(meta.timer);meta.timer=0;
      if (!meta.dirty || meta.blockWrites) return;
      var fields=values(form);
      if (!fields.length || !meaningful(fields)) return;
      var snapshot={version:1,time:now(),user:account,guard:guard,fields:fields};
      var json=JSON.stringify(snapshot);
      if (json.length>maxBytes) {
        notify('Draft is too large to store locally. Please use Save Draft.');
        return;
      }
      if (json===meta.last) return;
      try {
        store.setItem(k,json);
        meta.last=json;
        notify('Auto Draft Saved on this browser (not submitted).');
      } catch (_) {notify('Local draft storage unavailable. Please use Save Draft.');}
    }
    function schedule(e) {
      if (e && e.target && e.target.closest('[data-no-autosave]')) return;
      if (meta.blockWrites) return;
      meta.dirty=true;
      clearTimeout(meta.timer);
      meta.timer=setTimeout(persist,500);
    }
    form.addEventListener('input',schedule,true);
    form.addEventListener('change',schedule,true);
    form.addEventListener('submit',function(){ meta.dirty=true;persist(); },true);
    // Dynamic form rows (e.g. purchase/estimate) can be inserted or removed.
    var observer=new MutationObserver(function (mutations) {
      if (!mutations.some(function(m){return m.addedNodes.length || m.removedNodes.length;})) return;
      // Do not start storing a blank form just because a menu opened.
      if (meta.dirty) schedule();
    });
    observer.observe(form,{childList:true,subtree:true});
    meta.persist=persist;
  }
  function collect() { Array.from(document.forms).forEach(bind); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',collect);
  else collect();
  // Forms inserted later by modal/AJAX.
  var pending=0;
  var domObserver=new MutationObserver(function (mutations) {
    if (!mutations.some(function(m) {
      return Array.from(m.addedNodes).some(function(n) {
        return n.nodeType===1 && (n.tagName==='FORM' || n.querySelector && n.querySelector('form'));
      });
    })) return;
    clearTimeout(pending);pending=setTimeout(collect,300);
  });
  domObserver.observe(document.documentElement,{subtree:true,childList:true});
  function flush() {
    activeStores.forEach(function(meta) {if(meta && meta.persist) meta.persist();});
  }
  window.addEventListener('pagehide',flush);
  document.addEventListener('visibilitychange',function(){if(document.hidden)flush();});
})();
