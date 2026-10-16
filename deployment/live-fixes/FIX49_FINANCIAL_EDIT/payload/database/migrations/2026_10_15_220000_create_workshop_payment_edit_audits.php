<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(Schema::hasTable('workshop_payment_edit_audits')) return;
        Schema::create('workshop_payment_edit_audits', function(Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('payment_voucher_id')->index();
            $t->string('voucher_no',80)->default('');
            $t->decimal('previous_amount',14,2);
            $t->decimal('corrected_amount',14,2);
            $t->longText('before_json');
            $t->longText('after_json');
            $t->string('reason',1000);
            $t->string('actor',160);
            $t->timestamp('created_at');
        });
    }
    public function down(): void
    {
        // Financial audit histories are append-only, even on source rollback.
    }
};