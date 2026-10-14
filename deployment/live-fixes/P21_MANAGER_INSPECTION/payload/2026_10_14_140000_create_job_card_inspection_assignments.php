<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P21_MANAGER_INSPECTION_ASSIGNMENT: additive history/assignment mapping.
// Does not touch already-completed inspections or existing technician assignment.
return new class extends Migration {
    public function up(): void {
        if(Schema::hasTable('job_card_inspection_assignments')) return;
        Schema::create('job_card_inspection_assignments', function(Blueprint $t){
            $t->bigIncrements('id');
            $t->unsignedBigInteger('job_card_id')->unique();
            $t->unsignedBigInteger('assigned_staff_id');
            $t->unsignedBigInteger('assigned_by_user_id');
            $t->timestamp('assigned_at');
            $t->unsignedBigInteger('completed_by_staff_id')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index('assigned_staff_id');
        });
    }
    public function down(): void {
        // Preserve historical assignment/completion audit even if code is rolled back.
        // Structural removal must be an explicitly reviewed data migration.
    }
};
