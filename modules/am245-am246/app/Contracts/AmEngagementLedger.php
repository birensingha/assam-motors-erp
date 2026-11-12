<?php
namespace App\Contracts;
interface AmEngagementLedger {
    /** Returns verified ERP customer data: ['id'=>int,'name'=>string,'phone'=>string] or null. */
    public function customer(int $id): ?array;
    /** Authoritative accounting balance after receipts, credit notes and on-account allocations. */
    public function outstanding(int $id): string;
}
