<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // AM256_EXPENSE_AMOUNT_AUDIT: preserve each old/new monetary correction.
        // No old expense, payment, allocation or staff finance data rewritten.
        if (!Schema::hasTable('workshop_expenses')) {
            throw new RuntimeException('AM256_REQUIRED_EXPENSE_TABLE_MISSING');
        }
        if (Schema::hasTable('am_expense_amount_corrections')) {
            return;
        }
        Schema::create('am_expense_amount_corrections', function(Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('expense_id')->index();
            $table->string('expense_no', 60);
            $table->decimal('old_taxable_amount', 15, 2);
            $table->decimal('new_taxable_amount', 15, 2);
            $table->decimal('old_gst_percent', 6, 2);
            $table->decimal('new_gst_percent', 6, 2);
            $table->decimal('old_gst_amount', 15, 2);
            $table->decimal('new_gst_amount', 15, 2);
            $table->decimal('old_total_amount', 15, 2);
            $table->decimal('new_total_amount', 15, 2);
            $table->string('reason', 300);
            $table->string('changed_by', 180);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Deliberately retain financial audit on rollback.
        // Dropping this table would destroy correction evidence.
    }
};
