<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Independent archive; intentionally NO foreign key to deleted job_cards. */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('am193_deleted_job_card_audits')) return;
        Schema::create('am193_deleted_job_card_audits',function(Blueprint $table):void{
            $table->bigIncrements('id');
            $table->unsignedBigInteger('job_card_id')->index();
            $table->string('job_card_number',120)->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name',190);
            $table->text('reason');
            $table->longText('snapshot_json');
            $table->timestamp('created_at')->nullable();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Deliberately keep deletion archives on rollback/migrate:rollback.
        // They are audit records, never safe to erase automatically.
    }
};
