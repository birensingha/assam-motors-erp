#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CORE.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"

echo "== ROUTES CORE =="
php artisan route:list 2>&1 | grep -Ei 'erp/job-cards|erp/invoices|invoice|billing' | grep -Ei 'complete|close|ready|quality|qc|invoice|billing' | head -n 120 || true
echo

echo "== JOB CARD METHODS CORE =="
grep -nEi -A45 -B8 'public function (complete|close|mark|ready|quality|qc|invoice|convert)|customer_public_status|completed_at|closed_at|invoice_id' app/Http/Controllers/Web/AdminJobCardPageController.php | head -n 520 || true
echo

echo "== SERVICE METHODS CORE =="
grep -nEi -A55 -B10 'public function (complete|close|mark|ready|quality|qc|invoice|convert)|customer_public_status|completed_at|closed_at|invoice_id' app/Services/JobCardService.php | head -n 620 || true
echo

echo "== INVOICE SERVICE CORE =="
grep -Rni --include='*.php' -E 'public function .*invoice|public function .*convert|job_card_id|invoice_id|closed_at' app/Http/Controllers/Web/*Invoice*.php app/Services/*Invoice*.php 2>/dev/null | head -n 520 || true
echo

echo "== COLUMNS CORE =="
php <<'PHP' || true
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$wanted=["status","customer_public_status","completed_at","closed_at","invoice_id","ready_at","quality_check_at","qc_status","quality_status","delivered_at"];
$cols=DB::select("SHOW COLUMNS FROM job_cards");
$map=[]; foreach($cols as $c){$map[$c->Field]=$c;}
foreach($wanted as $k) echo $k."=". (isset($map[$k]) ? "YES" : "NO") . PHP_EOL;
PHP
echo
echo "CORE COMPLETE"
