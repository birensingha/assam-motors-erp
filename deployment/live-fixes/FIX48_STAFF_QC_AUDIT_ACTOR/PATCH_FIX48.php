<?php
/**
 * FIX48 — Staff QC audit schema compatibility, staging only.
 * The existing job_card_change_audits schema has actor_id and actor_name,
 * not the legacy actor column. Source-only fix. No migrations, no data edits.
 */
declare(strict_types=1);
$root = rtrim((string)($argv[1] ?? ''), '/');
$path = $root.'/app/Http/Controllers/Web/StaffQcController.php';
if (!is_file($root.'/artisan') || !is_file($path)) {
    fwrite(STDERR, "FIX48: incorrect staging Laravel root or controller missing\n");
    exit(2);
}
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "FIX48: controller cannot be read\n");
    exit(2);
}
$old = "                    'actor'=>'STAFF:'.\$staffId.' / '.((string)\$user->name),";
$new = "                    'actor_id'=>\$staffId,\n".
       "                    'actor_name'=>'STAFF:'.\$staffId.' / '.((string)\$user->name),";
if (str_contains($source, $new) && !str_contains($source, $old)) {
    echo "FIX48: already patched (no-op)\n";
    exit(0);
}
if (substr_count($source, $old) !== 1 || !str_contains($source, "DB::table('job_card_change_audits')->insert([")) {
    fwrite(STDERR, "FIX48: expected audit anchor not exactly present; no modifications\n");
    exit(3);
}
$changed = str_replace($old, $new, $source);
if (substr_count($changed, "'actor_id'=>\$staffId") !== 1 ||
    substr_count($changed, "'actor_name'=>'STAFF:'") !== 1) {
    fwrite(STDERR, "FIX48: unexpected replacement; no modifications\n");
    exit(3);
}
$temp = tempnam(dirname($path), '.qc_fix48_');
if ($temp === false || file_put_contents($temp, $changed) === false) {
    fwrite(STDERR, "FIX48: temp write failed\n");
    exit(4);
}
@chmod($temp, fileperms($path) & 0777);
if (!rename($temp, $path)) {
    @unlink($temp);
    fwrite(STDERR, "FIX48: atomic replace failed\n");
    exit(4);
}
echo "FIX48: staff QC audit now uses actor_id and actor_name; source patched\n";
