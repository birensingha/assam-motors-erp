<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am206_inspection_master_versions')) {
            Schema::create('am206_inspection_master_versions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('version')->unique();
                $table->string('status',16)->index();
                $table->longText('points_json');
                $table->unsignedBigInteger('published_by_admin_id');
                $table->timestamp('published_at');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('am206_job_inspection_forms')) {
            Schema::create('am206_job_inspection_forms', function (Blueprint $table) {
                $table->unsignedBigInteger('job_card_id')->primary();
                $table->unsignedBigInteger('master_version_id')->nullable();
                $table->string('source',16); // STANDARD or CUSTOM
                $table->longText('points_json');
                $table->string('state',16)->default('DRAFT');
                $table->unsignedBigInteger('assigned_staff_id')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('configured_by_admin_id');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Historical versions and assigned-job checklist snapshots are audit
        // evidence. Never automatically destroy them during rollback.
    }
};
