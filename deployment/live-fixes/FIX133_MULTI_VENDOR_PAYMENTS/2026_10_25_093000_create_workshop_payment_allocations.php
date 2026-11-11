<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // FIX133: new additive allocation table only; historical payments untouched.
        if (Schema::hasTable('workshop_payment_allocations')) {
            return;
        }
        Schema::create('workshop_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_voucher_id');
            $table->string('purchase_ref', 180);
            $table->decimal('amount', 14, 2);
            $table->uuid('request_key');
            $table->timestamps();
            $table->unique(['payment_voucher_id', 'purchase_ref'], 'am133_voucher_ref_uniq');
            $table->unique(['request_key', 'purchase_ref'], 'am133_request_ref_uniq');
            $table->index('purchase_ref', 'am133_purchase_ref_idx');
            $table->index('payment_voucher_id', 'am133_voucher_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('workshop_payment_allocations');
    }
};
