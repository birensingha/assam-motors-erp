<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // AM261_OSL_MASTER_SAC: additive; existing historical OSL lines and
        // invoices are not changed, and unknown classifications stay NULL.
        if (!Schema::hasTable('workshop_osl_master')) {
            throw new RuntimeException('AM261_REQUIRED_OSL_MASTER_MISSING');
        }
        if (!Schema::hasColumn('workshop_osl_master', 'sac_code')) {
            Schema::table('workshop_osl_master', function(Blueprint $table): void {
                $table->string('sac_code', 6)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally preserve the SAC classification history on rollback.
        // Dropping the column would discard legitimate service tax metadata.
    }
};
