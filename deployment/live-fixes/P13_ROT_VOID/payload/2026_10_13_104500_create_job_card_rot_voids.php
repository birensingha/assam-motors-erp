<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void {
        if(Schema::hasTable('job_card_rot_voids')) return;
        Schema::create('job_card_rot_voids',function(Blueprint $table){
            $table->id();
            $table->string('rot_session_id',40)->unique();
            $table->unsignedBigInteger('job_card_id')->index();
            $table->text('reason');
            $table->longText('original_snapshot');
            $table->unsignedBigInteger('actor_id');
            $table->timestamp('created_at');
        });
    }
    public function down():void {
        // Never drop audited void records on a routine application rollback.
        // A data migration needs separate authorization.
    }
};
