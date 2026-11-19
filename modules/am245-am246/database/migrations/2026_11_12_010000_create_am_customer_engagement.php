<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('am_customer_feedback', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->string('customer_name',160); $t->unsignedBigInteger('vehicle_id')->nullable()->index();
            $t->unsignedBigInteger('job_card_id')->nullable()->index();
            $t->string('vehicle',32)->nullable();
            $t->string('job_card_ref',64)->nullable(); $t->unsignedTinyInteger('rating');
            $t->text('comment'); $t->string('source',32)->default('internal');
            $t->unsignedBigInteger('created_by')->nullable()->index();
            $t->text('reply_draft')->nullable(); $t->timestamps();
        });
        Schema::create('am_customer_complaints', function(Blueprint $t) {
            $t->id(); $t->foreignId('feedback_id')->constrained('am_customer_feedback');
            $t->unique('feedback_id'); // one complaint workflow per feedback; prevents concurrent duplicates
            $t->string('status',24)->default('open'); $t->text('resolution_note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('am_customer_complaint_history',function(Blueprint $t) {
            $t->id();
            $t->foreignId('complaint_id')->constrained('am_customer_complaints');
            $t->string('action',24);
            $t->unsignedBigInteger('actor_id');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['complaint_id','created_at']);
        });

    }
    public function down(): void {
        Schema::dropIfExists('am_customer_complaint_history');
        Schema::dropIfExists('am_customer_complaints');
        Schema::dropIfExists('am_customer_feedback');
    }
};
