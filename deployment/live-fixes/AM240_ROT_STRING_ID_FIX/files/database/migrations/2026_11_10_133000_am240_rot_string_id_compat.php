<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rot_sessions') || !Schema::hasTable('am_rot_work_complaints')) {
            throw new RuntimeException('AM240 prerequisite tables missing.');
        }
        $original = DB::selectOne(
            "SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rot_sessions'
               AND COLUMN_NAME = 'id'"
        );
        $report = DB::selectOne(
            "SELECT DATA_TYPE AS data_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'am_rot_work_complaints'
               AND COLUMN_NAME = 'rot_session_id'"
        );
        if (!$original || !$report) {
            throw new RuntimeException('AM240 session ID column metadata unavailable.');
        }
        $originalType = strtolower((string)$original->data_type);
        $reportType = strtolower((string)$report->data_type);
        if (in_array($originalType, ['char', 'varchar'], true)) {
            if ((int)$original->max_length > 191) {
                throw new RuntimeException('AM240: ROT ID maximum length exceeds 191; migration requires explicit review.');
            }
            if (in_array($reportType, ['char', 'varchar'], true)) {
                return;
            }
            if ((int)DB::table('am_rot_work_complaints')->count() !== 0) {
                throw new RuntimeException('AM240: complaint records exist; stop before schema conversion.');
            }
            // Avoid numeric coercion or loss of UUID/slug identifiers.
            DB::statement("ALTER TABLE am_rot_work_complaints
                           MODIFY COLUMN rot_session_id VARCHAR(191) NOT NULL");
            return;
        }
        if (!in_array($originalType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) {
            throw new RuntimeException('AM240: unsupported ROT identifier database type.');
        }
        // Numeric primary keys still arrive as string route parameters.
    }

    public function down(): void
    {
        // Preserve complaint evidence during application rollback.
    }
};
