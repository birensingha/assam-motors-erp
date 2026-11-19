<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('workshop_expense_categories')) {
            Schema::create('workshop_expense_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 140);
                $table->string('ledger_name', 140)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
        if (!DB::table('workshop_expense_categories')->where('code', 'DONATION')->exists()) {
            DB::table('workshop_expense_categories')->insert([
                'code' => 'DONATION', 'name' => 'Donation',
                'ledger_name' => 'Donation Expense', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {
        // Data retention by design: do not drop a master referred to by posted vouchers.
        // Rollback of application code must not erase category history.
    }
};
