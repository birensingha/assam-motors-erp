<?php
namespace App\Contracts;

/**
 * Read-only bridge to the ERP's actual Customer, Vehicle and Job Card masters.
 *
 * The deployed ERP must implement this interface with its real relationships,
 * company/tenant scope and permissions. No transaction balances are involved.
 */
interface AmFeedbackCustomerSource
{
    /**
     * Search by customer name, registration number or Job Card number.
     * Each result must represent one valid combination from the real ERP.
     * Include: customer_id, customer_name, vehicle_id|null, vehicle|null,
     * job_card_id|null, job_card_ref|null.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 15): array;

    /**
     * Return canonical context ONLY if vehicle and job card, where supplied,
     * really belong to the requested customer and current company scope.
     * Return null otherwise. Never trust browser-submitted labels.
     *
     * @return array{customer_id:int,customer_name:string,vehicle:string|null,job_card_ref:string|null}|null
     */
    public function resolve(int $customerId, ?int $vehicleId, ?int $jobCardId): ?array;
}
