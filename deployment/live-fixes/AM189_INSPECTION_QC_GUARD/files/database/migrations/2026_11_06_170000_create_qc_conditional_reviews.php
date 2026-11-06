<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('am_qc_conditional_reviews')) {
            Schema::create('am_qc_conditional_reviews', function(Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->unique();
                $table->string('approved_work_result',24);
                $table->text('approved_work_findings');
                $table->string('assessment',30)->default('HOLD');
                $table->text('risk_assessment')->nullable();
                $table->string('customer_acknowledgement_ref',500)->nullable();
                $table->unsignedBigInteger('actor_admin_id');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('am_qc_conditional_review_audits')) {
            Schema::create('am_qc_conditional_review_audits', function(Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->index();
                $table->unsignedBigInteger('actor_admin_id');
                $table->longText('review_json');
                $table->timestamp('created_at');
            });
        }
    }
    public function down(): void {
        // Safety and historical review records must never be deleted implicitly.
    }
};
