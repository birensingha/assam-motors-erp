<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {
  if (!Schema::hasTable('am_rot_work_complaints')) {
   throw new \RuntimeException('AM240 complaints must exist before AM246.');
  }
  if (!Schema::hasTable('am_complaint_rework_links')) {
   Schema::create('am_complaint_rework_links',function(Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('complaint_id')->unique();
    $t->unsignedBigInteger('job_card_id')->index();
    $t->string('original_rot_session_id',191);
    $t->unsignedBigInteger('original_staff_id')->index();
    $t->string('rework_rot_session_id',191)->nullable()->unique();
    $t->unsignedBigInteger('assigned_staff_id')->index();
    $t->unsignedBigInteger('assigned_by_admin_id');
    $t->string('status',24)->default('ASSIGNED');
    $t->text('instructions');
    $t->timestamp('assigned_at');
    $t->timestamps();
   });
  }
  if (!Schema::hasTable('am_complaint_rework_audits')) {
   Schema::create('am_complaint_rework_audits',function(Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('complaint_id')->index();
    $t->unsignedBigInteger('rework_link_id')->index();
    $t->unsignedBigInteger('actor_admin_id');
    $t->string('action',40);
    $t->longText('event_json');
    $t->timestamp('created_at');
   });
  }
  if (!Schema::hasTable('am_complaint_staff_notifications')) {
   Schema::create('am_complaint_staff_notifications',function(Blueprint $t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('complaint_id')->index();
    $t->unsignedBigInteger('rework_link_id')->index();
    $t->unsignedBigInteger('recipient_staff_id')->index();
    $t->string('notification_type',32);
    $t->string('title',240);
    $t->text('message');
    $t->timestamp('read_at')->nullable();
    $t->timestamps();
    $t->unique(['rework_link_id','recipient_staff_id','notification_type'],'am246_staff_notice_unique');
   });
  }
 }
 public function down():void {
  // Preserve staff duty evidence and incident history even if the feature is rolled back.
 }
};
