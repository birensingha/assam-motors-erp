<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AM210 customizable seconds. Preserve legacy sample_minutes and history.
 * Null means use existing sample_minutes * 60 until an Admin saves.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_location_settings')) {
            throw new RuntimeException('Existing Staff GPS settings table is required.');
        }
        if (!Schema::hasColumn('staff_location_settings', 'sample_seconds')) {
            Schema::table('staff_location_settings', function (Blueprint $table): void {
                $table->unsignedInteger('sample_seconds')->nullable()->after('sample_minutes');
            });
        }
    }

    public function down(): void
    {
        // Never destroy the Admin-selected reporting interval during rollback.
        // Retain sample_seconds until an explicitly audited data migration.
    }
};
