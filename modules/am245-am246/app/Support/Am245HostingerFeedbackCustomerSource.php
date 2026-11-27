<?php
namespace App\Support;

use App\Contracts\AmFeedbackCustomerSource;
use App\Models\ErpAuthUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Adapter for the verified Assam Motors Hostinger schema (November 2026).
 * NEVER enable unless the deployed installation is confirmed single-company.
 * No invoices, financials or phone numbers are queried by this class.
 */
final class Am245HostingerFeedbackCustomerSource implements AmFeedbackCustomerSource
{
    private function permitted(): bool
    {
        $user = Auth::guard('web')->user();
        return config('am245.verified_single_company_scope', false) === true
            && $user instanceof ErpAuthUser
            && (bool) $user->active
            && strtolower((string) $user->role) === 'admin';
    }

    public function search(string $query, int $limit = 15): array
    {
        if (!$this->permitted()) return [];
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 80) return [];
        $limit = max(1, min($limit, 15));
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
        $result = [];
        // Master-linked Job Cards only. Verify actual vehicle ownership.
        $rows = DB::table('job_cards as j')
            ->join('am_customer_master as c', 'c.id', '=', 'j.customer_id')
            ->join('am_vehicle_master as v', function ($join) {
                $join->on('v.id', '=', 'j.vehicle_id')
                    ->on('v.customer_id', '=', 'c.id');
            })
            ->where(function ($q) use ($needle) {
                $q->where('c.name', 'like', $needle)
                    ->orWhere('v.registration_no', 'like', $needle)
                    ->orWhere('j.job_no', 'like', $needle);
            })
            ->orderByDesc('j.id')->limit($limit)
            ->get(['c.id as customer_id', 'c.name as customer_name',
                'v.id as vehicle_id', 'v.registration_no as vehicle',
                'j.id as job_card_id', 'j.job_no as job_card_ref']);
        foreach ($rows as $row) {
            $result[] = (array) $row;
        }
        return $result;
    }

    public function resolve(int $customerId, ?int $vehicleId, ?int $jobCardId): ?array
    {
        if (!$this->permitted() || $customerId < 1) return null;
        $customer = DB::table('am_customer_master')
            ->where('id', $customerId)->first(['id', 'name']);
        if (!$customer || trim((string) $customer->name) === '') return null;
        if ($jobCardId !== null && $vehicleId === null) return null;

        $vehicle = null;
        if ($vehicleId !== null) {
            if ($vehicleId < 1) return null;
            $vehicle = DB::table('am_vehicle_master')
                ->where('id', $vehicleId)->where('customer_id', $customerId)
                ->first(['id', 'registration_no']);
            if (!$vehicle) return null;
        }
        $job = null;
        if ($jobCardId !== null) {
            if ($jobCardId < 1) return null;
            $job = DB::table('job_cards')->where('id', $jobCardId)
                ->where('customer_id', $customerId)
                ->where('vehicle_id', $vehicleId)
                ->first(['id', 'job_no']);
            if (!$job) return null;
        }
        return [
            'customer_id' => (int) $customer->id,
            'customer_name' => (string) $customer->name,
            'vehicle_id' => $vehicle === null ? null : (int) $vehicle->id,
            'vehicle' => $vehicle === null ? null : (string) $vehicle->registration_no,
            'job_card_id' => $job === null ? null : (int) $job->id,
            'job_card_ref' => $job === null ? null : (string) $job->job_no,
        ];
    }
}
