#!/usr/bin/env bash
# ASSAM MOTORS STAGING: read-only code/schema inspection for audited ROT cancellation.
set -u
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
printf '\n=== A. CONTROLLER / ROUTE ===\n'
sed -n '1,170p' app/Http/Controllers/Web/AdminLabourLineAuditController.php
printf '\n=== B. TAX TOTAL SERVICE ===\n'
tax=$(find app -type f -name 'JobCardTaxTotals.php' -print -quit 2>/dev/null)
if [ -n "$tax" ]; then echo "File: $tax"; sed -n '1,185p' "$tax"; else echo 'JobCardTaxTotals source not found'; fi
printf '\n=== C. AMOUNT EDITOR VIEW - FIRST 160 LINES ===\n'
sed -n '1,160p' resources/views/job-cards/rot-alteration.blade.php
printf '\n=== D. JOB CARD ROT ACTIONS / ESTIMATE FLAGS ===\n'
grep -nE -C 2 'labour.delete-admin|labour.review-admin|data-am92-locked|data-am92-source|am92-delete|source_estimate_item_id' resources/views/job-cards/show.blade.php | head -105
printf '\n=== E. DB TABLE COLUMN NAMES ONLY (NO ROW DATA) ===\n'
php <<'PHP'
<?php
try {
    require getcwd() . '/vendor/autoload.php';
    $app=require getcwd() . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach(['job_card_labour','rot_sessions','rot_mechanic_segments','job_card_change_audits','estimate_items','job_card_estimate_items','estimates'] as $t){
        try {
            if(\Illuminate\Support\Facades\Schema::hasTable($t)){
                echo $t.': '.implode(', ', \Illuminate\Support\Facades\Schema::getColumnListing($t)).PHP_EOL;
            }else echo $t.": MISSING\n";
        }catch(\Throwable $e){echo $t.": READ ERROR (".get_class($e).")\n";}
    }
} catch(\Throwable $e) {echo 'Schema metadata unavailable: '.get_class($e).PHP_EOL;}
PHP
printf '\nREAD-ONLY PREFLIGHT COMPLETE (no application code or DB records changed)\n'