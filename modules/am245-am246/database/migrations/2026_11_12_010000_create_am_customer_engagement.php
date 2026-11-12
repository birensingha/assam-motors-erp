<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('am_customer_feedback', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->string('customer_name',160); $t->string('vehicle',32)->nullable();
            $t->string('job_card_ref',64)->nullable(); $t->unsignedTinyInteger('rating');
            $t->text('comment'); $t->string('source',32)->default('internal');
            $t->text('reply_draft')->nullable(); $t->timestamps();
        });
        Schema::create('am_customer_complaints', function(Blueprint $t) {
            $t->id(); $t->foreignId('feedback_id')->constrained('am_customer_feedback');
            $t->string('status',24)->default('open'); $t->text('resolution_note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('am_customer_message_log',function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->index();
            $t->string('customer_name_snapshot',160); $t->string('phone_last4',4);
            $t->string('type',32); $t->decimal('balance_snapshot',14,2)->nullable();
            $t->text('message'); $t->string('status',64)->default('whatsapp_opened_not_sent');
            $t->unsignedBigInteger('actor_id')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('am_customer_message_log');
        Schema::dropIfExists('am_customer_complaints');
        Schema::dropIfExists('am_customer_feedback');
    }
};
