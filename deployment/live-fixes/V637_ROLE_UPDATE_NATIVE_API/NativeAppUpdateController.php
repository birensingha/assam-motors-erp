<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NativeAppUpdateController extends Controller
{
    // Same exact signed release and 15-minute HMAC download contract as the
    // existing protected legacy Staff updater, but native Laravel auth:Sanc­tum
    // supports Staff, Owner, Admin and HR without their legacy Staff JWT.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active, 403, 'Active Assam Motors ERP login required.');

        abort_unless(strtolower(trim((string)$request->query('platform', 'android'))) === 'android', 422, 'Unsupported platform.');

        $current = max(0, (int)($request->query('version_code') ?? 0));
        $webRoot = '/home/u956497103/domains/assammotors.com/public_html/staging';
        $manifestFile = $webRoot.'/staff-app/latest.json';
        abort_unless(is_file($manifestFile), 503, 'Official Android update metadata unavailable.');

        $meta = json_decode((string)file_get_contents($manifestFile), true);
        abort_unless(is_array($meta), 503, 'Invalid official Android update metadata.');
        $code = (int)($meta['version_code'] ?? 0);
        $name = trim((string)($meta['version_name'] ?? ''));
        $file = trim((string)($meta['apk_file'] ?? ''));
        $sha = strtolower(trim((string)($meta['sha256'] ?? '')));
        abort_unless(
            ($meta['package_id'] ?? null) === 'com.assammotors.staff'
            && $code > 0 && $name !== '' && $file !== ''
            && basename($file) === $file
            && preg_match('/^[a-f0-9]{64}$/D', $sha) === 1,
            503,
            'Incomplete official Android release.'
        );

        $apk = $webRoot.'/staff-app/private/'.$file;
        abort_unless(is_file($apk) && filesize($apk) > 500000, 503, 'Official signed Android APK unavailable.');
        abort_unless(hash_equals($sha, strtolower((string)hash_file('sha256', $apk))), 503, 'Official Android APK checksum mismatch.');

        // The native controller is server-side only. It loads exactly the
        // existing legacy HMAC key (never exports it to the device).
        if (!defined('JWT_SECRET')) {
            require_once $webRoot.'/legacy/api/config.php';
        }
        abort_unless(defined('JWT_SECRET') && is_string(JWT_SECRET) && JWT_SECRET !== '', 503, 'Official signed download channel unavailable.');

        $expires = time() + 900;
        $sig = hash_hmac('sha256', $file.'|'.$expires, JWT_SECRET);
        $url = 'https://staging.assammotors.com/legacy/api/staff-app-download.php?file='
            .rawurlencode($file).'&expires='.$expires.'&sig='.$sig;

        return response()->json([
            'ok' => true,
            'update_available' => $code > $current,
            'current_version_code' => $current,
            'latest' => [
                'package_id' => 'com.assammotors.staff',
                'version_name' => $name,
                'version_code' => $code,
                'download_url' => $url,
                'sha256' => $sha,
                'mandatory' => (bool)($meta['mandatory'] ?? false),
                'channel' => (string)($meta['channel'] ?? 'production'),
                'published_at' => (string)($meta['published_at'] ?? ''),
            ],
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
