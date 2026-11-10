<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('am_rot_work_complaints')) {
   Schema::create('am_rot_work_complaints',function(Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('job_card_id')->index();
    $t->unsignedBigInteger('rot_session_id')->index();
    $t->unsignedBigInteger('staff_id')->index();
    $t->unsignedBigInteger('reported_by_admin_id');
    $t->string('status',24)->default('REPORTED');
    $t->text('description');
    $t->string('outcome',32)->nullable();
    $t->text('investigation_notes')->nullable();
    $t->text('employee_response')->nullable();
    $t->unsignedBigInteger('reviewed_by_admin_id')->nullable();
    $t->timestamp('reported_at')->index();
    $t->timestamp('reviewed_at')->nullable();
    $t->timestamps();
   });
  }
  if (!Schema::hasTable('am_rot_work_complaint_audits')) {
   Schema::create('am_rot_work_complaint_audits',function(Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('complaint_id')->index();
    $t->unsignedBigInteger('actor_admin_id');
    $t->string('action',40);
    $t->longText('previous_json')->nullable();
    $t->longText('new_json');
    $t->timestamp('created_at');
   });
  }
 }
 public function down():void {
  // Do not delete complaint / disciplinary evidence in automatic rollbacks.
 }
};
