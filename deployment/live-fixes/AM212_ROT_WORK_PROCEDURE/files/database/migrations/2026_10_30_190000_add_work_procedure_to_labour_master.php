<?php
/**
 * AM212 - Admin-only internal technical steps for Labour/ROT master.
 * Optional nullable text. Does not touch Job Card, Invoice, ROT Session,
 * or existing historical master values.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('labour_master')) {
            throw new RuntimeException('AM212_STOP_LABOUR_MASTER_MISSING');
        }
        if (!Schema::hasColumn('labour_master', 'work_procedure')) {
            Schema::table('labour_master', function (Blueprint $table) {
                $table->text('work_procedure')->nullable();
            });
        }
    }
    public function down(): void
    {
        // No destructive rollback: recorded workshop technical instructions
        // must remain stored even if the UI is reverted.
    }
};
