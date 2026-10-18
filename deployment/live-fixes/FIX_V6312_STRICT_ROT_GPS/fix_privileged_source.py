#!/usr/bin/env python3
"""Fix v6.3.12 native login-location source compatibility for Admin/HR."""
import argparse,os,pathlib,subprocess,tempfile,datetime,shutil
p=argparse.ArgumentParser();p.add_argument('--root',required=True);p.add_argument('--apply',action='store_true');a=p.parse_args()
root=pathlib.Path(a.root)
assert (root/'artisan').is_file()
source=root/'app/Http/Controllers/Api/V1/StaffLocationController.php'
s=source.read_text()
old="if (($data['source']??null) !== 'ANDROID_FOREGROUND_NATIVE_60S') {"
new="if (($data['source']??null) !== 'ANDROID_FOREGROUND_NATIVE_60S' && !$loginGps) {"
if s.count(new)==1:
 print('V6312_PRIVILEGED_GPS_COMPAT=ALREADY_PRESENT')
 exit()
assert s.count(old)==1,'Refuse unknown live privileged GPS guard'
assert "$loginGps = $request->header('X-Assam-Motors-App') === 'Android/6.3.12'" in s
patched=s.replace(old,new,1)
fd,name=tempfile.mkstemp(prefix='.v6312-privileged-',suffix='.php',dir=source.parent);os.close(fd)
temp=pathlib.Path(name);temp.write_text(patched)
try:
 proc=subprocess.run(['php','-l',str(temp)],capture_output=True,text=True)
 assert proc.returncode==0,proc.stderr
 print('V6312_PRIVILEGED_GPS_COMPAT_PHP_LINT=PASS')
 if a.apply:
  backup=root/'storage/app/patch-backups'/('v6312-gps-compat-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
  backup.mkdir(parents=True,mode=0o700)
  (backup/'StaffLocationController.php').write_text(s)
  shutil.copyfile(temp,source)
  proc=subprocess.run(['php','-l',str(source)],capture_output=True,text=True)
  if proc.returncode!=0:
   source.write_text(s);raise RuntimeError('Rolled back failed live PHP lint')
  print('V6312_PRIVILEGED_GPS_COMPAT=APPLIED')
 else:print('V6312_PRIVILEGED_GPS_COMPAT_DRY_RUN=PASS')
finally:
 temp.unlink(missing_ok=True)
