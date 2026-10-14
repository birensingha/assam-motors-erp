<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P34_ROT_BILLING_CONTROL: track charge inclusion separately from technician time. */
return new class extends Migration {
    public function up(): void {
        if(Schema::hasTable('job_card_labour')&&!Schema::hasColumn('job_card_labour','billing_included')){
            Schema::table('job_card_labour',function(Blueprint $table){
                $table->boolean('billing_included')->default(true);
                $table->string('billing_reason',1000)->nullable();
            });
        }
    }
    public function down(): void {
        // Do not drop immutable billing policy metadata on rollback.
    }
};
