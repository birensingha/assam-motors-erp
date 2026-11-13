<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM253: additive supplier debit notes and line-item return history.
 * Original Purchase Bills and supplier payment vouchers are never overwritten.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am_purchase_returns')) {
            Schema::create('am_purchase_returns', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->string('return_no', 42)->unique();
                $t->string('request_key', 36)->unique();
                $t->unsignedBigInteger('source_purchase_id')->index();
                $t->string('source_batch_no', 90)->nullable()->index();
                $t->string('purchase_type', 12)->index();
                $t->unsignedBigInteger('vendor_id')->index();
                $t->string('vendor_name', 180);
                $t->date('return_date')->index();
                $t->string('supplier_credit_note', 120)->nullable();
                $t->text('reason');
                $t->unsignedInteger('item_count');
                $t->decimal('total_qty', 16, 3);
                $t->decimal('taxable_amount', 18, 2);
                $t->decimal('gst_amount', 18, 2);
                $t->decimal('pre_round_total', 18, 2);
                $t->decimal('round_off', 18, 2)->default(0);
                $t->decimal('grand_total', 18, 2);
                $t->string('record_status', 15)->default('POSTED')->index();
                $t->string('created_by', 180);
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('voided_at')->nullable();
                $t->string('voided_by', 180)->nullable();
                $t->string('void_reason', 300)->nullable();
            });
        }
        if (!Schema::hasTable('am_purchase_return_lines')) {
            Schema::create('am_purchase_return_lines', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('return_id')->index();
                $t->unsignedBigInteger('purchase_id')->index();
                $t->unsignedBigInteger('part_master_id')->nullable()->index();
                $t->unsignedBigInteger('job_card_id')->nullable()->index();
                $t->string('item_description', 255);
                $t->string('hsn_code', 35)->nullable();
                $t->string('unit', 25)->nullable();
                $t->decimal('qty', 16, 3);
                $t->decimal('unit_cost', 18, 2);
                $t->decimal('discount_percent', 9, 2)->default(0);
                $t->decimal('discount_amount', 18, 2)->default(0);
                $t->decimal('gst_percent', 9, 2)->default(0);
                $t->decimal('taxable_amount', 18, 2)->default(0);
                $t->decimal('gst_amount', 18, 2)->default(0);
                $t->decimal('total_amount', 18, 2)->default(0);
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['return_id','purchase_id'],'am253_one_line_per_source_per_return');
            });
        }
    }
    public function down(): void
    {
        // Preserve historic supplier credit documents even if code is rolled back.
        if (Schema::hasTable('am_purchase_return_lines') &&
            DB::table('am_purchase_return_lines')->exists()) return;
        if (Schema::hasTable('am_purchase_returns') &&
            DB::table('am_purchase_returns')->exists()) return;
        Schema::dropIfExists('am_purchase_return_lines');
        Schema::dropIfExists('am_purchase_returns');
    }
};
