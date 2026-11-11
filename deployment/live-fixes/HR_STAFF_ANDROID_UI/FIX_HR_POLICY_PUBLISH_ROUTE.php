<?php
/** Assam Motors HR policy publish route repair; deployed STAGING-only source, no DB changes. */
declare(strict_types=1);
$mode = $argv[1] ?? '';
if (!in_array($mode, ['check', 'apply'], true)) { fwrite(STDERR,"Use: php FIX_HR_POLICY_PUBLISH_ROUTE.php check|apply\n"); exit(2); }
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
$canonical=realpath($root);
if (!$canonical || $canonical !== $root || !is_file($root.'/artisan')) { fwrite(STDERR,"NO-GO: not the canonical STAGING Laravel root\n"); exit(2); }
$rel='resources/views/hr/dashboard.blade.php';
$file=$root.'/'.$rel;
if (!is_file($file) || is_link($file)) { fwrite(STDERR,"NO-GO: HR view missing or unsafe\n"); exit(2); }
$actual=file_get_contents($file);
if (!is_string($actual)) { fwrite(STDERR,"NO-GO: HR view not readable\n"); exit(2); }
$expected='998dd9aded99209c7e02bb1ee3c5c2b5b4bd09c6be84fd9dccd04a4d0b5b2f7d';
$old="route('staff.hr.policies.publish', ['policy' => \$p->id])";
$new="route('erp.hr.policies.publish', ['policy' => \$p->id])";
if (str_contains($actual,$new) && !str_contains($actual,$old)) { echo "ALREADY FIXED: HR publish route present; no changes.\n";exit(0); }
if (!hash_equals($expected,hash('sha256',$actual))) { fwrite(STDERR,"NO-GO: HR view changed from reviewed theme baseline; no changes.\n");exit(2); }
if (substr_count($actual,$old)!==1 || substr_count($actual,$new)!==0) { fwrite(STDERR,"NO-GO: unexpected policy publish route pattern\n");exit(2); }
$patched=str_replace($old,$new,$actual);
if ($mode==='check') { echo "HR_POLICY_PUBLISH_ROUTE PRECHECK PASS: one exact link needs correction; no changes.\n";exit(0); }
$backup=$root.'/storage/app/hr_publish_route_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if (!copy($file,$backup) || !hash_equals($expected,hash_file('sha256',$backup))) { fwrite(STDERR,"NO-GO: backup verification failed\n");exit(2); }
$temporary=tempnam(dirname($file),'.hr_policy_route_');
if (!$temporary) { fwrite(STDERR,"NO-GO: temp file unavailable\n");exit(2); }
$length=file_put_contents($temporary,$patched,LOCK_EX);
if ($length!==strlen($patched) || !chmod($temporary,fileperms($file)&0777) || !rename($temporary,$file)) { @unlink($temporary);fwrite(STDERR,"NO-GO: unable to update HR view\n");exit(2); }
if (hash_file('sha256',$file)!==hash('sha256',$patched)) { copy($backup,$file);fwrite(STDERR,"NO-GO: post-install mismatch; source rolled back\n");exit(2); }
echo "HR_POLICY_PUBLISH_ROUTE PATCH APPLIED; backup=$backup\n";
echo "NO HR POLICY, STAFF, PAYROLL, AUTHENTICATION, ROT OR DB RECORDS CHANGED.\n";