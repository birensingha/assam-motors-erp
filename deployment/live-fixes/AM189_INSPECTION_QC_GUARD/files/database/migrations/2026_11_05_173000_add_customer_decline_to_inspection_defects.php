<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('am189_inspection_defects')) {
            throw new \RuntimeException('AM189 defects missing; do not migrate');
        }
        $columns = [
            'decline_reason', 'decline_acknowledgement', 'decline_evidence_ref',
            'declined_by_admin_id', 'declined_at',
        ];
        foreach ($columns as $column) {
            if (Schema::hasColumn('am189_inspection_defects', $column)) {
                throw new \RuntimeException('Customer decline schema partially exists: review before migration');
            }
        }
        Schema::table('am189_inspection_defects', function (Blueprint $table) {
            $table->text('decline_reason')->nullable();
            $table->string('decline_acknowledgement', 32)->nullable();
            $table->string('decline_evidence_ref', 500)->nullable();
            $table->unsignedBigInteger('declined_by_admin_id')->nullable();
            $table->timestamp('declined_at')->nullable();
        });
    }

    public function down(): void
    {
        // Preserve historical customer refusal and audit; never drop records automatically.
    }
};
