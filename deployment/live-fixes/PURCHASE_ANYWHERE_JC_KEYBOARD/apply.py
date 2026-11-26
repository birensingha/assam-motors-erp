#!/usr/bin/env python3
"""Guarded staging-only purchase search and keyboard patch."""
import sys,re,subprocess,tempfile,shutil,os
from pathlib import Path
mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
assert mode in ('check','apply') and (root/'artisan').is_file()
controller=root/'app/Http/Controllers/Web/AdminPurchasePageController.php'
view=root/'resources/views/purchases/osl-create.blade.php'
old={p:p.read_text() for p in (controller,view)}
marker='AM_PURCHASE_ANYWHERE_KEYBOARD_V1'
if all(marker in s for s in old.values()):
 print('ALREADY_APPLIED');sys.exit(0)
assert not any(marker in s for s in old.values()),'Partial install'
s=old[controller]
start=s.index("        if ($q !== '') {",s.index('public function partSearch('))
# Match the original query block only, not the ordering or financial fields.
end=re.search(r'^        \}',s[start:],re.M)
assert end
end=start+end.end()
block=s[start:end]
assert all(x in block for x in ["$normLike","part_name","part_code","hsn_code","category"])
newblock=r"""        // AM_PURCHASE_ANYWHERE_KEYBOARD_V1
        if ($q !== '') {
            $terms = array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 12);
            foreach ($terms as $term) {
                $like = '%'.mb_strtolower($term).'%';
                $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($term)) ?: '';
                $query->where(function ($w) use ($like, $normalized) {
                    foreach (['part_name','part_code','hsn_code','category','vehicle_make','vehicle_model'] as $column) {
                        $w->orWhereRaw("LOWER(COALESCE($column,'')) LIKE ?", [$like]);
                    }
                    if ($normalized !== '') {
                        foreach (['part_name','part_code'] as $column) {
                            $w->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(COALESCE($column,'')),' ',''),'-',''),'/',''),'.','') LIKE ?", ['%'.$normalized.'%']);
                        }
                    }
                });
            }
        }"""
s=s[:start]+newblock+s[end:]
v=old[view]
anchor='<script id="am232-osl-purchase-inline">{!! file_get_contents(public_path(\'js/am232-osl-purchase.js\')) !!}</script>'
assert v.count(anchor)==1
assert 'am232-job-choice' in v and 'am232-job-menu' in v
extra=r"""
<style>
/* AM_PURCHASE_ANYWHERE_KEYBOARD_V1 */
#osl-form .am232-job-choice.am-key-active{background:#dbeafe!important;outline:2px solid #2563eb;outline-offset:-2px;color:#123b70}
</style>
<script>
(function(){
'use strict';
const form=document.getElementById('osl-form');
if(!form)return;
const states=new WeakMap();
function context(input){
 const wrap=input.closest('.job-filter-wrap');
 const menu=wrap&&wrap.querySelector('.am232-job-menu');
 if(!menu||menu.hidden||!menu.getClientRects().length)return null;
 const choices=Array.from(menu.querySelectorAll('.am232-job-choice')).filter(b=>!b.disabled&&!b.hidden&&b.getClientRects().length);
 return {menu,choices};
}
function clear(input){
 const c=context(input);
 if(c)c.choices.forEach(b=>b.classList.remove('am-key-active'));
 states.delete(input);
}
form.addEventListener('input',e=>{if(e.target.matches('.job-filter'))clear(e.target)},true);
form.addEventListener('keydown',e=>{
 const input=e.target;
 if(!input.matches('.job-filter')||e.isComposing)return;
 const c=context(input);
 const key=e.key;
 if(!['ArrowDown','ArrowUp','Enter','Tab','Escape'].includes(key)||e.shiftKey&&key==='Tab')return;
 if(!c){
   if(key==='Enter'){e.preventDefault();e.stopImmediatePropagation();}
   return;
 }
 const current=states.get(input);
 let index=c.choices.indexOf(current);
 if(key==='Escape'){
   e.preventDefault();e.stopImmediatePropagation();clear(input);c.menu.hidden=true;input.setAttribute('aria-expanded','false');return;
 }
 if(!c.choices.length){
   if(key==='Enter'){e.preventDefault();e.stopImmediatePropagation();}
   return;
 }
 if(key==='ArrowDown'||key==='ArrowUp'){
   e.preventDefault();e.stopImmediatePropagation();
   index=index<0?(key==='ArrowDown'?0:c.choices.length-1):Math.max(0,Math.min(c.choices.length-1,index+(key==='ArrowDown'?1:-1)));
   c.choices.forEach((b,i)=>b.classList.toggle('am-key-active',i===index));
   const choice=c.choices[index];states.set(input,choice);
   choice.scrollIntoView({block:'nearest',inline:'nearest'});return;
 }
 // Only select a highlighted result (or an unambiguous single match).
 const choice=index>=0?c.choices[index]:c.choices.length===1?c.choices[0]:null;
 if(!choice){
   if(key==='Enter'){e.preventDefault();e.stopImmediatePropagation();}
   return;
 }
 e.preventDefault();e.stopImmediatePropagation();
 clear(input);
 // Reuse existing AM232 click handler and its original JC/billing selection.
 choice.click();
 if(key==='Tab'){
   const row=input.closest('.osl-line');
   const next=row&&Array.from(row.querySelectorAll('input,select,button')).find(el=>el!==input&&!el.disabled&&el.tabIndex>=0&&el.getClientRects().length&&Boolean(input.compareDocumentPosition(el)&Node.DOCUMENT_POSITION_FOLLOWING)&&!el.closest('.am232-job-menu'));
   if(next)next.focus();
 }
},true);
})();
</script>
"""
v=v.replace(anchor,anchor+extra)
candidate={controller:s,view:v}
with tempfile.TemporaryDirectory() as d:
 t=Path(d)
 php=t/'controller.php';php.write_text(s)
 subprocess.run(['php','-l',str(php)],check=True,cwd=root)
 blade=t/'view.blade.php';blade.write_text(v);compiled=t/'compiled.php'
 compiler=r"require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"
 subprocess.run(['php','-r',compiler,str(blade),str(compiled)],check=True,cwd=root)
 subprocess.run(['php','-l',str(compiled)],check=True,cwd=root)
 if shutil.which('node'):
  js=t/'keyboard.js';js.write_text(extra.split('<script>')[1].split('</script>')[0]);subprocess.run(['node','--check',str(js)],check=True)
print('PURCHASE_PATCH_PREFLIGHT_PASS')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups'/marker
assert not backup.exists(),'Backup already exists'
backup.mkdir(parents=True)
for p in old:shutil.copy2(p,backup/p.name)
def write(p,content):
 fd,tmp=tempfile.mkstemp(dir=p.parent)
 try:
  with os.fdopen(fd,'w') as f:f.write(content)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
try:
 for p,value in candidate.items():write(p,value)
 subprocess.run(['php','artisan','view:clear'],check=True,cwd=root)
except BaseException:
 for p,value in old.items():write(p,value)
 raise
print('PURCHASE_PATCH_APPLIED; backup:',backup)
