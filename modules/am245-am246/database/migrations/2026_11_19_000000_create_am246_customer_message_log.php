<?php
// AM246 only. Deliberately NOT part of the AM245 feedback-only rollout.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('am_customer_message_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->index();
            $t->string('customer_name_snapshot',160);
            $t->string('phone_last4',4);
            $t->string('type',32);
            $t->decimal('balance_snapshot',14,2)->nullable();
            $t->text('message');
            $t->string('status',64)->default('whatsapp_opened_not_sent');
            $t->unsignedBigInteger('actor_id')->nullable();
            // Operator attestation only; NOT evidence of customer opt-in.
            $t->timestamp('permission_attested_at')->nullable();
            $t->string('permission_attestation',120)->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('am_customer_message_log');
    }
};
