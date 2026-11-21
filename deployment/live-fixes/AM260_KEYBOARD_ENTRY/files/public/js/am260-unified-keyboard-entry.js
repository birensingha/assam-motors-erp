/* AM260_UNIFIED_KEYBOARD_ENTRY
 * Common safe, Tally-style keyboard behavior for authenticated ASSAM MOTORS
 * ERP/Staff data-entry forms. Native Tab order is NEVER intercepted.
 * Enter in an ordinary text/number/email field advances to next editable
 * field; custom item picker Enter is owned by its existing dropdown handler.
 * Does NOT submit, post, approve, delete, record attendance or mutate ledger.
 */
(function () {
  'use strict';
  if (window.__am260UnifiedKeyboardEntry) return;
  var host = document.getElementById('am254-global-draft-script');
  if (!host) return;
  var user = String(host.getAttribute('data-user') || '').trim();
  if (!user || user === '0') return;
  if (/(?:^|\/)(?:login|sign-in|sign-out|password|reset-password|forgot-password|otp|2fa)(?:\/|$)/i.test(location.pathname)) return;
  window.__am260UnifiedKeyboardEntry = true;

  // Never change the keyboard semantics of an action, a confirmation or a
  // dangerous single-field admin command. A user must explicitly click its
  // existing button to perform the business operation.
  var actionRoute = /(?:^|\/)(?:login|logout|sign-in|sign-out|destroy|delete|void|cancel|approve|reject|decline|reverse|credit-note|reset|close|complete|reopen|finalize|verify|qc-result|check-in|check-out|start|pause|resume|assign|unassign|logout-all|result)(?:\/|$)/i;

  var editableSelector = 'input,textarea,select';
  var doNotAdvanceTypes = new Set([
    'hidden','password','file','submit','reset','button','image','color',
    'checkbox','radio'
  ]);
  var advanceTypes = new Set(['text','number','search','tel','email','url']);
  var pickerSelector = [
    '[role="combobox"]',
    '[aria-autocomplete]',
    '[aria-haspopup="listbox"]',
    '[aria-haspopup="tree"]',
    '[list]',
    '.am60-input',
    '.e-ref-search',
    '.part-search',
    '.select2-search__field',
    '.choices__input',
    '.ts-control input',
    '.tom-select-input',
    '[data-am-picker]',
    '[data-autocomplete]'
  ].join(',');

  function visible(element) {
    if (!element || !element.isConnected || element.disabled ||
        element.tabIndex < 0 || element.hidden) return false;
    if (element.closest('[hidden],[inert],template,[aria-hidden="true"]')) return false;
    var style = window.getComputedStyle(element);
    if (style.display === 'none' || style.visibility === 'hidden' ||
        style.visibility === 'collapse') return false;
    // offsetParent may be null for position:fixed and for browser test DOMs.
    // Real display:none and hidden ancestors are already excluded above.
    var parent = element.parentElement;
    while (parent) {
      if (parent.hidden || parent.hasAttribute('inert')) return false;
      var ps = window.getComputedStyle(parent);
      if (ps.display === 'none' || ps.visibility === 'hidden') return false;
      parent = parent.parentElement;
    }
    return true;
  }

  function editable(element) {
    if (!element || !element.matches ||
        !element.matches(editableSelector) ||
        !visible(element) || element.readOnly ||
        element.closest('[data-am-enter-native],[data-am-no-keyboard],[data-no-autosave]')) {
      return false;
    }
    var type = String(element.type || '').toLowerCase();
    return !doNotAdvanceTypes.has(type);
  }

  function isActionForm(form) {
    if (!form || form.hasAttribute('data-am-enter-native') ||
        form.hasAttribute('data-am-no-keyboard') ||
        form.hasAttribute('data-am-transaction-action') ||
        form.closest('[data-am-enter-native],[data-am-no-keyboard]')) return true;
    var method = String(form.getAttribute('method') || 'get').toLowerCase();
    if (method === 'get' || method === 'dialog') return true;
    var intended = form.querySelector('input[name="_method"]');
    if (intended && /^(DELETE)$/i.test(intended.value || '')) return true;
    if (form.querySelector('input[type="password"]')) return true;
    var action = '';
    try { action = new URL(form.getAttribute('action') || location.href,location.href).pathname; }
    catch (_) { return true; }
    if (actionRoute.test(action)) return true;
    var elements = Array.from(form.querySelectorAll(editableSelector)).filter(editable);
    // Single-input submit/search forms do not become keyboard navigators.
    if (elements.length < 2) return true;
    return false;
  }

  function isNativePicker(field) {
    if (field.matches(pickerSelector)) return true;
    var ancestor = field.closest(
      '.select2-container,.choices,.ts-wrapper,.tom-select,.autocomplete,'+
      '[role="combobox"],[data-am-picker],[data-autocomplete]'
    );
    if (ancestor) return true;
    if (field.getAttribute('aria-expanded') === 'true') return true;
    if (field.getAttribute('aria-controls') && field.getAttribute('aria-autocomplete')) return true;
    return false;
  }

  function nextEditable(form,field) {
    var all = Array.from(form.querySelectorAll(editableSelector)).filter(editable);
    var current = all.indexOf(field);
    if (current < 0) return null;
    for (var index = current + 1; index < all.length; index++) {
      if (all[index].form === form) return all[index];
    }
    return null;
  }

  function handleEnter(event) {
    if (event.key !== 'Enter' || event.defaultPrevented ||
        event.isComposing || event.repeat ||
        event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;
    var field = event.target;
    if (!(field instanceof HTMLElement)) return;
    if (!field.matches('input')) return;
    if (!editable(field) || !advanceTypes.has(String(field.type || 'text').toLowerCase())) return;
    var form = field.form || field.closest('form');
    if (isActionForm(form)) return;

    // Picker Enter belongs to its specialized AJAX or native searchable
    // selector. Never choose an arbitrary first Master, double-commit an item,
    // or steal focus away from a highlighted option.
    if (isNativePicker(field)) return;

    // A plain editable field may safely treat Enter as forward navigation.
    // Never submit from a field or silently trigger the last Save/Pay action.
    event.preventDefault();
    var next = nextEditable(form,field);
    if (!next) return;
    next.focus({ preventScroll: true });
    // Only text-like input types support text selection. Calling select()
    // on a number/date/email input can throw InvalidStateError in browsers.
    if (next.tagName === 'INPUT' &&
        ['text','search','tel','url'].includes(next.type) &&
        typeof next.select === 'function' && !isNativePicker(next)) {
      next.select();
    }
  }

  // Bubble listener runs AFTER existing item-search handlers. Those handlers
  // preventDefault + stopPropagation when Enter selects a highlighted option;
  // we deliberately never interfere with that specialized behavior.
  document.addEventListener('keydown',handleEnter,false);
  // Tab / Shift+Tab are intentionally handled by the browser and by
  // AM257/AM259 targeted item selectors, not intercepted here.
  window.__am260KeyboardDebug = {
    // Read-only diagnostics, no personal data, no user input persistence.
    enabled: true,
    version: 'AM260'
  };
})();
