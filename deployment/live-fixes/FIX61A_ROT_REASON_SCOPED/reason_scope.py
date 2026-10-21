#!/usr/bin/env python3
"""FIX61A: existing ROT alteration form - reason-scoped field permission.
Safe exact-anchor replacement; works on STAGING Laravel only, no DB writes.
"""
from pathlib import Path
import sys, os, tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: reason_scope.py check|apply STAGING_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file() and (root/'routes/web.php').is_file()
controller=root/'app/Http/Controllers/Web/JobCardRotAlterationController.php'
view=root/'resources/views/job-cards/rot-alteration.blade.php'
marker='FIX61A_ROT_REASON_SCOPED_V1'
def replace(s,old,new,where):
    n=s.count(old)
    print('FIX61A_ANCHOR',where,n)
    if n!=1:raise SystemExit('FIX61A_PREFLIGHT_FAILED '+where)
    return s.replace(old,new,1)
old_ctl=controller.read_text(encoding='utf8')
old_view=view.read_text(encoding='utf8')
if marker in old_ctl or marker in old_view:
    if marker in old_ctl and marker in old_view:print('FIX61A_ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('FIX61A_PARTIAL_INSTALL_ABORT')
ctl=old_ctl
ctl=replace(ctl,"            'admin_note'=>['required','string','min:3','max:1000'],",
 """            'admin_note'=>['required','string','min:3','max:1000'],
            'alteration_target'=>['nullable','string','in:rot_code,mechanic_id,standard_hours,labour_rate,discount_percent'],""",'validation_target')
for field,old,new in [
('hours',"'standard_hours'=>['required','numeric','gt:0','lte:1000']","'standard_hours'=>['nullable','numeric','gt:0','lte:1000']"),
('rate',"'labour_rate'=>['required','numeric','min:0','lte:10000000']","'labour_rate'=>['nullable','numeric','min:0','lte:10000000']"),
('discount',"'discount_percent'=>['required','numeric','between:0,100']","'discount_percent'=>['nullable','numeric','between:0,100']")]:
    ctl=replace(ctl,old,new,'validation_'+field)
gate="""            // FIX61A_ROT_REASON_SCOPED_V1 - never rely on a browser's disabled inputs.
            // Only the field(s) authorised by the selected alteration reason may be posted.
            $am61AllowedByReason=[
                'Wrong ROT selected'=>['rot_code'],
                'Wrong mechanic selected'=>['mechanic_id'],
                'Start / End time correction'=>['rot_start','rot_end'],
                'Standard Hours / Rate correction'=>['standard_hours','labour_rate'],
                'Wrong ROT + Timing correction'=>['rot_code','rot_start','rot_end'],
            ];
            $am61Reason=(string)$data['alteration_reason'];
            if($am61Reason==='Other'){
                $am61Target=(string)($data['alteration_target']??'');
                if($am61Target==='')$this->block('Other reason requires exact target field.','alteration_target');
                $am61Allowed=[$am61Target];
            }else{
                $am61Allowed=$am61AllowedByReason[$am61Reason]??[];
            }
            if(!$am61Allowed)$this->block('Unsupported ROT alteration reason.','alteration_reason');
            foreach(['rot_code','mechanic_id','rot_start','rot_end',
                      'standard_hours','labour_rate','discount_percent'] as $am61Field){
                if(!in_array($am61Field,$am61Allowed,true) && array_key_exists($am61Field,$data)){
                    $this->block('Reason does not authorise field: '.$am61Field.'. Select appropriate reason.',$am61Field);
                }
            }

"""
ctl=replace(ctl,"            $before=(array)$line;",gate+"            $before=(array)$line;",'insert_gate')
for field,old,new in [
('hours',"$hours=round((float)$data['standard_hours'],3);","$hours=round((float)($data['standard_hours']??$line->standard_hours),3);"),
('rate',"$rate=round((float)$data['labour_rate'],2);","$rate=round((float)($data['labour_rate']??$line->labour_rate),2);"),
('discount',"$discount=round((float)$data['discount_percent'],2);","$discount=round((float)($data['discount_percent']??$line->discount_percent),2);")]:
    ctl=replace(ctl,old,new,'fallback_'+field)
blade=old_view
extra="""              <label data-p55-target-wrap hidden>For Other: Which field? *
                <select name="alteration_target" data-p55-target>
                  <option value="">— Select One Field —</option>
                  <option value="rot_code">ROT Master</option>
                  <option value="mechanic_id">Mechanic</option>
                  <option value="standard_hours">Standard Hours</option>
                  <option value="labour_rate">Labour Rate</option>
                  <option value="discount_percent">Discount %</option>
                </select>
              </label>
"""
blade=replace(blade,"              <option>Other</option>\n", "              <option>Other</option>\n",'other_anchor_check')
blade=replace(blade,"            </select>\n          </label>\n          <label>Admin Note *",
"""            </select>
          </label>
"""+extra+"""          <label>Admin Note *""",'insert_other_target')
oldjs="""     fields.forEach(el=>{el.disabled=false;});
     card.classList.add('is-unlocked');"""
newjs="""     // FIX61A_ROT_REASON_SCOPED_V1: unlock only reason-authorised fields.
     const targets={
       'Wrong ROT selected':['rot_code'],
       'Wrong mechanic selected':['mechanic_id'],
       'Start / End time correction':['rot_start','rot_end'],
       'Standard Hours / Rate correction':['standard_hours','labour_rate'],
       'Wrong ROT + Timing correction':['rot_code','rot_start','rot_end'],
     };
     const other=form.querySelector('[data-p55-target]');
     const allowed=reason.value==='Other'?(other&&other.value?[other.value]:[]):(targets[reason.value]||[]);
     if(!allowed.length){if(other){other.setCustomValidity('Select a target field.');other.reportValidity();other.setCustomValidity('');}return;}
     fields.forEach(el=>{el.disabled=!allowed.includes(el.name);});
     card.classList.add('is-unlocked');"""
blade=replace(blade,oldjs,newjs,'js_scope')
hook="""  // Changing Reason or Other target immediately re-locks the row.
  // Prevents editing a second field with the first reason's authorisation.
  const reasonSelect=form&&form.querySelector('[data-p55-reason]');
  const targetSelect=form&&form.querySelector('[data-p55-target]');
  const targetWrap=form&&form.querySelector('[data-p55-target-wrap]');
  function relock(){fields.forEach(el=>{el.disabled=true;});save.disabled=true;
    if(unlock)unlock.disabled=false;card.classList.remove('is-unlocked');
    const ok=card.querySelector('[data-p55-success]');if(ok)ok.hidden=true;}
  if(reasonSelect)reasonSelect.addEventListener('change',function(){
    relock();if(targetWrap)targetWrap.hidden=reasonSelect.value!=='Other';
    if(targetSelect&&reasonSelect.value!=='Other')targetSelect.value='';
  });
  if(targetSelect)targetSelect.addEventListener('change',relock);
"""
blade=replace(blade,"  if(lock&&panel)lock.addEventListener('click',function(){",
hook+"  if(lock&&panel)lock.addEventListener('click',function(){",'js_relock')
if mode=='check':
    print('FIX61A_CHECK_PASS_CONTROLLER_AND_BLADE_ONLY')
    sys.exit(0)
for p,data in [(controller,ctl),(view,blade)]:
    fd,tmp=tempfile.mkstemp(prefix='.fix61a.',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8') as f:f.write(data)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
print('FIX61A_APPLIED_NO_DATABASE_WRITES')
