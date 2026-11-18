<?php
/**
 * AM274 — immutable, balanced Vendor Dr / Staff Cr Journal Voucher (F7).
 * Additive, no prior purchases, money movements or staff salary journals changed.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if(!Schema::hasTable('am274_journals')) {
 Schema::create('am274_journals',static function(Blueprint $t):void{
  $t->bigIncrements('id');
  $t->string('voucher_no',56)->nullable()->unique();
  $t->date('journal_date')->index();
  $t->unsignedBigInteger('vendor_id')->index();
  $t->unsignedBigInteger('staff_id')->index();
  $t->string('bill_type',20);
  $t->string('bill_ref',180)->index();
  $t->decimal('amount',15,2);
  $t->string('status',20)->default('ACTIVE');
  $t->text('narration');
  $t->string('request_key',80)->unique();
  $t->string('created_by',160);
  $t->string('reversal_reason',600)->nullable();
  $t->string('reversed_by',160)->nullable();
  $t->timestamp('reversed_at')->nullable();
  $t->timestamps();
 });
 echo "AM274_JOURNAL_HEADER_TABLE_CREATED\n";
}
if(!Schema::hasTable('am274_journal_lines')) {
 Schema::create('am274_journal_lines',static function(Blueprint $t):void{
  $t->bigIncrements('id');
  $t->unsignedBigInteger('journal_id')->index();
  $t->string('account_kind',24);
  $t->unsignedBigInteger('account_id');
  $t->string('account_name',200);
  $t->string('account_group',80);
  $t->decimal('debit',15,2)->default(0);
  $t->decimal('credit',15,2)->default(0);
  $t->string('entry_kind',24)->default('POSTED');
  $t->timestamp('created_at')->useCurrent();
 });
 echo "AM274_JOURNAL_BALANCED_LINES_TABLE_CREATED\n";
}
echo "AM274_ADDITIVE_FINANCE_SCHEMA_READY_NO_OLD_TRANSACTIONS_CHANGED\n";
