<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('am_qc_delivery_risk_reviews')) {
            Schema::create('am_qc_delivery_risk_reviews', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->unique();
                $table->string('decision', 16);
                $table->unsignedBigInteger('reviewed_by_staff_id');
                $table->unsignedBigInteger('approved_by_admin_id');
                $table->text('actual_safety_assessment');
                $table->string('signed_customer_ack_ref', 500);
                $table->string('defects_fingerprint',64);
                $table->timestamp('reviewed_at');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('am_qc_delivery_risk_review_audits')) {
            Schema::create('am_qc_delivery_risk_review_audits', function(Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->index();
                $table->unsignedBigInteger('actor_admin_id');
                $table->longText('before_json')->nullable();
                $table->longText('after_json');
                $table->timestamp('created_at');
            });
        }
    }
    public function down(): void {
        // Do not destroy customer safety / release decision audit history.
    }
};
