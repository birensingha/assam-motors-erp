#!/usr/bin/env bash
# Assam Motors STAGING ONLY: allow advance reassignment for never-started ASSIGNED ROT.
# Does not modify Start/Resume, invoice locks, time logs, attendance records or money entries.
set -Eeuo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
f='app/Services/RotWorkflowService.php'
[ -f "$f" ] || { echo 'STOP: RotWorkflowService.php missing'; exit 1; }
command -v php >/dev/null || { echo 'STOP: PHP unavailable'; exit 1; }
mkdir -p storage/app/am-backups
stamp="$(date +%Y%m%d_%H%M%S)"
backup="storage/app/am-backups/RotWorkflowService_FIX113_${stamp}.php"
tmp="${f}.fix113.${stamp}.$$.tmp"
cp -p "$f" "$backup" || exit 1
cp -p "$f" "$tmp" || exit 1
changed=0
finish() {
  code=$?
  trap - EXIT
  if [ "$code" -ne 0 ] && [ "$changed" -eq 1 ]; then
    cp -p "$backup" "$f"
    echo 'FIX113 FAILED: prior RotWorkflowService.php restored from backup.'
  fi
  rm -f "$tmp"
  exit "$code"
}
trap finish EXIT
export FIX113_TEMP="$tmp"
php <<'PHP'
<?php
$f = getenv('FIX113_TEMP');
$s = file_get_contents($f);
if (!is_string($s)) { fwrite(STDERR,"STOP: Cannot read service\n"); exit(1); }
$old = <<<'OLD'
            // FIX V6312: Reassignment is a new assignment and requires Check-In.
            $this->assertStaffCheckedIn($newStaffId);

            $busy = RotSession::query()
                ->where('staff_id', $newStaffId)
                ->where('status', 'Running')
                ->where('id', '<>', $rot->id)
                ->lockForUpdate()
                ->exists();
            if ($busy) {
                throw ValidationException::withMessages(['mechanic' => 'New mechanic already has an Assigned or Running ROT.']);
            }
OLD;
$new = <<<'NEW'
            // FIX113: Advance allocation is allowed for a never-started ASSIGNED ROT.
            // It does not start work, allocate productive time, or override duty rules.
            $advanceAssignment = $rot->status === 'Assigned' && empty($rot->first_start_at);
            if (!$advanceAssignment) {
                // Reassigning an already-started ROT still needs duty Check-In.
                $this->assertStaffCheckedIn($newStaffId);

                // Preserve running-session concurrency checks on active reassignment.
                $busy = RotSession::query()
                    ->where('staff_id', $newStaffId)
                    ->where('status', 'Running')
                    ->where('id', '<>', $rot->id)
                    ->lockForUpdate()
                    ->exists();
                if ($busy) {
                    throw ValidationException::withMessages(['mechanic' => 'New mechanic has another RUNNING ROT. Pause or complete it first.']);
                }
            }
NEW;
if (substr_count($s, $old)!==1) {
    fwrite(STDERR,"STOP: Exact FIX V6312 code not found once. No changes made.\n"); exit(1);
}
foreach (['Invoiced Job Card is locked.', 'Selected mechanic is already assigned.', "['Assigned', 'Running', 'Paused']"] as $guard) {
    if (strpos($s,$guard) === false) { fwrite(STDERR,"STOP: Existing safeguard missing: $guard\n"); exit(1); }
}
$s=str_replace($old,$new,$s);
if (file_put_contents($f,$s)===false) { fwrite(STDERR,"STOP: Unable to write temporary file\n"); exit(1); }
echo "FIX113 rule patch staged in temporary file.\n";
PHP
php -l "$tmp"
cmp -s "$backup" "$f" || { echo 'STOP: Server service changed during patch preparation. No changes made.'; exit 1; }
mv -f "$tmp" "$f"
changed=1
php -l "$f"
echo 'FIX113 SOURCE + PHP SYNTAX PASS'
echo "Backup: $backup"
echo 'No database records have been changed by the installer.'
echo 'NEXT: Authenticated browser test required; not yet verified live.'
