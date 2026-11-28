#!/usr/bin/env python3
"""Targeted audited Job Card odometer edit; staging only."""
import sys,os,subprocess,tempfile,shutil
from pathlib import Path
mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
if mode not in ('check','apply') or not (root/'artisan').is_file() or not root.name.endswith('staging'):
 raise SystemExit('STOP: staging Laravel root required')
controller=root/'app/Http/Controllers/Web/AdminJobCardPageController.php'
view=root/'resources/views/job-cards/edit.blade.php'
original={p:p.read_text() for p in (controller,view)}
marker='AM_JC_ODOMETER_EDIT_V1'
if all(marker in s for s in original.values()):
 print('ODOMETER_ALREADY_APPLIED');sys.exit(0)
assert not any(marker in s for s in original.values()),'Partial install'
def once(s,a,b):
 if s.count(a)!=1:raise RuntimeError('Source drift: '+a[:100])
 return s.replace(a,b,1)
s=original[controller]
start=s.index('    public function update(Request $request, int $job): RedirectResponse')
end=s.index("->with('success','Job Card updated with audit history.');",start)
end=s.index('    }',end)+5
u=s[start:end]
assert all(x in u for x in ["$before = $card->toArray();","$after = (array)DB::table('job_cards')","$this->auditChange(","$data['reason']","lockForUpdate()","'closed','invoiced','cancelled'"])
u=once(u,"            'vehicle_variant'=>['nullable','string','max:100'],","            'vehicle_variant'=>['nullable','string','max:100'],\n            // AM_JC_ODOMETER_EDIT_V1\n            'odometer_km'=>['sometimes','nullable','integer','min:0','max:9999999'],")
u=once(u,"            $update = [];","""            $update = [];
            // AM_JC_ODOMETER_EDIT_V1: absent/blank input preserves existing reading.
            if (array_key_exists('odometer_km', $data) && $data['odometer_km'] !== null && $data['odometer_km'] !== '') {
                $update['odometer_km'] = (string)(int)$data['odometer_km'];
            }""")
s=s[:start]+u+s[end:]
v=original[view]
anchor="""    <label class="span-2">
        Floor Supervisor (Independent of Inspector &amp; Deciding Technician)"""
new="""    {{-- AM_JC_ODOMETER_EDIT_V1 --}}
    <label>
        Odometer Reading (KM)
        <input name="odometer_km" type="number" min="0" max="9999999" step="1"
               inputmode="numeric" value="{{ old('odometer_km',$job->odometer_km) }}"
               placeholder="Enter kilometer">
        @error('odometer_km')<span class="field-help" role="alert">{{ $message }}</span>@enderror
    </label>

"""+anchor
v=once(v,anchor,new)
candidate={controller:s,view:v}
with tempfile.TemporaryDirectory() as d:
 t=Path(d);php=t/'controller.php';php.write_text(s)
 subprocess.run(['php','-l',str(php)],check=True,cwd=root)
 blade=t/'edit.blade.php';blade.write_text(v);compiled=t/'compiled.php'
 compiler=r"require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();if(!\Illuminate\Support\Facades\Schema::hasColumn('job_cards','odometer_km')){exit(42);}file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"
 subprocess.run(['php','-r',compiler,str(blade),str(compiled)],check=True,cwd=root)
 subprocess.run(['php','-l',str(compiled)],check=True,cwd=root)
print('ODOMETER_PREFLIGHT_PASS_EXISTING_AUDIT_TRANSACTION_PRESERVED')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups'/marker
assert not backup.exists(),'Backup exists; manual review required'
backup.mkdir(parents=True)
for p in original:shutil.copy2(p,backup/p.name)
def write(p,data):
 fd,tmp=tempfile.mkstemp(dir=p.parent)
 try:
  with os.fdopen(fd,'w') as f:f.write(data)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
try:
 for p,data in candidate.items():write(p,data)
 subprocess.run(['php','artisan','view:clear'],check=True,cwd=root)
 for p,data in candidate.items():assert p.read_text()==data
except BaseException:
 for p,data in original.items():write(p,data)
 subprocess.run(['php','artisan','view:clear'],cwd=root)
 raise
print('ODOMETER_PATCH_APPLIED_AND_SOURCE_VERIFIED',backup)
