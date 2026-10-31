#!/usr/bin/env python3
"""AM244 DEFINITIVE OSL search fix: build input in same proven JC modal constructor as button.
check|apply|rollback STAGING_ROOT. Only alters Job Card Blade; no finance/DB writes.
The previously external AM242 script is removed from runtime to avoid racing the core.
"""
from pathlib import Path
import sys,os,tempfile,hashlib,json,subprocess,shutil,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM244_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM244_STOP_NOT_STAGING')
view=root/'resources/views/job-cards/show.blade.php'
backup=root/'storage/app/am-backups/AM244_OSL_CORE_SEARCH'
before=backup/'show.blade.before';manifest=backup/'checksums.json'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,t=tempfile.mkstemp(prefix='.am244_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if not view.is_file() or view.is_symlink():raise SystemExit('AM244_STOP_MISSING_VIEW')
old=read(view)
if mode=='rollback':
 if not before.is_file() or not manifest.is_file():raise SystemExit('AM244_STOP_BACKUP_MISSING')
 if sha(old)!=json.loads(read(manifest))['after']:raise SystemExit('AM244_STOP_CONCURRENT_CHANGE')
 atomic(view,read(before))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,timeout=90)
 print('AM244_SOURCE_ROLLBACK_PASS');sys.exit(0)
marker='AM244_OSL_CORE_SEARCH'
if marker in old:
 if old.count(marker)==1 and 'am244-core-osl-search' in old:
  print('AM244_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM244_STOP_PARTIAL')
if 'AM243_JC_OSL_SEARCH_INLINE' not in old or 'AM146_OSL_MASTER' not in old:
 raise SystemExit('AM244_STOP_PREREQUISITE_MISSING')
anchor_pattern=r"(?m)^[ \t]*masterRow\.append\(label,add,feedback\);[ \t]*\r?\n[ \t]*form\.insertBefore\(masterRow,form\.firstChild\.nextSibling\);"
matches=list(re.finditer(anchor_pattern,old))
if len(matches)!=1:raise SystemExit('AM244_STOP_CORE_CONSTRUCTOR_ANCHOR_COUNT_'+str(len(matches)))
anchor=matches[0].group(0)
# Use the source of truth already proven by the Create button visible on screen.
code=r"""   // AM244_OSL_CORE_SEARCH: the Master search is created by the EXACT SAME
   // constructor that creates the Create OSL Master button. No async asset race.
   const combo=document.createElement('div');combo.className='am242-combo am244-core-osl-search';
   const searchLabel=document.createElement('label');
   searchLabel.className='am242-label';searchLabel.textContent='OSL Service / Master *';
   const search=document.createElement('input');
   search.type='search';search.className='am242-search am244-search';
   search.placeholder='Search OSL service by name or code';
   search.autocomplete='off';search.setAttribute('role','combobox');
   search.setAttribute('aria-expanded','false');search.setAttribute('aria-autocomplete','list');
   const menu=document.createElement('div');menu.className='am242-options am244-menu';menu.hidden=true;
   menu.setAttribute('role','listbox');
   const hint=document.createElement('div');hint.className='am242-help';hint.setAttribute('role','status');
   searchLabel.appendChild(search);combo.append(searchLabel,menu,hint);
   const normal=x=>String(x||'').toLocaleLowerCase('en-IN').trim().replace(/\s+/g,' ');
   const optionRows=()=>Array.from(select.options).filter(o=>o.value&&o.dataset.name);
   function closeSearch(){menu.hidden=true;search.setAttribute('aria-expanded','false');}
   let activeOption=0;
   function selectService(option){
     select.value=option.value;description.value=option.dataset.name||'';
     search.value=description.value;
     select.dispatchEvent(new Event('change',{bubbles:true}));
     description.dispatchEvent(new Event('input',{bubbles:true}));
     hint.textContent='Selected '+(option.textContent||description.value);
     search.setCustomValidity('');closeSearch();
   }
   function showOptions(){
     const q=normal(search.value);
     const matches=optionRows().filter(o=>!q||normal(o.textContent+' '+o.dataset.name).includes(q)).slice(0,80);
     menu.replaceChildren();activeOption=0;
     matches.forEach(function(o){
       const button=document.createElement('button');button.type='button';button.className='am242-option';
       button.textContent=o.textContent||o.dataset.name;
       button.addEventListener('pointerdown',e=>e.preventDefault());
       button.addEventListener('click',()=>selectService(o));
       menu.appendChild(button);
     });
     if(!matches.length){
       const empty=document.createElement('div');empty.className='am242-empty';
       empty.textContent=q?'No matching OSL Master. Use + Create OSL Master.':
         'No active items loaded. Check Configuration → OSL Master.';
       menu.appendChild(empty);
     }
     menu.hidden=false;search.setAttribute('aria-expanded','true');
   }
   const oldLabel=label;oldLabel.hidden=true;oldLabel.setAttribute('aria-hidden','true');
   const oldDescriptionLabel=description.closest('label');
   if(oldDescriptionLabel){oldDescriptionLabel.hidden=true;oldDescriptionLabel.setAttribute('aria-hidden','true');}
   masterRow.append(combo,oldLabel,add,feedback);
   form.insertBefore(masterRow,form.firstChild.nextSibling);
   // Search shows immediately, even if master catalog API has not responded yet.
   if(select.value){const o=select.options[select.selectedIndex];search.value=o?.dataset?.name||description.value||'';}
   else search.value=description.value||'';
   search.addEventListener('focus',showOptions);
   search.addEventListener('click',showOptions);
   search.addEventListener('input',()=>{
     const exact=optionRows().find(o=>normal(o.dataset.name)===normal(search.value)||normal(o.textContent)===normal(search.value));
     if(exact){selectService(exact);return;}
     select.value='';description.value=search.value.trim();
     hint.textContent='Select a Master item from the list or create a new one.';
     search.setCustomValidity('');showOptions();
   });
   search.addEventListener('keydown',e=>{
     if(e.key==='Escape'){closeSearch();return;}
     if(e.key==='ArrowDown'||e.key==='ArrowUp'){
       e.preventDefault();if(menu.hidden)showOptions();
       const rows=Array.from(menu.querySelectorAll('button.am242-option'));
       if(rows.length){
         activeOption=(activeOption+(e.key==='ArrowDown'?1:-1)+rows.length)%rows.length;
         rows.forEach((b,i)=>b.classList.toggle('active',i===activeOption));
         rows[activeOption].scrollIntoView({block:'nearest'});
       }
     }
     if(e.key==='Enter'&&!menu.hidden){
       const row=menu.querySelectorAll('button.am242-option')[activeOption];
       if(row){e.preventDefault();row.click();}
     }
   });
   // The AM241 live catalog replaces select options after AJAX: refresh matches.
   new MutationObserver(()=>{
     const found=optionRows().find(o=>o.value===select.value);
     if(found){search.value=found.dataset.name||'';description.value=search.value;}
     if(!menu.hidden)showOptions();
   }).observe(select,{childList:true});
   select.addEventListener('change',()=>{
     const o=select.options[select.selectedIndex];
     if(o?.dataset?.name){search.value=o.dataset.name;description.value=o.dataset.name;search.setCustomValidity('');}
   });
   // Existing Create handler uses description.value; keep it synced to typed search.
   add.addEventListener('click',()=>{description.value=search.value.trim();},{capture:true});
   document.addEventListener('pointerdown',e=>{if(!combo.contains(e.target))closeSearch();});
   form.addEventListener('submit',e=>{
     const isEdit=/\/\d+\/?$/.test(form.action);
     if(!isEdit&&!select.value){
       e.preventDefault();e.stopImmediatePropagation();
       hint.textContent='Select an OSL Master service first, or use + Create OSL Master.';
       search.setCustomValidity('Select an active OSL Master item.');
       search.reportValidity();search.focus();showOptions();
     }
   },true);
"""
new=old.replace(anchor,code,1)
# Retire AM243 late-injected JS to avoid double UI mounting; preserve its visibility styles.
retired="""<script id="am243-jc-osl-one-field-inline">{!! file_get_contents(public_path('js/am242-jc-osl-one-field.js')) !!}</script>"""
if new.count(retired)!=1:raise SystemExit('AM244_STOP_PREVIOUS_INLINE_SOURCE_UNEXPECTED')
new=new.replace(retired,"{{-- AM244: AM242 late-JS injector retired; core constructor owns one search input. --}}",1)
if new.count(marker)!=1 or new.count('am244-core-osl-search')!=1:
 raise SystemExit('AM244_STOP_DUPLICATE_MARKER_OR_COMBO')
