<?php
declare(strict_types=1);

require_once __DIR__.'/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}

$file = basename(trim((string)($_GET['file'] ?? '')));
$expires = (int)($_GET['expires'] ?? 0);
$sig = strtolower(trim((string)($_GET['sig'] ?? '')));

if ($file === '' || $expires < time() || $sig === '') {
    http_response_code(403);
    echo 'Update link expired or invalid.';
    exit;
}

$expected = hash_hmac('sha256', $file.'|'.$expires, JWT_SECRET);
if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    echo 'Invalid update link.';
    exit;
}

$docroot = dirname(__DIR__, 2);
$metaPath = $docroot.'/staff-app/latest.json';
if (!is_file($metaPath)) {
    http_response_code(404);
    exit;
}

$meta = json_decode((string)file_get_contents($metaPath), true);
$allowedFile = basename((string)($meta['apk_file'] ?? ''));
if ($allowedFile === '' || !hash_equals($allowedFile, $file)) {
    http_response_code(403);
    exit;
}

$apkPath = $docroot.'/staff-app/private/'.$allowedFile;
if (!is_file($apkPath)) {
    http_response_code(404);
    exit;
}

$expectedSha = strtolower(trim((string)($meta['sha256'] ?? '')));
$actualSha = strtolower(hash_file('sha256', $apkPath));
if ($expectedSha === '' || !hash_equals($expectedSha, $actualSha)) {
    http_response_code(503);
    echo 'Published APK integrity check failed.';
    exit;
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="'.$allowedFile.'"');
header('Content-Length: '.filesize($apkPath));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($apkPath);
