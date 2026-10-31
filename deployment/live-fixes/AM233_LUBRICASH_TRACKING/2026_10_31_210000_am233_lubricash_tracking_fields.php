<?php
/**
 * AM233: employee / coupon identity on workshop Expense and linked payments.
 * Additive and nullable; all historic rows remain unchanged. No backfill.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('workshop_expenses')) {
            Schema::table('workshop_expenses', function(Blueprint $table) {
                if (!Schema::hasColumn('workshop_expenses','staff_id')) {
                    $table->unsignedBigInteger('staff_id')->nullable()->index('am233_expense_staff_idx');
                }
                if (!Schema::hasColumn('workshop_expenses','coupon_company')) {
                    $table->string('coupon_company',140)->nullable();
                }
                if (!Schema::hasColumn('workshop_expenses','coupon_no')) {
                    $table->string('coupon_no',120)->nullable();
                }
            });
        }
        if (Schema::hasTable('workshop_payment_vouchers')) {
            Schema::table('workshop_payment_vouchers', function(Blueprint $table) {
                if (!Schema::hasColumn('workshop_payment_vouchers','staff_id')) {
                    $table->unsignedBigInteger('staff_id')->nullable()->index('am233_payment_staff_idx');
                }
                if (!Schema::hasColumn('workshop_payment_vouchers','request_key')) {
                    $table->uuid('request_key')->nullable()->unique('am233_payment_request_unique');
                }
            });
        }
    }
    public function down(): void {
        // Deliberately NO destructive rollback of voucher identity or audit fields.
        // Rolling back the feature is a source-code operation; retain recorded ids.
    }
};
