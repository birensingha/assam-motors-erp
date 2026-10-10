<?php
declare(strict_types=1);

require_once __DIR__.'/config.php';
date_default_timezone_set('Asia/Kolkata');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function amStaffUpdateAuth(): array {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization']
        ?? $headers['authorization']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));

    if (!preg_match('/Bearer\s+(.+)$/i', (string)$auth, $m)) {
        sendJson(['error' => 'Staff login required'], 401);
    }

    $parts = explode('.', $m[1]);
    if (count($parts) !== 2) {
        sendJson(['error' => 'Invalid staff session'], 401);
    }

    [$data, $sig] = $parts;
    $expected = hash_hmac('sha256', $data, JWT_SECRET);
    if (!hash_equals($expected, $sig)) {
        sendJson(['error' => 'Invalid staff session'], 401);
    }

    $payload = json_decode(base64_decode($data), true);
    if (!$payload || empty($payload['staffId']) || (($payload['exp'] ?? 0) < time())) {
        sendJson(['error' => 'Staff session expired'], 401);
    }

    return $payload;
}

amStaffUpdateAuth();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$platform = strtolower(trim((string)($_GET['platform'] ?? 'android')));
if ($platform !== 'android') {
    sendJson(['error' => 'Unsupported platform'], 422);
}

$currentBuild = max(0, (int)($_GET['version_code'] ?? 0));
$docroot = dirname(__DIR__, 2);
$metaPath = $docroot.'/staff-app/latest.json';

if (!is_file($metaPath)) {
    sendJson(['error' => 'Update metadata not published'], 503);
}

$raw = file_get_contents($metaPath);
$meta = json_decode((string)$raw, true);
if (!is_array($meta)) {
    sendJson(['error' => 'Invalid update metadata'], 503);
}

$latestBuild = max(0, (int)($meta['version_code'] ?? 0));
$versionName = trim((string)($meta['version_name'] ?? ''));
$downloadUrl = trim((string)($meta['download_url'] ?? ''));
$sha256 = strtolower(trim((string)($meta['sha256'] ?? '')));

if ($latestBuild <= 0 || $versionName === '' || $downloadUrl === '') {
    sendJson(['error' => 'Incomplete update metadata'], 503);
}

$latest = [
    'package_id' => (string)($meta['package_id'] ?? 'com.assammotors.staff'),
    'version_name' => $versionName,
    'version_code' => $latestBuild,
    'download_url' => $downloadUrl,
    'sha256' => $sha256,
    'mandatory' => (bool)($meta['mandatory'] ?? false),
    'channel' => (string)($meta['channel'] ?? 'production'),
    'published_at' => (string)($meta['published_at'] ?? ''),
    'release_notes' => (string)($meta['release_notes'] ?? ''),
];

sendJson([
    'ok' => true,
    'update_available' => $latestBuild > $currentBuild,
    'current_version_code' => $currentBuild,
    'latest' => $latest,
]);
