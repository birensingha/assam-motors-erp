{{-- AM194_COMPACT_CUSTOMER_CORRECTION: hidden modal; no large form on JC screen.
     Existing AM172 POST controller remains authoritative for identity, history
     locks, audit, invoice protection and validation. --}}
@php
    $am194Actor = request()->user();
    $am194IsAdmin = $am194Actor instanceof \App\Models\ErpAuthUser
        && $am194Actor->active && $am194Actor->isAdmin();
    $am194CanCorrect = $am194IsAdmin
        && empty($job->invoice_id)
        && !in_array(strtolower(trim((string)($job->status ?? ''))), [
            'invoiced','cancelled','canceled'
        ], true);
    $am194HasMasterId = (int)($job->customer_id ?? 0) > 0;
    $am194Mode = old('mode', $am194HasMasterId ? 'sync' : 'manual');
    if (!in_array($am194Mode, $am194HasMasterId ? ['sync','manual'] : ['manual'], true)) {
        $am194Mode = 'manual';
    }
    $am194RestoreModal = in_array(old('mode'), ['sync','manual'], true)
        && ($errors->has('new_name') || $errors->has('reason')
            || $errors->has('customer_name') || $errors->has('customer_id'));
@endphp
<style id="AM194_COMPACT_CUSTOMER_CORRECTION">
.am194-top-button{display:inline-flex!important;align-items:center!important;justify-content:center!important;
  flex:0 0 auto!important;min-height:38px!important;padding:9px 13px!important;white-space:nowrap!important;
  border:1px solid #8aafd6!important;border-radius:8px!important;color:#164a82!important;
  background:#fff!important;font-size:12px!important;font-weight:850!important;cursor:pointer!important}
.am194-top-button:hover,.am194-top-button:focus-visible{background:#e7f2ff!important;outline:2px solid #93c5fd}
.am194-backdrop[hidden]{display:none!important}
.am194-backdrop{position:fixed!important;inset:0!important;z-index:9999!important;display:flex!important;
  align-items:center!important;justify-content:center!important;padding:16px!important;
  background:rgba(7,25,48,.57)!important;overflow-y:auto!important}
.am194-modal{width:min(100%,540px);max-height:calc(100vh - 32px);overflow:auto;border-radius:14px;
  background:#fff;box-shadow:0 18px 65px rgba(0,19,46,.28);padding:0}
.am194-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 20px;
  color:#fff;background:#123963}
.am194-head h2{font-size:18px;font-weight:850;color:#fff;margin:0 0 4px}
.am194-head p{margin:0;color:#dbeafa;font-size:12px}
.am194-close{cursor:pointer;border:1px solid #7495b9;border-radius:8px;color:#fff;background:transparent;
  font-size:21px;min-width:34px;min-height:34px}
.am194-content{padding:18px 20px}
.am194-current{border:1px solid #dbe6f1;background:#f5f8fc;border-radius:9px;
  padding:10px 12px;margin:0 0 14px;color:#1b3554;font-size:13px}
.am194-choice{display:block;border:1px solid #d1ddeb;border-radius:10px;padding:10px 12px;
  margin:8px 0;cursor:pointer;background:#fff}
.am194-choice:has(input:checked){border-color:#0e5d9f;background:#f0f7ff}
.am194-choice input{vertical-align:middle;margin-right:7px}
.am194-choice strong{font-size:13px;color:#1d3654}
.am194-choice small{display:block;margin-left:23px;color:#64748b;margin-top:2px}
.am194-field{display:block;font-size:12px;color:#2d4867;font-weight:700;margin-top:14px}
.am194-field input,.am194-field textarea{display:block;width:100%;margin-top:6px;border:1px solid #c7d7e6;
  border-radius:8px;padding:10px;font-size:13px;background:#fff;color:#17365a}
.am194-field textarea{min-height:66px;resize:vertical}
.am194-field[hidden]{display:none!important}
.am194-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;margin-top:18px}
.am194-actions button{cursor:pointer;border-radius:8px;padding:10px 13px;font-weight:750;font-size:12px}
.am194-cancel{background:#fff;color:#254565;border:1px solid #c2d4e7}
.am194-submit{background:#15528d;color:#fff;border:1px solid #15528d}
.am194-note{font-size:11px;color:#52657a;line-height:1.5;margin:12px 0 0}
.am194-error{color:#a61a1a;font-weight:650;font-size:12px;margin:8px 0}
@media(max-width:600px){.am194-content{padding:14px}.am194-head{padding:14px}.am194-top-button{font-size:11px!important;padding:9px 11px!important}}
</style>
@if($am194CanCorrect)
<div id="am194-customer-modal" class="am194-backdrop" role="presentation" hidden
     data-restore="{{ $am194RestoreModal ? '1':'0' }}">
  <section class="am194-modal" role="dialog" aria-modal="true"
           aria-labelledby="am194-title" aria-describedby="am194-description" tabindex="-1">
    <header class="am194-head">
      <div>
        <h2 id="am194-title">Correct Customer Name</h2>
        <p id="am194-description">Admin-only · One Job Card · Full audit</p>
      </div>
      <button type="button" class="am194-close" id="am194-close" aria-label="Close customer correction">×</button>
    </header>
    <div class="am194-content">
      <p class="am194-current">Current Job Card customer: <strong>{{ $job->customer_name ?: '—' }}</strong></p>
      @foreach(['customer_name','customer_id','new_name','reason'] as $am194Field)
        @if($errors->has($am194Field))
          <p class="am194-error" role="alert">{{ $errors->first($am194Field) }}</p>
        @endif
      @endforeach
      <form id="am194-customer-form" method="POST"
            action="{{ route('erp.job-cards.customer-name-sync', $job->id) }}">
        @csrf
        @if($am194HasMasterId)
          <label class="am194-choice">
            <input type="radio" name="mode" value="sync" @checked($am194Mode==='sync')>
            <strong>Sync from Customer Master</strong>
            <small>Only after matching mobile/email identity is verified by ERP.</small>
          </label>
        @endif
        <label class="am194-choice">
          <input type="radio" name="mode" value="manual" @checked($am194Mode==='manual')>
          <strong>Correct Name Manually</strong>
          <small>Spelling correction on this Job Card only; Customer Master unchanged.</small>
        </label>
        <label class="am194-field" id="am194-manual-label">
          New Customer Name
          <input id="am194-new-name" name="new_name" type="text" minlength="2"
                 maxlength="200" value="{{ old('new_name') }}"
                 autocomplete="off" placeholder="Enter corrected name">
        </label>
        <label class="am194-field">
          Correction Reason (minimum 10 characters)
          <textarea id="am194-reason" name="reason" minlength="10" maxlength="500"
                    required placeholder="Why is the name being corrected?">{{ old('reason') }}</textarea>
        </label>
        <p class="am194-note">Existing invoices are protected. No Customer ID, Customer Master,
          invoice or payment record will be changed by this action.</p>
        <div class="am194-actions">
          <button type="button" class="am194-cancel" id="am194-cancel">Cancel</button>
          <button type="submit" class="am194-submit">Save Name Correction</button>
        </div>
      </form>
    </div>
  </section>
</div>
<script>
(function() {
  'use strict';
  function initCustomerModal() {
  var modal = document.getElementById('am194-customer-modal');
  var open = document.getElementById('am194-open');
  if (!modal || !open) return;
  var panel = modal.querySelector('.am194-modal');
  var form = document.getElementById('am194-customer-form');
  var nameInput = document.getElementById('am194-new-name');
  var manualLabel = document.getElementById('am194-manual-label');
  var priorFocus = null, priorBodyOverflow = '';
  var choices = form.querySelectorAll('input[name="mode"]');

  function toggleMode() {
    var checked = form.querySelector('input[name="mode"]:checked');
    var manual = checked && checked.value === 'manual';
    manualLabel.hidden = !manual;
    nameInput.required = !!manual;
    nameInput.disabled = !manual;
  }
  function openModal() {
    priorFocus = document.activeElement;
    modal.hidden = false;
    priorBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    toggleMode();
    panel.focus();
  }
  function closeModal() {
    modal.hidden = true;
    document.body.style.overflow = priorBodyOverflow;
    if (priorFocus && priorFocus.focus) priorFocus.focus();
  }
  open.addEventListener('click', openModal);
  document.getElementById('am194-close').addEventListener('click', closeModal);
  document.getElementById('am194-cancel').addEventListener('click', closeModal);
  modal.addEventListener('click',function(event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function(event) {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
    }
    if (event.key === 'Tab') {
      var focusable = Array.from(panel.querySelectorAll(
        'button:not([disabled]),input:not([disabled]),textarea:not([disabled])'
      )).filter(function(el){return el.getClientRects().length > 0;});
      if (!focusable.length) return;
      var first = focusable[0], last = focusable[focusable.length-1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();first.focus();
      }
    }
  });
  Array.from(choices).forEach(function(choice){
    choice.addEventListener('change',toggleMode);
  });
  form.addEventListener('submit',function(event) {
    var mode = form.querySelector('input[name="mode"]:checked');
    if (!mode) {event.preventDefault();return;}
    if (!confirm('Correct this Job Card customer name? This action is audited.')) {
      event.preventDefault();
    }
  });
  toggleMode();
  if (modal.getAttribute('data-restore') === '1') openModal();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded',initCustomerModal,{once:true});
  } else {
    initCustomerModal();
  }
})();
</script>
@endif
