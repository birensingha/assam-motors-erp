<?php
namespace App\Support;

use App\Contracts\AmFeedbackInvitationSource;
use App\Models\ErpAuthUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hostinger Job Card, Customer and Vehicle schema adapter.
 * Consent is fail-closed unless a real Customer opt-in timestamp exists.
 * The operator checkbox alone is NOT independent opt-in proof.
 */
final class Am245HostingerFeedbackInvitationSource implements AmFeedbackInvitationSource
{
    public function forJobCard(int $jobCardId): ?array
    {
        $user = Auth::guard('web')->user();
        if ($jobCardId < 1 || config('am245.verified_single_company_scope', false) !== true
            || !$user instanceof ErpAuthUser || !(bool) $user->active
            || strtolower((string) $user->role) !== 'admin') {
            return null;
        }

        $row = DB::table('job_cards as j')
            ->join('am_customer_master as c', 'c.id', '=', 'j.customer_id')
            ->join('am_vehicle_master as v', function ($join) {
                $join->on('v.id', '=', 'j.vehicle_id')
                    ->on('v.customer_id', '=', 'c.id');
            })->where('j.id', $jobCardId)
            ->first(['j.id as job_card_id', 'j.status', 'j.customer_public_status',
                'j.job_no as job_card_ref', 'c.id as customer_id',
                'c.name as customer_name', 'c.phone as phone',
                'v.id as vehicle_id', 'v.registration_no as vehicle']);
        if (!$row) return null;

        $status = strtolower(trim((string) $row->status));
        $public = strtolower(trim((string) $row->customer_public_status));
        $finished = in_array($status, ['closed', 'invoiced'], true)
            || ($status === 'completed' && (
                str_contains($public, 'quality check passed')
                || str_contains($public, 'ready for delivery')
            ));

        // Actual attached source does not establish an opt-in column.
        // Enable only after the deployed customer consent policy is reviewed.
        $consentColumn = config('am245.verified_customer_opt_in_column');
        $consented = false;
        if ($consentColumn === 'whatsapp_opt_in_at'
            && Schema::hasColumn('am_customer_master', $consentColumn)) {
            $consented = DB::table('am_customer_master')
                ->where('id', $row->customer_id)
                ->whereNotNull('whatsapp_opt_in_at')->exists();
        }

        return [
            'customer_id' => (int) $row->customer_id,
            'customer_name' => (string) $row->customer_name,
            'vehicle_id' => (int) $row->vehicle_id,
            'vehicle' => (string) $row->vehicle,
            'job_card_id' => (int) $row->job_card_id,
            'job_card_ref' => (string) $row->job_card_ref,
            'phone' => (string) $row->phone,
            'service_finished' => $finished,
            'messaging_permitted' => $consented,
        ];
    }
}
