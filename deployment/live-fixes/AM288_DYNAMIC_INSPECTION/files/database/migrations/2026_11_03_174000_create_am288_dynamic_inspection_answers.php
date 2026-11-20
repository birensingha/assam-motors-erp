<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am288_inspection_answers')) {
            Schema::create('am288_inspection_answers', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('job_card_id')->index();
                $t->unsignedSmallInteger('position');
                $t->string('point_key', 40);
                $t->string('point_label', 160);
                $t->string('point_type', 20);
                $t->string('value', 80)->default('');
                $t->string('remarks', 500)->default('');
                $t->boolean('is_defect')->default(false);
                $t->timestamps();
                $t->unique(['job_card_id','point_key'],'am288_one_answer_per_point');
                $t->unique(['job_card_id','position'],'am288_one_position_per_job');
            });
        }
        if (!Schema::hasTable('am288_inspection_events')) {
            Schema::create('am288_inspection_events',function(Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('job_card_id')->index();
                $t->unsignedBigInteger('actor_user_id');
                $t->unsignedBigInteger('actor_staff_id')->nullable();
                $t->string('action', 48);
                $t->longText('payload_json');
                $t->timestamp('created_at');
            });
        }
    }

    public function down(): void
    {
        // Submitted answers and audit events are evidence. Never delete
        // automatically on rollback; archive only through explicit approval.
    }
};
