<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am189_inspection_defects')) {
            Schema::create('am189_inspection_defects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->index();
                $table->char('point_key', 40);
                $table->string('point_label', 160);
                $table->string('original_condition', 20);
                $table->text('original_observation')->nullable();
                $table->string('severity', 16)->default('normal');
                $table->string('status', 16)->default('OPEN');
                $table->unsignedBigInteger('repair_staff_id')->nullable();
                $table->unsignedBigInteger('repair_admin_id')->nullable();
                $table->text('repair_note')->nullable();
                $table->timestamp('repaired_at')->nullable();
                $table->unsignedBigInteger('verified_by_admin_id')->nullable();
                $table->string('verification_result', 16)->nullable();
                $table->text('verification_note')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->unique(['job_card_id','point_key'], 'am189_job_point_unique');
            });
        }
        if (!Schema::hasTable('am189_inspection_defect_audits')) {
            Schema::create('am189_inspection_defect_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->index();
                $table->unsignedBigInteger('defect_id')->index();
                $table->unsignedBigInteger('actor_admin_id');
                $table->string('action', 30);
                $table->longText('before_json')->nullable();
                $table->longText('after_json');
                $table->timestamp('created_at');
            });
        }
    }

    public function down(): void
    {
        // Audit and safety records are intentionally retained.
        // Only a separately approved archival procedure may remove them.
    }
};
