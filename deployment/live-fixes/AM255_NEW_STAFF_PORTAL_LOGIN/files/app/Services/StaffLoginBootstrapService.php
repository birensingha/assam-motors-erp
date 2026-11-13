<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ErpAuthUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * AM255 — reconcile a missing Staff Portal / native App login account only
 * after the ACTIVE Staff Master's original password has been verified.
 *
 * Never grants Admin or HR rights. Never changes an existing auth user or
 * linked staff ID. Existing accounts continue using the current auth path.
 */
final class StaffLoginBootstrapService
{
    public function resolve(string $email, string $plainPassword): ?ErpAuthUser
    {
        $email = strtolower(trim($email));
        if ($email === '' || strlen($email) > 180 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $plainPassword === '') {
            return null;
        }

        try {
            return DB::transaction(function () use ($email, $plainPassword): ?ErpAuthUser {
                // An existing ERP account, even inactive, must never be
                // overwritten or resurrected by Staff Master credentials.
                if (DB::table('erp_auth_users')->whereRaw('LOWER(email)=?', [$email])->exists()) {
                    return null;
                }
                $staffRows = DB::table('staff')
                    ->whereRaw('LOWER(email)=?', [$email])
                    ->lockForUpdate()->limit(2)->get();
                if ($staffRows->count() !== 1) {
                    return null;
                }
                $staff = $staffRows->first();
                if (strtolower(trim((string)($staff->status ?? ''))) !== 'active') {
                    return null;
                }
                $hash = (string)($staff->password ?? '');
                $algo = (string)(password_get_info($hash)['algoName'] ?? 'unknown');
                if (!in_array($algo, ['bcrypt','argon2i','argon2id'], true)) {
                    return null;
                }
                if (!Hash::check($plainPassword, $hash)) {
                    return null;
                }
                $staffId = (int)$staff->id;
                if ($staffId <= 0 || DB::table('erp_auth_users')->where('staff_id', $staffId)->exists()) {
                    return null;
                }
                // A second check in the lock for concurrent/legacy writers.
                if (DB::table('erp_auth_users')->whereRaw('LOWER(email)=?', [$email])->exists()) {
                    return null;
                }
                try {
                    $id = DB::table('erp_auth_users')->insertGetId([
                        'name' => (string)$staff->name,
                        'email' => $email,
                        'password' => Hash::make($plainPassword),
                        'role' => 'staff',
                        'staff_id' => $staffId,
                        'active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return ErpAuthUser::find($id);
                } catch (QueryException $e) {
                    // A concurrent create may have won unique email/staff ID.
                    // Accept it only when identity AND actual password match.
                    $existing = ErpAuthUser::query()
                        ->where('staff_id', $staffId)
                        ->whereRaw('LOWER(email)=?', [$email])->first();
                    if ($existing && $existing->active && $existing->isStaff() &&
                        Hash::check($plainPassword, (string)$existing->password)) {
                        return $existing;
                    }
                    return null;
                }
            }, 2);
        } catch (\Throwable $e) {
            // Fail closed with the existing generic invalid-login response.
            return null;
        }
    }
}
