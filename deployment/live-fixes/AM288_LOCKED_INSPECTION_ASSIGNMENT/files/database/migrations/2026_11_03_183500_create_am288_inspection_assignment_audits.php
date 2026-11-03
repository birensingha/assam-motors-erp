<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am288_inspection_assignment_audits')) {
            Schema::create('am288_inspection_assignment_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_card_id')->index();
                $table->unsignedBigInteger('actor_admin_id');
                $table->string('action', 30);
                $table->longText('payload_json');
                $table->timestamp('created_at');
            });
        }
    }
    public function down(): void
    {
        // Append-only assignment evidence must not be dropped automatically.
    }
};
