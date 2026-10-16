#!/usr/bin/env bash
# Only signed v6.3.5 under Staging Staff App protected update channel.
set -euo pipefail
SITE="${1:?Give staging staff-app root}"
cd "$SITE"
test -f latest.json
test -f ../legacy/api/staff-app-update.php
test -f private/Assam-Motors-Staff-v6.3.5-INSPECTION-WORK-FIX-SIGNED.apk
test "$(wc -c < private/Assam-Motors-Staff-v6.3.5-INSPECTION-WORK-FIX-SIGNED.apk)" = 3336948
echo "70036733ac3e1490cc1ef032f122f3d70074ffa52f5f7508bad306526e72ce2d  private/Assam-Motors-Staff-v6.3.5-INSPECTION-WORK-FIX-SIGNED.apk" | sha256sum -c -
php -l ../legacy/api/staff-app-update.php
python3 - <<'PY'
import os,json,hashlib,tempfile
from pathlib import Path
from datetime import datetime,timezone
root=Path.cwd()
manifest=root/'latest.json'
data=json.loads(manifest.read_text())
assert data.get('package_id')=='com.assammotors.staff'
old=int(data.get('version_code',0))
assert old in (60304,60305), 'Unexpected current production build, refusing overwrite'
name='Assam-Motors-Staff-v6.3.5-INSPECTION-WORK-FIX-SIGNED.apk'
expected='70036733ac3e1490cc1ef032f122f3d70074ffa52f5f7508bad306526e72ce2d'
file=root/'private'/name
assert file.is_file() and file.stat().st_size==3336948
assert hashlib.sha256(file.read_bytes()).hexdigest()==expected
if old==60305:
    assert data['sha256']==expected and data['apk_file']==name, 'Release checksum mismatch'
    print('V635_RELEASE_MANIFEST=ALREADY_PUBLISHED')
else:
    stamp=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup=root/('latest.json.backup-before-v635-'+stamp)
    backup.write_bytes(manifest.read_bytes())
    backup.chmod(0o644)
    notes='ASSAM-MOTORS-STAFF-v6.3.5-INSPECTION-FIX.txt'
    (root/notes).write_text(
        'ASSAM MOTORS STAFF APP v6.3.5\n'
        'My Inspections: Open assigned Job Card and complete 10-point checklist.\n'
        'Show failing ERP module on connection errors. Preserves login, GPS and outbox.\n'
        'Staging ERP database now uses verified local Unix socket.\n'
        'Installation on real Staff phone must be verified before final sign-off.\n')
    data.update({
        'version_code':60305,'version_name':'6.3.5','apk_file':name,'sha256':expected,
        'release_notes_file':notes,
        'published_at':datetime.now(timezone.utc).isoformat(timespec='seconds').replace('+00:00','Z')
    })
    fd,tmp=tempfile.mkstemp(dir=root,prefix='.v635-manifest-')
    try:
        with os.fdopen(fd,'w') as stream:
            json.dump(data,stream,indent=2)
            stream.write('\n')
        os.chmod(tmp,0o644)
        assert json.loads(Path(tmp).read_text())['version_code']==60305
        os.replace(tmp,manifest)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    print('V635_MANIFEST_BACKUP='+backup.name)
    print('V635_RELEASE_MANIFEST=PUBLISHED')
read=json.loads(manifest.read_text())
assert int(read['version_code'])==60305
assert read['sha256']==expected
assert read['apk_file']==name
assert read.get('licence_file'), 'Existing license metadata was lost'
print('V635_UPDATE_CHANNEL=VERIFIED_SIGNED_APK_60305')
PY
if [ -f v635-upload-once.php ]; then
  grep -Fq '70036733ac3e1490cc1ef032f122f3d70074ffa52f5f7508bad306526e72ce2d' v635-upload-once.php
  rm -f v635-upload-once.php
  echo "V635_TEMP_UPLOAD_RECEIVER=REMOVED"
fi
echo "V635_HOSTINGER_PUBLISH=VERIFIED"
