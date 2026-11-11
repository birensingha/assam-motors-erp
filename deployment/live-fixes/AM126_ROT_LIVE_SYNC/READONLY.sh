#!/usr/bin/env bash
# AM126 focused staging-only READONLY source/clock-value inspection.
set -Eeuo pipefail
cd "$1"; test -f artisan
echo 'AM126_READONLY_START'
for f in \
  resources/views/dashboard/partials/rot-live-clock.blade.php \
  resources/views/job-cards/show.blade.php \
  app/Http/Controllers/Web/JobCardRotAlterationController.php \
  app/Services/RotWorkflowService.php \
  app/Http/Controllers/Web/AdminRotTimelineController.php; do
  echo "=== FILE $f ==="
  if ! test -f "$f"; then echo MISSING;continue;fi
  echo "FILE_LINES=$(wc -l < "$f")"
  case "$f" in
    *rot-live-clock*)
      # Show entire narrowly scoped partial, bounded 320 lines. It contains presentation only.
      sed -n '1,320p' "$f" | cut -c1-240 ;;
    *show.blade.php)
      # Relevant ROT card rows with context; avoid unrelated confidential page data.
      grep -nEi -m 35 'rot-live-clock|am83RotLabour|rotSessions|rot\.status|first_start_at|total_seconds|am-rot-live|rot_session_id|rot-live' "$f" | cut -c1-240 || true ;;
    *JobCardRotAlterationController*)
      # Full bounded save logic from known controller.
      sed -n '35,275p' "$f" | cut -c1-240 ;;
    *RotWorkflowService*)
      grep -nEi -m 38 'first_start_at|end_time|total_seconds|productive_seconds|rot_start|rot_end|std_hours|complete|session.*status' "$f" | cut -c1-240 || true ;;
    *)
      grep -nEi -m 35 'first_start_at|end_time|total_seconds|labour_line|rot_start|rot_end|rot_sessions' "$f" | cut -c1-240 || true ;;
  esac
done
echo '=== ROT-CUS-0198 read-only time comparison ==='
php <<'PHP'
<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$rot='ROT-CUS-0198';
foreach(DB::table('job_card_labour')->where('rot_code',$rot)->orderByDesc('id')->limit(5)->get() as $line) {
  echo 'LINE_ID='.$line->id.' JC_ID='.$line->job_card_id.' CODE='.$line->rot_code."\n";
  foreach(['rot_start','rot_end','total_hours','productive_seconds','standard_hours','execution_status','rot_session_id','mechanic_id'] as $k)
    echo 'LINE_'.strtoupper($k).'='.(string)($line->{$k}??'')."\n";
  foreach(DB::table('rot_sessions')->where('job_card_id',$line->job_card_id)->where('labour_line_id',$line->id)->limit(5)->get() as $s){
    echo 'SESSION_ID='.$s->id."\n";
    foreach(['status','first_start_at','end_time','start_time','completed_at','total_seconds','std_hours','staff_id'] as $k)
      echo 'SESSION_'.strtoupper($k).'='.(string)($s->{$k}??'')."\n";
  }
}
echo "AM126_DB_READONLY_COMPLETE\n";
PHP
echo 'AM126_READONLY_COMPLETE_NO_WRITES'
