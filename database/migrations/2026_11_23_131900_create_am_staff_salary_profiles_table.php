<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Salary Master foundation. No existing staff/attendance/ledger rows changed.
 * Not deployable until checked against real staging schema and existing HR salary tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('am_staff_salary_profiles', function (Blueprint $table): void {
            $table->id();
            // Use the existing staff code as a stable, reviewable mapping key;
            // do not assume the live Staff model PK or any existing salary schema.
            $table->string('staff_code', 64);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('monthly_salary', 14, 2);
            $table->string('payable_days_basis', 20)->default('calendar');
            $table->unsignedTinyInteger('scheduled_work_days')->nullable();
            $table->unsignedSmallInteger('duty_minutes_per_day')->default(480);
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0);
            $table->boolean('paid_weekly_off')->default(false);
            $table->boolean('paid_holiday')->default(false);
            $table->decimal('monthly_allowances', 14, 2)->default(0);
            $table->string('created_by_staff_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['staff_code', 'effective_from'], 'am_salary_profile_staff_start_uq');
            $table->index(['staff_code', 'effective_to'], 'am_salary_profile_staff_end_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('am_staff_salary_profiles');
    }
};