if new.count("masterRow.append(combo,oldLabel,add,feedback)")!=1 or new.count('am241-jc-osl-master-live')!=1:
 raise SystemExit('AM244_STOP_SOURCE_INTEGRITY')
with tempfile.TemporaryDirectory(prefix='am244_qa_') as temp:
 d=Path(temp);src=d/'show.blade.php';out=d/'compiled.php';helper=d/'compile.php'
 src.write_text(new,encoding='utf8',errors='surrogateescape')
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 p=subprocess.run(['php',str(helper),str(root),str(src),str(out)],cwd=root,capture_output=True,text=True,timeout=65)
 if p.returncode:raise SystemExit('AM244_BLADE_COMPILE_FAILED '+p.stderr[-520:])
 p=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=35)
 if p.returncode:raise SystemExit('AM244_COMPILED_PHP_LINT_FAILED '+(p.stdout+p.stderr)[-520:])
 compiled=read(out)
 if "masterRow.append(combo,oldLabel,add,feedback)" not in compiled:
  raise SystemExit('AM244_COMPILED_VIEW_LACKS_INLINE_SEARCH_CONSTRUCTOR')
 # Inline JS is checked in source without the Blade directives.
 jsPart=new[new.index("   // AM244_OSL_CORE_SEARCH:"):new.index("   select.addEventListener('change'",new.index("   // AM244_OSL_CORE_SEARCH:"))]
 if not jsPart.strip():raise SystemExit('AM244_STOP_JS_MISSING')
print('AM244_PHP_BLADE_CORE_SEARCH_PREFLIGHT_PASS')
if mode=='check':
 print('AM244_CHECK_ONLY_NO_WRITES');sys.exit(0)
if backup.exists() or read(view)!=old:raise SystemExit('AM244_STOP_BACKUP_COLLISION_OR_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
before.write_text(old,encoding='utf8',errors='surrogateescape');os.chmod(before,0o600)
try:
 atomic(view,new)
 manifest.write_text(json.dumps({'after':sha(new)}),encoding='ascii');os.chmod(manifest,0o600)
 for command in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  p=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=90)
  if p.returncode:raise RuntimeError('AM244_VIEW_CACHE_ERROR '+p.stderr[-420:])
except BaseException as e:
 atomic(view,old)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM244_DEPLOY_ROLLED_BACK '+str(e))
print('AM244_OSL_SEARCH_SAME_CONSTRUCTOR_AS_CREATE_DEPLOYED_NO_DB_WRITES')
print('AM244_AUTHENTICATED_BROWSER_QA_PENDING')
