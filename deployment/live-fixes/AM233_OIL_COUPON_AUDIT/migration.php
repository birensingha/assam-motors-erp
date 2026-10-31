<?php
/** AM233 additive only: employee ID and coupon issuer; never change past money. */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('workshop_expenses',function(Blueprint $table){
   if(!Schema::hasColumn('workshop_expenses','staff_id'))
    $table->unsignedBigInteger('staff_id')->nullable()->index();
   if(!Schema::hasColumn('workshop_expenses','coupon_company'))
    $table->string('coupon_company',120)->nullable()->index();
  });
 }
 public function down():void {
  // Audit fields retain history intentionally. No destructive automated rollback.
 }
};