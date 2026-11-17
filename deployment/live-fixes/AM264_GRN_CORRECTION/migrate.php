<?php
/**
 * AM264 add-on, strictly additive GRN correction audit.
 * Changes to posted receipts append new inventory movements; original receipt
 * movements, vendor challans, bill allocations and JC issues remain untouched.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if(!Schema::hasTable('am264_grn_line_edits')){
  Schema::create('am264_grn_line_edits',static function(Blueprint $table):void{
    $table->bigIncrements('id');
    $table->string('edit_no',48)->index();
    $table->unsignedBigInteger('grn_id')->index();
    $table->unsignedBigInteger('grn_line_id')->index();
    $table->unsignedBigInteger('part_master_id')->index();
    $table->decimal('old_received_qty',13,3)->default(0);
    $table->decimal('new_received_qty',13,3);
    $table->decimal('qty_delta',13,3);
    $table->decimal('old_provisional_cost',14,2)->default(0);
    $table->decimal('new_provisional_cost',14,2);
    $table->decimal('stock_before',13,3)->nullable();
    $table->decimal('stock_after',13,3)->nullable();
    $table->unsignedBigInteger('movement_id')->nullable();
    $table->string('action',36);
    $table->string('actor',128);
    $table->text('reason');
    $table->timestamp('created_at')->useCurrent();
  });
  echo "AM264_GRN_CORRECTION_AUDIT_TABLE_CREATED\n";
}else echo "AM264_GRN_CORRECTION_AUDIT_TABLE_EXISTS\n";
echo "AM264_ADDITIVE_AUDIT_SCHEMA_READY_NO_EXISTING_STOCK_CHANGED\n";
