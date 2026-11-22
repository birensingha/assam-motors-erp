<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('am_feedback_invitations', function (Blueprint $t) {
            $t->id();
            // One row per Job Card. Re-request rotates the opaque token and
            // invalidates any previous link; a submitted response cannot reopen.
            $t->unsignedBigInteger('job_card_id')->unique();
            $t->unsignedBigInteger('customer_id')->index();
            $t->unsignedBigInteger('vehicle_id');
            $t->string('customer_name_snapshot', 160);
            $t->string('vehicle_snapshot', 32);
            $t->string('job_card_ref_snapshot', 64);
            $t->char('token_hash', 64)->unique(); // NEVER store raw token/phone
            $t->timestamp('expires_at')->index();
            $t->timestamp('whatsapp_opened_at')->nullable();
            $t->unsignedBigInteger('requested_by');
            $t->unsignedInteger('request_count')->default(1);
            $t->foreignId('feedback_id')->nullable()
                ->constrained('am_customer_feedback')->restrictOnDelete();
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('am_feedback_invitations');
    }
};
