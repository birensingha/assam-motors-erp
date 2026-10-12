<?php
/** Assam Motors Staff Portal + HR ERP Android-compatible UI theme. STAGING ONLY.
 *  Does not touch authentication, API, salaries, attendance or ROT data. */
declare(strict_types=1);
function stopTheme(string $message, int $status=1): never {
    fwrite($status ? STDERR : STDOUT, $message . "\n");
    exit($status);
}
$action = $argv[1] ?? '';
if (!in_array($action, ['check', 'apply'], true)) stopTheme('Usage: php APPLY_STAFF_ANDROID_THEME.php check|apply');
$home = rtrim((string)getenv('HOME'), '/');
$root = $home.'/domains/assammotors.com/assam-erp-staging';
$cssPath = __DIR__.'/assam-staff-android-theme.css';
if (!$home || realpath($root)!==$root || !is_file($root.'/artisan')) stopTheme('NO-GO: canonical staging Laravel root missing');
if (!is_file($cssPath)) stopTheme('NO-GO: Android theme CSS missing');
$css = file_get_contents($cssPath);
if ($css===false || strlen($css)<6000 || !str_contains($css,'ASSAM_MOTORS_STAFF_ANDROID_THEME_V1') || substr_count($css,'{')!==substr_count($css,'}')) {
    stopTheme('NO-GO: theme CSS invalid');
}
$targets = [
    'resources/views/staff/portal.blade.php'=>'b9a574fe55f0e64b58c1e64e2598aac819de6ed81ba9b77708a92c622a6106a8',
    'resources/views/hr/dashboard.blade.php'=>'9d74a437e074c2cc6e5f64e557cbded71ebd25169cccbdf016be455cbf9118cf',
    'resources/views/staff/hr/policies.blade.php'=>'9a08162a149c49470b05b1c3b5e55e01f913bc4302bb0a44c37202c7e4f5c1c1',
];
$changes=[];$marker='assam-motors-staff-android-theme-v1';
$style="\n<style id=\"{$marker}\">\n{$css}\n</style>\n";
foreach ($targets as $rel=>$expectedSha) {
    $file=$root.'/'.$rel;
    if (!is_file($file) || is_link($file)) stopTheme('NO-GO: expected view missing/symlink: '.$rel);
    $raw=file_get_contents($file);
    if ($raw===false) stopTheme('NO-GO: cannot read '.$rel);
    if (str_contains($raw,$marker)) stopTheme('ALREADY UPDATED: '.$rel.'; no reapply');
    if (!hash_equals($expectedSha,hash('sha256',$raw))) stopTheme('NO-GO: unexpected source SHA in '.$rel.'; leave as-is');
    if (substr_count(strtolower($raw),'</head>')!==1) stopTheme('NO-GO: invalid head markup in '.$rel);
    $index=stripos($raw,'</head>');
    $modified=substr($raw,0,$index).$style.substr($raw,$index);
    if ($rel==='resources/views/staff/portal.blade.php') {
        if (substr_count($modified,'</header>')!==1 || !str_contains($modified,'class="staff-topbar"')) stopTheme('NO-GO: Staff Portal header mismatch');
        $nav=<<<'BLADE'
<nav class="staff-top-actions" aria-label="Staff and HR shortcuts">
  <a href="{{ route('staff.hr.policies') }}">📋 HR Policies</a>
  @if(\App\Support\HrAccess::permitted(auth()->user()))
    <a href="{{ route('erp.hr.dashboard') }}" class="staff-hr-shortcut">👥 HR Management</a>
  @endif
</nav>
BLADE;
        $modified=str_replace('</header>',"\n".$nav."\n</header>",$modified);
    }
    $changes[$rel]=['old'=>$raw,'new'=>$modified,'sha'=>hash('sha256',$modified)];
}
echo 'HR/STAFF ANDROID THEME PRECHECK PASS; files='.count($changes).' css_sha256='.hash('sha256',$css)."\n";
foreach ($changes as $name=>$v) echo 'READY '.$name.' result_sha256='.$v['sha']."\n";
if ($action==='check') stopTheme('READ ONLY PASS — no files changed.',0);
$backup = $root.'/storage/app/hr_staff_android_theme_backup_'.date('Ymd_His').'_'.getmypid();
if (!@mkdir($backup,0700,true)) stopTheme('NO-GO: cannot create protected backup folder');
$installed=[];
try {
    // All original files backed up before first write.
    foreach ($changes as $rel=>$v) {
        $target=$root.'/'.$rel;
        $backupFile=$backup.'/'.str_replace('/','_',$rel);
        if (!copy($target,$backupFile) || hash_file('sha256',$backupFile)!==hash('sha256',$v['old'])) {
            throw new RuntimeException('backup verification failed: '.$rel);
        }
    }
    foreach ($changes as $rel=>$v) {
        $target=$root.'/'.$rel;
        if (hash_file('sha256',$target)!==hash('sha256',$v['old'])) throw new RuntimeException('concurrent source edit: '.$rel);
        $temp=tempnam(dirname($target),'.am_theme_');
        if (!$temp) throw new RuntimeException('cannot make temporary file: '.$rel);
        $written=file_put_contents($temp,$v['new'],LOCK_EX);
        if ($written!==strlen($v['new'])) { @unlink($temp); throw new RuntimeException('short write: '.$rel); }
        chmod($temp,fileperms($target)&0777);
        if (!rename($temp,$target)) { @unlink($temp); throw new RuntimeException('cannot install: '.$rel); }
        $installed[]=$rel;
        if (hash_file('sha256',$target)!==$v['sha']) throw new RuntimeException('post-write hash mismatch: '.$rel);
    }
} catch (Throwable $e) {
    foreach ($installed as $rel) copy($backup.'/'.str_replace('/','_',$rel),$root.'/'.$rel);
    stopTheme('THEME NO-GO; source rollback attempted: '.$e->getMessage());
}
echo 'APPLIED ASSAM_MOTORS_STAFF_ANDROID_THEME_V1; BACKUP='.$backup."\n";
echo 'NO STAFF DATA, PAYROLL, AUTH, ROT, APK OR MIGRATION CHANGED.'."\n";