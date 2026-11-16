/* AM258_ALWAYS_READY_ENTRY_ROW
 * Tally-style ERP line entry: keep one trailing blank row ready; on any
 * user entry append a fresh blank without moving focus. Do not submit blank
 * lines to Laravel. No network calls, stock, payment or ledger writes.
 */
(function () {
  'use strict';
  if (window.__am258ReadyRowActive) return;
  window.__am258ReadyRowActive = true;

  var definitions = [
    {
      form: '#estimate-form', body: '#estimate-lines',
      add: '#add-estimate-line', row: 'tr', kind: 'estimate',
      picker: '.e-ref-search', max: 101
    },
    {
      form: '#purchase-form', body: '#purchase-lines',
      add: '#add-line', row: '.purchase-line', kind: 'purchase',
      picker: '.part-search', max: 101
    },
    {
      form: '#p31-counter-invoice-form', body: '#p31-lines',
      add: '#p31-add-line', row: 'tr', kind: 'counter',
      picker: '.am60-input, select[data-part]', max: 101
    }
  ];

  function setup(def) {
    var form = document.querySelector(def.form);
    var body = document.querySelector(def.body);
    var addButton = document.querySelector(def.add);
    if (!form || !body || !addButton || form.dataset.am258Ready === '1') return;
    form.dataset.am258Ready = '1';

    var appending = false;
    var submitting = false;
    var pendingEnsure = false;

    function rows() {
      return Array.from(body.children).filter(function (r) {
        return r.matches(def.row);
      });
    }

    function entered(row) {
      if (!row) return false;
      if (row.dataset.am258Touched === '1') return true;
      if (def.kind === 'estimate') {
        return !!(
          row.querySelector('.e-ref-id')?.value ||
          row.querySelector('.e-ref-search')?.value.trim() ||
          row.querySelector('.e-desc')?.value.trim()
        );
      }
      if (def.kind === 'purchase') {
        return !!(
          row.querySelector('.part-id')?.value ||
          row.querySelector('.part-search')?.value.trim()
        );
      }
      return !!(
        row.querySelector('select[data-part]')?.value ||
        row.querySelector('[data-note]')?.value.trim()
      );
    }

    function updateRequired(row) {
      var used = entered(row);
      // Native browser validates required fields before firing a submit event.
      // A completely empty row must not block the user from saving other rows.
      if (def.kind === 'estimate') {
        var description = row.querySelector('.e-desc');
        if (description) description.required = used;
      }
      if (def.kind === 'counter') {
        var part = row.querySelector('select[data-part]');
        var rate = row.querySelector('[data-rate]');
        if (part) part.required = used;
        if (rate) rate.required = used;
      }
      row.querySelectorAll('.e-remove, .remove-line, [data-remove]').forEach(function (button) {
        button.tabIndex = -1; // Tab runs from the final field to the next item row.
      });
      row.querySelectorAll('[data-gst][readonly]').forEach(function (input) {
        input.tabIndex = -1;
      });
    }

    function prepareRows() {
      rows().forEach(updateRequired);
    }

    function addBlank() {
      if (appending || submitting) return;
      var current = rows();
      if (current.length && !entered(current[current.length - 1])) return;
      if (current.length >= def.max) return;
      appending = true;
      var previous = document.activeElement;
      try {
        window.__am258AppendingBlank = true;
        addButton.click(); // Existing working row constructor, not a copied template.
      } finally {
        window.__am258AppendingBlank = false;
        appending = false;
      }
      // All patched row constructors respect the flag. Fallback for Counter.
      if (previous && previous.isConnected &&
          (document.activeElement !== previous) &&
          typeof previous.focus === 'function') {
        previous.focus({ preventScroll: true });
      }
      prepareRows();
    }

    function ensure() {
      if (submitting || appending) return;
      prepareRows();
      var current = rows();
      if (!current.length || entered(current[current.length - 1])) addBlank();
      prepareRows();
    }

    function scheduleEnsure() {
      if (pendingEnsure || submitting) return;
      pendingEnsure = true;
      queueMicrotask(function () {
        pendingEnsure = false;
        ensure();
      });
    }

    function handleEntry(e) {
      var element = e.target;
      if (!element || !element.closest) return;
      var row = element.closest(def.row);
      if (!row || row.parentNode !== body || submitting) return;
      // Genuine field input, OR programmatic master selection (native select
      // dispatches a change event when its chosen option has been committed).
      var chosenMaster = (
        def.kind === 'counter' && element.matches('select[data-part]') && !!element.value
      ) || (
        def.kind === 'estimate' && element.matches('.e-ref-id') && !!element.value
      ) || (
        def.kind === 'purchase' && element.matches('.part-id') && !!element.value
      );
      if (e.isTrusted || chosenMaster) row.dataset.am258Touched = '1';
      if (entered(row)) scheduleEnsure();
      else updateRequired(row);
    }

    form.addEventListener('input', handleEntry, false);
    form.addEventListener('change', handleEntry, false);

    // + Add Line reuses the existing ready blank; prevents two empty rows.
    addButton.addEventListener('click', function (e) {
      if (window.__am258AppendingBlank) return;
      var all = rows();
      var last = all[all.length - 1];
      if (!last || entered(last)) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      var input = last.querySelector(def.picker);
      if (input && !input.disabled) input.focus();
    }, true);

    // Row delete, restored draft, and any other native line constructors.
    new MutationObserver(scheduleEnsure).observe(body, { childList: true });

    // A blank row is a UI entry slot, NOT an item. Completely omit it from
    // submitted FormData so existing backend validation and pricing remain
    // authoritative. Removing from DOM also lets legacy Purchase Edit JS
    // duplicate/empty-row checks see only actual entered rows.
    form.addEventListener('submit', function (e) {
      var present = rows();
      var meaningful = present.filter(entered);
      if (!meaningful.length) {
        e.preventDefault();
        var first = present[0] || null;
        var input = first?.querySelector(def.picker);
        if (input) input.focus();
        window.alert('Please enter at least one item before saving.');
        return;
      }
      submitting = true;
      var removed = [];
      present.forEach(function (row) {
        if (entered(row)) return;
        var placeholder = document.createComment('AM258_BLANK_UI_ROW_NOT_SUBMITTED');
        row.replaceWith(placeholder);
        removed.push({ placeholder: placeholder, row: row });
      });
      // A later handler can still block the submit (e.g. price/stock errors).
      // Restore slots if the browser stays here; actual submitted FormData
      // has already been built after dispatching the submit event.
      setTimeout(function () {
        removed.forEach(function (record) {
          if (record.placeholder.isConnected) {
            record.placeholder.replaceWith(record.row);
          }
        });
        submitting = false;
        ensure();
      }, 120);
    }, true);

    // All rows restored from server/draft must have one ready slot after them.
    ensure();
    var firstTimeout = setTimeout(ensure, 80);
    window.addEventListener('pagehide', function () { clearTimeout(firstTimeout); });
  }

  function init() {
    definitions.forEach(setup);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
