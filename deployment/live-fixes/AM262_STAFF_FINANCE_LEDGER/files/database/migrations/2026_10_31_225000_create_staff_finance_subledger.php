<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // AM262_STAFF_FINANCE_LEDGER: isolated balanced staff subledger.
        // Existing Expense / Payment / HR salary-impact records are not migrated or reposted.
        Schema::create('workshop_staff_finance_journals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->string('voucher_no', 44)->unique();
            $table->date('entry_date')->index();
            $table->string('entry_type', 36);
            $table->string('salary_month', 7)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('payment_mode', 40)->nullable();
            $table->string('account_name', 120)->nullable();
            $table->string('reference_no', 140)->nullable();
            $table->string('narration', 600);
            $table->uuid('request_key')->unique();
            $table->string('created_by', 120);
            $table->unsignedBigInteger('reversal_of')->nullable()->unique();
            $table->string('reversal_reason', 300)->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'salary_month', 'entry_date'], 'staff_finance_staff_month_idx');
        });
        Schema::create('workshop_staff_finance_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_id');
            $table->unsignedBigInteger('staff_id');
            $table->string('account_code', 36);
            $table->string('account_group', 60);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->timestamps();
            $table->foreign('journal_id', 'staff_finance_line_journal_fk')
                  ->references('id')->on('workshop_staff_finance_journals')
                  ->restrictOnDelete();
            $table->index(['staff_id', 'account_code'], 'staff_finance_staff_acc_idx');
        });
    }

    public function down(): void
    {
        // Do not cascade existing posted accounting records during rollback.
        if (Schema::hasTable('workshop_staff_finance_journals') &&
            \Illuminate\Support\Facades\DB::table('workshop_staff_finance_journals')->exists()) {
            throw new \RuntimeException('Cannot drop posted Staff Ledger accounting history.');
        }
        Schema::dropIfExists('workshop_staff_finance_lines');
        Schema::dropIfExists('workshop_staff_finance_journals');
    }
};
