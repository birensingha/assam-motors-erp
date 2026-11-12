<?php
namespace App\Contracts;
interface AmEngagementLedger {
    /** Returns verified ERP customer data: ['id'=>int,'name'=>string,'phone'=>string] or null. */
    public function customer(int $id): ?array;
    /**
     * Search authorized customer master by name, mobile or vehicle registration.
     * Return at most $limit records containing only id and name.
     * Must enforce tenant/company scope and use parameterized queries.
     * Never return phone numbers or financial details in search results.
     * @return array<int,array{id:int,name:string}>
     */
    public function searchCustomers(string $query, int $limit = 15): array;
    /** Authoritative accounting balance after receipts, credit notes and on-account allocations. */
    public function outstanding(int $id): string;
}
