{{-- P55_OLD_ERP_ROT_ALTERATION: separate per-line form; existing Job Card header form unchanged. --}}
<style id="p55-rot-editor">
.p55-panel{background:#fff;border:1px solid #c9d9e7;border-radius:13px;margin:17px 0;padding:14px;box-shadow:0 5px 15px rgba(19,62,105,.045)}
.p55-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.p55-head h2{margin:0;color:#143b61;font-size:16px}.p55-head small{color:#647d91;font-size:11px}
.p55-rot{border:1px solid #cedde9;border-radius:12px;overflow:hidden;margin-top:11px}
.p55-rot.is-unlocked{border-color:#71ba96;background:#f5fcf7}
.p55-rot-top{padding:10px 12px;background:#f4f8fc;display:flex;gap:8px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.p55-rot.is-unlocked .p55-rot-top{background:#e5f6e9}
.p55-rot-top strong{font-size:13px;color:#214a70}
.p55-status{border-radius:99px;padding:3px 8px;font-size:10px;font-weight:800;color:#26578b;background:#ddecf9}
.p55-status.completed{background:#dcefe7;color:#19603d}
.p55-fields{display:grid;grid-template-columns:minmax(170px,2fr) minmax(145px,1.4fr) repeat(2,minmax(143px,1.35fr)) repeat(3,minmax(92px,.85fr));gap:8px;align-items:end;padding:11px}
.p55-fields label,.p55-approval label{font-size:10px;letter-spacing:.01em;text-transform:uppercase;color:#536c83;font-weight:800;display:flex;flex-direction:column;gap:5px;min-width:0}
.p55-fields input,.p55-fields select,.p55-approval input,.p55-approval select,.p55-approval textarea{width:100%;border:1px solid #cbd9e6;border-radius:8px;min-height:38px;padding:7px 8px;background:#fff;color:#244361;font:inherit;font-size:12px}
.p55-fields [disabled]{color:#405a71;opacity:1;background:#eef2f6;cursor:not-allowed}
.p55-tat{font-size:10px;color:#526d85;padding:7px 12px 12px}
.p55-tat b{color:#1a4d7a}
.p55-btn{min-height:37px;border-radius:8px;border:1px solid #c8d8e5;background:#fff;color:#144b7a;font-size:11px;font-weight:800;padding:8px 11px;cursor:pointer}
.p55-btn.lock{background:#fff5d8;border-color:#e2b648;color:#805c16}
.p55-btn.unlock{background:#0d3b69;color:#fff;border-color:#0d3b69}
.p55-btn.save{background:#127146;color:white;border-color:#127146}
.p55-btn[disabled]{opacity:.55;cursor:not-allowed}
.p55-approval{border:1px solid #edbf66;border-radius:9px;background:#fff9ee;padding:12px;margin:0 10px 10px}
.p55-approval h3{font-size:12px;color:#8c600d;margin:0 0 4px}
.p55-approval p{margin:0 0 11px;font-size:10.5px;color:#765b36}
.p55-approval-grid{display:grid;grid-template-columns:1fr 1.6fr auto;gap:9px;align-items:end}
.p55-footer{display:flex;align-items:center;justify-content:space-between;gap:11px;flex-wrap:wrap;padding:11px;border-top:1px solid #dbe9f0}
.p55-note{font-size:10.5px;color:#526e86}
.p55-alert{background:#fff6df;border:1px solid #ebd39b;border-radius:9px;color:#866014;padding:10px;font-size:11px}
.p55-success{color:#146a39;font-size:11px;font-weight:800;margin:6px 0 0}
@media(max-width:1240px){.p55-fields{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:720px){.p55-fields{grid-template-columns:repeat(2,minmax(0,1fr))}.p55-approval-grid{grid-template-columns:1fr}.p55-footer{align-items:stretch}.p55-footer button{width:100%}}
@media(max-width:380px){.p55-fields{grid-template-columns:1fr}}
</style>
<div class="p55-panel" id="p55-rot-edit">
  <div class="p55-head">
    <div><h2>🔧 Labour / ROT Item Selection & Editing</h2><small>Old ERP-style Completed ROT alteration · Manager/Admin correction audit</small></div>
    <span class="p55-status">{{ ($p55Rows??collect())->count() }} Labour / ROT</span>
  </div>
  @if($p55InvoiceLocked??false)
    <div class="p55-alert">Invoice already issued: original ROT amounts and completed history are locked. Reverse/revise through authorised finance controls first.</div>
  @else
    <div class="p55-alert">Completed ROTs remain locked until Admin selects a reason, enters an Admin Note and clicks Unlock. Changing ROT dates corrects elapsed TAT; it does not fabricate productive time or overwrite original staff events.</div>
  @endif
  <datalist id="p55-rot-master-list">
    @foreach(($p55Master??collect()) as $master)
      <option value="{{ $master->rot_code }}" label="{{ $master->rot_description??$master->description??$master->rot_code }}">{{ $master->rot_code }} — {{ $master->rot_description??$master->description??'' }}</option>
    @endforeach
  </datalist>
  @forelse(($p55Rows??collect()) as $line)
  @php
    $p55Session=($p55Sessions??collect())->first(fn($s)=>(string)($s->labour_line_id??'')===(string)$line->id ||
         (!empty($line->rot_session_id) && (string)$s->id===(string)$line->rot_session_id));
    $p55State=strtoupper(trim((string)($p55Session->status??$line->execution_status??'PLANNED')));
    $p55Completed=$p55State==='COMPLETED';
    $p55Start=$line->rot_start ? \Carbon\Carbon::parse($line->rot_start,'Asia/Kolkata')->format('Y-m-d\TH:i') : '';
    $p55End=$line->rot_end ? \Carbon\Carbon::parse($line->rot_end,'Asia/Kolkata')->format('Y-m-d\TH:i') : '';
    $p55Tat=($line->rot_start&&$line->rot_end)
        ? \Carbon\Carbon::parse($line->rot_start,'Asia/Kolkata')->diff(\Carbon\Carbon::parse($line->rot_end,'Asia/Kolkata'))
        : null;
    $p55TatText=$p55Tat ? ($p55Tat->days*24+$p55Tat->h).'h '.$p55Tat->i.'m' : '—';
  @endphp
  <article class="p55-rot" data-p55-item>
    <div class="p55-rot-top">
      <div><strong>{{ $line->rot_code ?: 'LABOUR' }} · {{ $line->description ?: 'Labour / Work' }}</strong> <span class="p55-status {{ $p55Completed?'completed':'' }}">{{ $p55State }}</span></div>
      @if(!($p55InvoiceLocked??false))
        <button type="button" class="p55-btn lock" data-p55-lock title="Request Completed ROT alteration">🔒 Lock / Alter</button>
      @endif
    </div>
    <form method="post" action="{{ route('erp.job-cards.labour.alter',[$job->id,$line->id]) }}" data-p55-form>
      @csrf
      <input type="hidden" name="expected_old_total" value="{{ number_format((float)$line->total,2,'.','') }}">
      <input type="hidden" name="expected_old_mechanic" value="{{ (int)($line->mechanic_id??0) }}">
      <div class="p55-fields">
        <label>Labour / ROT (Search)
          <input list="p55-rot-master-list" name="rot_code" value="{{ $line->rot_code }}" data-p55-field disabled placeholder="Search ROT Code / Work">
        </label>
        <label>Mechanic
          <select name="mechanic_id" data-p55-field disabled>
            <option value="">Not Assigned</option>
            @foreach(($p55Staff??collect()) as $person)
              <option value="{{ $person->id }}" @selected((int)$line->mechanic_id===(int)$person->id)>{{ $person->name }} · {{ $person->staff_code }}</option>
            @endforeach
          </select>
        </label>
        <label>ROT Start (IST)<input type="datetime-local" name="rot_start" value="{{ $p55Start }}" data-p55-field disabled></label>
        <label>ROT End (IST)<input type="datetime-local" name="rot_end" value="{{ $p55End }}" data-p55-field disabled></label>
        <label>Std Hours<input type="number" name="standard_hours" value="{{ number_format((float)$line->standard_hours,3,'.','') }}" min=".001" max="1000" step=".001" required data-p55-field disabled></label>
        <label>Rate (₹)<input type="number" name="labour_rate" value="{{ number_format((float)$line->labour_rate,2,'.','') }}" min="0" step=".01" required data-p55-field disabled></label>
        <label>Disc %<input type="number" name="discount_percent" value="{{ number_format((float)($line->discount_percent??0),2,'.','') }}" min="0" max="100" step=".01" required data-p55-field disabled></label>
      </div>
      <div class="p55-tat">
         TAT: <b>{{ $p55TatText }}</b> · Productive: {{ number_format((float)($line->productive_seconds??0)/3600,2) }}h · Saved Labour Total: <b>₹{{ number_format((float)$line->total,2) }}</b>
      </div>
      <div class="p55-approval" data-p55-approval hidden>
        <h3>🔒 Completed ROT Alteration</h3>
        <p>Unlock sirf Admin alteration reason aur Admin note ke baad hoga. Original before/after values Job Card audit mein save honge.</p>
        <div class="p55-approval-grid">
          <label>Alteration Reason *
            <select name="alteration_reason" data-p55-reason required>
              <option value="">— Select Reason —</option>
              <option>Wrong ROT selected</option>
              <option>Wrong mechanic selected</option>
              <option>Start / End time correction</option>
              <option>Standard Hours / Rate correction</option>
              <option>Wrong ROT + Timing correction</option>
              <option>Other</option>
            </select>
          </label>
          <label>Admin Note *
            <textarea name="admin_note" rows="2" minlength="3" maxlength="1000" placeholder="Why and what is being corrected?"></textarea>
          </label>
          <button type="button" class="p55-btn unlock" data-p55-unlock>🔓 Unlock for Alteration</button>
        </div>
        <p class="p55-success" data-p55-success hidden>✓ Unlocked. Update fields and click Save Job Card — ROT Alteration.</p>
      </div>
      <div class="p55-footer">
        <span class="p55-note">Reason + Admin note mandatory. No issued invoice, original staff events or stock quantities will be overwritten.</span>
        <button class="p55-btn save" type="submit" data-p55-save disabled>💾 Save Job Card — ROT Alteration</button>
      </div>
    </form>
  </article>
  @empty
    <div class="p55-alert">No Labour/ROT lines added to this Job Card yet.</div>
  @endforelse
</div>
<script id="p55-rot-editor-interaction">
document.querySelectorAll('[data-p55-item]').forEach(function(card){
 const panel=card.querySelector('[data-p55-approval]');
 const form=card.querySelector('[data-p55-form]');
 const save=card.querySelector('[data-p55-save]');
 const fields=card.querySelectorAll('[data-p55-field]');
 const lock=card.querySelector('[data-p55-lock]');
 const unlock=card.querySelector('[data-p55-unlock]');
 if(lock&&panel)lock.addEventListener('click',function(){
    panel.hidden=!panel.hidden;
    panel.scrollIntoView({behavior:'smooth',block:'nearest'});
 });
 if(unlock)unlock.addEventListener('click',function(){
    const reason=form.querySelector('[data-p55-reason]');
    const note=form.querySelector('[name=admin_note]');
    if(!reason.value){reason.reportValidity();return;}
    if(note.value.trim().length<3){note.setCustomValidity('Admin Note is required (minimum 3 characters).');note.reportValidity();note.setCustomValidity('');return;}
    fields.forEach(el=>{el.disabled=false;});
    card.classList.add('is-unlocked');
    save.disabled=false;
    unlock.disabled=true;
    const ok=card.querySelector('[data-p55-success]');if(ok)ok.hidden=false;
 });
 if(form)form.addEventListener('submit',function(ev){
    if(save.disabled){ev.preventDefault();return;}
    const note=form.querySelector('[name=admin_note]');
    if(!note||note.value.trim().length<3){ev.preventDefault();if(note){note.focus();note.reportValidity();}}
 });
});
</script>
