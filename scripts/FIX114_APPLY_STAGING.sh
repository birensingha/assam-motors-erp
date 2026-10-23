#!/usr/bin/env bash
# Assam Motors STAGING: remove duplicate Change Mechanic modal controls.
set -Eeuo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
f='resources/views/job-cards/show.blade.php'
expected='9dffb0849b3857a4ff5e648ecd54a8f5f950aabb7bc471ea21cbed029a59cf2e'
actual="$(sha256sum "$f" | awk '{print $1}')"
if [ "$actual" != "$expected" ]; then echo "STOP: Changed view ($actual). No edits."; exit 1; fi
mkdir -p storage/app/am-backups
backup="storage/app/am-backups/show_FIX114_$(date +%Y%m%d_%H%M%S).blade.php"
cp -p "$f" "$backup"
tmp="$f.fix114.$$.tmp"
cp -p "$f" "$tmp"
changed=0
cleanup(){ status=$?; trap - EXIT; if [ "$status" -ne 0 ] && [ "$changed" -eq 1 ]; then cp -p "$backup" "$f"; php artisan view:clear || true; echo 'ROLLBACK: restored prior Job Card view.'; fi; rm -f "$tmp"; exit "$status"; }
trap cleanup EXIT
export FIX114_TEMP="$tmp"
php <<'PHP'
<?php
$f=getenv('FIX114_TEMP');$s=file_get_contents($f);
$old='        if(oldCombo)oldCombo.replaceWith(picker);else oldSelect.replaceWith(picker);';
$new=<<<'JS'
        // FIX114: Remove BOTH old controls from the New Mechanic label.
        var mechanicLabel=oldSelect.closest('label');
        if(mechanicLabel){
          mechanicLabel.replaceChildren(document.createTextNode('New Mechanic'),picker);
        }else if(oldCombo)oldCombo.replaceWith(picker);else oldSelect.replaceWith(picker);
JS;
if(substr_count($s,$old)!==1 || strpos($s,'FIX112: One picker per actual dialog')===false){fwrite(STDERR,"STOP: popup code mismatch; nothing replaced.\n");exit(1);}
if(file_put_contents($f,str_replace($old,$new,$s))===false){fwrite(STDERR,"STOP: cannot write temporary view\n");exit(1);}
echo "FIX114 ready.\n";
PHP
cmp -s "$backup" "$f" || { echo 'STOP: live view modified concurrently'; exit 1; }
changed=1
mv -f "$tmp" "$f"
php artisan view:clear
php artisan view:cache
compiled="$(grep -l 'FIX114: Remove BOTH old controls' storage/framework/views/*.php 2>/dev/null | head -n 1 || true)"
[ -n "$compiled" ] || { echo 'STOP: compiled FIX114 view not found'; exit 1; }
php -l "$compiled"
echo "FIX114 VIEW VALIDATION PASS"
echo "Backup: $backup"
