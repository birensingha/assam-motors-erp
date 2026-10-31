<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AM238: bookkeeping-only use of a previous Vendor Debit / On Account balance.
 * Never post this amount to Cash/Bank a second time.
 */
return new class extends Migration {
    public function up(): void {
        if(Schema::hasTable('workshop_vendor_debit_uses')) return;
        Schema::create('workshop_vendor_debit_uses', function(Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('source_payment_voucher_id');
            $t->unsignedBigInteger('settlement_payment_voucher_id');
            $t->unsignedBigInteger('vendor_id');
            $t->decimal('amount',14,2);
            $t->uuid('request_key');
            $t->timestamp('created_at')->nullable();
            $t->unique(['source_payment_voucher_id','settlement_payment_voucher_id'],'am238_debit_source_settlement_unique');
            $t->unique(['request_key','source_payment_voucher_id'],'am238_request_source_unique');
            $t->index(['vendor_id','source_payment_voucher_id'],'am238_vendor_debit_source');
            $t->index('settlement_payment_voucher_id','am238_settlement_lookup');
        });
    }
    public function down(): void {
        // A posted allocation history must not be silently hard-deleted.
        // Explicit audited rollback required before altering financial schema.
        if(Schema::hasTable('workshop_vendor_debit_uses') &&
           \Illuminate\Support\Facades\DB::table('workshop_vendor_debit_uses')->exists())
            throw new \RuntimeException('AM238 debit adjustments exist. Export/reconcile before rollback.');
        Schema::dropIfExists('workshop_vendor_debit_uses');
    }
};
