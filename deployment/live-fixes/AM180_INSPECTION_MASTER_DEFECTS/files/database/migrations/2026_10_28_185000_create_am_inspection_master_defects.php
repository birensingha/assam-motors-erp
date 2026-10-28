<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('am_inspection_master_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('version')->unique();
            $table->string('status', 16)->index(); // published or superseded
            $table->longText('points_json');
            $table->string('published_by', 190);
            $table->timestamp('published_at');
            $table->timestamps();
        });
        Schema::create('am_inspection_job_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('job_card_id')->primary();
            $table->unsignedBigInteger('master_version_id')->nullable();
            $table->longText('points_json');
            $table->timestamp('created_at');
        });
        Schema::create('am_inspection_defects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('job_card_id')->index();
            $table->string('point_key', 80);
            $table->string('point_label', 200);
            $table->string('original_answer', 120);
            $table->text('original_observation');
            $table->string('severity', 16)->default('normal');
            $table->string('status', 16)->default('OPEN')->index();
            $table->text('repair_note')->nullable();
            $table->string('repaired_by', 190)->nullable();
            $table->timestamp('repaired_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->string('verified_by', 190)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['job_card_id', 'point_key'],'am_defect_job_point_unique');
        });
        Schema::create('am_inspection_defect_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('defect_id')->index();
            $table->unsignedBigInteger('job_card_id')->index();
            $table->string('action', 32);
            $table->string('actor', 190);
            $table->longText('before_json')->nullable();
            $table->longText('after_json');
            $table->timestamp('created_at');
        });
    }
    public function down(): void {
        // Do not delete inspected vehicle findings, snapshots or audit records
        // implicitly during rollback. Use an explicitly approved data plan.
    }
};
