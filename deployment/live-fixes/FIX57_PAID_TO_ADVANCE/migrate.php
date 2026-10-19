<?php
/** FIX57: additive register for audited receipt-to-advance and reapplication. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan'))exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
if(!Schema::hasTable('customer_advance_transfers')){
  Schema::create('customer_advance_transfers',function(Blueprint $t){
    $t->bigIncrements('id');
    $t->unsignedBigInteger('receipt_id')->unique();
    $t->unsignedBigInteger('original_invoice_id')->unique();
    $t->unsignedBigInteger('job_card_id')->index();
    $t->unsignedBigInteger('customer_id')->index();
    $t->unsignedBigInteger('credit_note_id')->unique();
    $t->unsignedBigInteger('original_allocation_id');
    $t->unsignedBigInteger('original_receipt_ledger_id');
    $t->decimal('amount',14,2);
    $t->decimal('remaining_amount',14,2);
    $t->string('status',20)->default('available');
    $t->unsignedBigInteger('posted_by')->nullable();
    $t->text('reason');
    $t->date('credit_date');
    $t->timestamp('created_at')->nullable();
    $t->timestamp('updated_at')->nullable();
  });
  echo "FIX57_ADVANCE_TRANSFERS_TABLE_CREATED\n";
}
if(!Schema::hasTable('customer_advance_applications')){
  Schema::create('customer_advance_applications',function(Blueprint $t){
    $t->bigIncrements('id');
    $t->unsignedBigInteger('advance_transfer_id')->index();
    $t->unsignedBigInteger('invoice_id')->index();
    $t->unsignedBigInteger('allocation_id')->unique();
    $t->decimal('amount',14,2);
    $t->timestamp('created_at')->nullable();
    $t->unique(['advance_transfer_id','invoice_id'],'uq_fix57_advance_invoice');
  });
  echo "FIX57_ADVANCE_APPLICATIONS_TABLE_CREATED\n";
}
foreach(['customer_advance_transfers','customer_advance_applications'] as $table){
  if(!Schema::hasTable($table))throw new RuntimeException('FIX57 missing register '.$table);
}
echo "FIX57_SCHEMA_PASS\n";
