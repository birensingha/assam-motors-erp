<?php
namespace App\Contracts;

/**
 * Deployed ERP integration boundary for AM245 Job Card feedback invitations.
 * Implement against the actual scoped Customer, Vehicle and Job Card models.
 * No financial ledger adapter or AM246 authorization is required.
 */
interface AmFeedbackInvitationSource
{
    /**
     * Resolve ONE Job Card in the authenticated operator's company scope.
     * Return null if missing or inaccessible.
     *
     * Fields (all mandatory):
     *  customer_id (positive int), customer_name (string),
     *  vehicle_id (positive int), vehicle (registration number string),
     *  job_card_id (positive int), job_card_ref (string),
     *  phone (verified customer WhatsApp destination),
     *  service_finished (bool), messaging_permitted (bool).
     *
     * service_finished must reflect the REAL ERP's Ready for Delivery,
     * Delivered or other approved finished status, not a browser value.
     * messaging_permitted must be backed by the deployed consent policy.
     * Return canonical associations, not labels from a request payload.
     *
     * @return array<string,mixed>|null
     */
    public function forJobCard(int $jobCardId): ?array;
}
