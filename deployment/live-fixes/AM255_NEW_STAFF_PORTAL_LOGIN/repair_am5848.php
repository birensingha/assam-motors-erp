<?php
declare(strict_types=1);

/**
 * AM255 — one-time guarded account recovery for the new active Staff Master.
 * This script does not know, reveal, reset or change the password, and it does
 * not change attendance/check-in data. Run ONLY after a fresh STAGING backup.
 */
if (PHP_SAPI !== 'cli' || ($argc ?? 0) !== 2) {
    fwrite(STDERR, "STOP_AM255_CLI_USAGE\n"); exit(2);
}
$root = realpath((string)$argv[1]);
if (!$root || basename($root) !== 'assam-erp-staging' || !is_file($root.'/artisan')) {
    fwrite(STDERR, "STOP_AM255_NOT_STAGING\n"); exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!str_contains((string)config('app.url'), 'staging.assammotors.com')
    || !Schema::hasTable('erp_auth_users') || !Schema::hasTable('staff')) {
    fwrite(STDERR, "STOP_AM255_STAGING_DATABASE_GUARD\n"); exit(2);
}
try {
    $result = DB::transaction(static function (): string {
        $staff = DB::table('staff')->where('id',9)->lockForUpdate()->first();
        if (!$staff || $staff->staff_code !== 'AM5848' ||
            strtolower(trim((string)$staff->status)) !== 'active') {
            throw new RuntimeException('STOP_AM255_EXPECTED_ACTIVE_STAFF_AM5848_NOT_FOUND');
        }
        $email = strtolower(trim((string)$staff->email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>180) {
            throw new RuntimeException('STOP_AM255_STAFF_EMAIL_INVALID');
        }
        if (DB::table('staff')->whereRaw('LOWER(email)=?',[$email])->count() !== 1) {
            throw new RuntimeException('STOP_AM255_STAFF_EMAIL_NOT_UNIQUE');
        }
        $hash = (string)$staff->password;
        if (!in_array((string)(password_get_info($hash)['algoName'] ?? ''), ['bcrypt','argon2i','argon2id'], true)) {
            throw new RuntimeException('STOP_AM255_STAFF_PASSWORD_NOT_A_SUPPORTED_HASH');
        }

        $byStaff = DB::table('erp_auth_users')->where('staff_id',9)->first();
        $byEmail = DB::table('erp_auth_users')->whereRaw('LOWER(email)=?',[$email])->first();
        if ($byStaff || $byEmail) {
            if (!$byStaff || !$byEmail || (int)$byStaff->id !== (int)$byEmail->id ||
                (int)$byStaff->staff_id !== 9 ||
                strtolower(trim((string)$byStaff->role)) !== 'staff' ||
                (int)$byStaff->active !== 1) {
                throw new RuntimeException('STOP_AM255_EXISTING_LOGIN_CONFLICT_NO_OVERWRITE');
            }
            return 'AM255_NEW_STAFF_ACCOUNT_ALREADY_LINKED_AND_ACTIVE user_id='.$byStaff->id;
        }

        // Copy a genuine preexisting password HASH; never print or change it.
        $id = DB::table('erp_auth_users')->insertGetId([
            'name' => (string)$staff->name,
            'email' => $email,
            'password' => $hash,
            'role' => 'staff',
            'staff_id' => 9,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return 'AM255_NEW_STAFF_AM5848_PORTAL_AND_APP_ACCOUNT_LINKED user_id='.$id;
    },2);
    echo $result."\n";
    $status = DB::table('erp_auth_users')->where('staff_id',9)->first(['id','staff_id','role','active']);
    if (!$status || $status->role !== 'staff' || (int)$status->active !== 1) {
        throw new RuntimeException('STOP_AM255_POST_COMMIT_AUTH_IDENTITY_VERIFICATION_FAILED');
    }
    echo "AM255_NEW_STAFF_ACCOUNT_VERIFIED_NO_ATTENDANCE_CHANGES\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(2);
}
