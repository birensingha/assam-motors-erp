  // AM263_GRN_DRAFT_RESTORE: re-create unsaved dynamic GRN rows before the
  // AM254 generic name-based draft restore resolves its form controls.
  // Never posts a GRN, changes stock, or deletes any browser draft.
  function prepareGrnDraftRows(form,draft) {
    if (!form || form.id !== 'am260-grn-form') return;
    var body = form.querySelector('#am260-grn-rows');
    if (!body || !body.querySelector('input.part-search') ||
        typeof window.am260AddGrnRow !== 'function') return;
    var savedIndexes = Object.create(null);
    (draft.fields || []).forEach(function (field) {
      var match = /^lines\[(\d+)\]\[(?:part_master_id|part_search_label|qty|unit_cost)\]$/.exec(field.n || '');
      if (match && Number(match[1]) < 100) savedIndexes[match[1]] = true;
    });
    var requested = Object.keys(savedIndexes);
    if (!requested.length) return;
    for (var attempt = 0; attempt < 100; attempt++) {
      var needsRow = requested.some(function (index) {
        return !body.querySelector('input[name="lines[' + index + '][part_master_id]"]');
      });
      if (!needsRow) break;
      var count = body.querySelectorAll('tr.line').length;
      if (!count || count >= 100) break;
      window.am260AddGrnRow();
      if (body.querySelectorAll('tr.line').length <= count) break;
    }
  }
  // Older AM260 draft snapshots stored hidden part_master_id but NOT the
  // unnamed Part Name input. Reconstruct visible dropdown text from master ID.
  // New AM263 snapshots also save lines[N][part_search_label] directly.
  function hydrateGrnPartSearches(form) {
    if (!form || form.id !== 'am260-grn-form') return 0;
    var datalist = document.getElementById('am260-part-options');
    if (!datalist) return 0;
    var options = Object.create(null);
    Array.from(datalist.querySelectorAll('option')).forEach(function (option) {
      var id = String(option.getAttribute('data-id') || '');
      if (id) options[id] = option.value;
    });
    var missing = 0;
    Array.from(form.querySelectorAll('#am260-grn-rows .part-id')).forEach(function (hidden) {
      var row = hidden.closest('tr');
      var display = row && row.querySelector('input.part-search');
      if (!display) return;
      var partId = String(hidden.value || '').trim();
      if (partId && Object.prototype.hasOwnProperty.call(options,partId)) {
        // Deliberately no input event: the hidden Part ID is already restored.
        // This avoids the GRN auto-add-blank-row handler running recursively.
        display.value = options[partId];
      } else if (partId || display.value.trim()) {
        // Missing/inactive Part Master or typed-but-unselected Part.
        // Keep the original full snapshot; user can reselect safely.
        missing++;
      }
    });
    return missing;
  }
