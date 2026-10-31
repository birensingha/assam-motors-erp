<?php
/* AM232 — additive, nullable staff identity for tracking Lubricash coupon usage.
   No modifications to existing expense/payment, totals, tax or ledger records. */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('workshop_expenses', 'staff_id')) {
            Schema::table('workshop_expenses', function (Blueprint $table): void {
                $table->unsignedBigInteger('staff_id')->nullable()->after('job_card_id');
                $table->index('staff_id', 'am232_expenses_staff_idx');
            });
        }
    }

    public function down(): void
    {
        // Intentionally conservative: do not delete linked expense staff records
        // from an accounting register on rollback. Column is safe to retain.
    }
};
