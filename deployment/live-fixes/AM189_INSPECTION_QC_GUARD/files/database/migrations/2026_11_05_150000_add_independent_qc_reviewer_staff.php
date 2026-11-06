<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am189_inspection_defects')) {
            throw new \RuntimeException('AM189 defect register missing; stop reviewer migration.');
        }
        if (!Schema::hasColumn('am189_inspection_defects', 'verified_by_staff_id')) {
            Schema::table('am189_inspection_defects', function (Blueprint $table) {
                $table->unsignedBigInteger('verified_by_staff_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Keep historical QC reviewer identity and audits; no destructive rollback.
    }
};
