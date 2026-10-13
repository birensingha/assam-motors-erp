<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('hr_salary_impact_profiles',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('staff_id');$t->char('report_month',7);
            $t->decimal('monthly_salary',12,2);$t->unsignedTinyInteger('scheduled_workdays');
            $t->decimal('target_productive_hours_per_day',5,2)->default(0);
            $t->decimal('estimated_recovery_rate_per_hour',12,2)->nullable();
            $t->unsignedBigInteger('updated_by_staff_id');$t->timestamps();
            $t->unique(['staff_id','report_month'],'hr_salary_month_unique');
        });
    }
    public function down():void { Schema::dropIfExists('hr_salary_impact_profiles'); }
};