<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additive only. Never derive balances from missing attendance or auto-credit leave.
        Schema::create('hr_leave_entries',function(Blueprint $t){
            $t->id();
            $t->unsignedBigInteger('staff_id')->index();
            $t->date('entry_date')->index();
            $t->string('kind',16);
            $t->decimal('days',8,2); // positive earned, negative used
            $t->string('approval_status',18);
            $t->string('application_status',22);
            $t->text('reason');
            $t->unsignedBigInteger('created_by_staff_id');
            $t->timestamp('created_at');
            $t->index(['staff_id','entry_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('hr_leave_entries'); }
};