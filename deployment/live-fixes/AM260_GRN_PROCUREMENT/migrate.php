<?php
/**
 * AM260 — STAGING-only, forward-only procurement/GRN schema.
 * This migration creates NEW tables exclusively. Existing invoice, purchase,
 * stock, salary and payment rows are never modified during installation.
 *
 * No down/drop: posted receipts must remain auditable if code is rolled back.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$tables=[
 'am260_purchase_orders'=>function(Blueprint $t){
    $t->bigIncrements('id');
    $t->string('po_no',42)->unique();
    $t->unsignedBigInteger('vendor_id')->index();
    $t->unsignedBigInteger('job_card_id')->nullable()->index();
    $t->date('order_date');
    $t->string('status',24)->default('OPEN');
    $t->text('notes')->nullable();
    $t->string('created_by',128);
    $t->timestamps();
 },
 'am260_po_lines'=>function(Blueprint $t){
    $t->bigIncrements('id');
    $t->unsignedBigInteger('po_id')->index();
    $t->unsignedBigInteger('part_master_id')->index();
    $t->decimal('qty_ordered',13,3);
    $t->decimal('qty_received',13,3)->default(0);
    $t->decimal('expected_unit_cost',14,2)->default(0);
    $t->timestamps();
    $t->unique(['po_id','part_master_id'],'am260_po_part_once');
 },
 'am260_grns'=>function(Blueprint $t){
    $t->bigIncrements('id');
    $t->string('grn_no',42)->unique();
    $t->unsignedBigInteger('vendor_id')->index();
    $t->unsignedBigInteger('po_id')->nullable()->index();
    $t->unsignedBigInteger('job_card_id')->nullable()->index();
    $t->string('challan_no',100);
    $t->date('challan_date');
    $t->date('receipt_date');
    $t->string('status',24)->default('RECEIVED');
    $t->text('notes')->nullable();
    $t->string('created_by',128);
    $t->timestamps();
    $t->unique(['vendor_id','challan_no'],'am260_vendor_challan_once');
 },
 'am260_grn_lines'=>function(Blueprint $t){
    $t->bigIncrements('id');
    $t->unsignedBigInteger('grn_id')->index();
    $t->unsignedBigInteger('po_line_id')->nullable()->index();
    $t->unsignedBigInteger('part_master_id')->index();
    $t->unsignedBigInteger('job_card_id')->nullable()->index();
    $t->decimal('qty_received',13,3);
    $t->decimal('qty_billed',13,3)->default(0);
    $t->decimal('provisional_unit_cost',14,2)->default(0);
    $t->timestamps();
    $t->unique(['grn_id','part_master_id'],'am260_grn_part_once');
 },
 'am260_grn_bills'=>function(Blueprint $t){
    $t->bigIncrements('id');
    $t->unsignedBigInteger('vendor_id')->index();
    $t->string('supplier_invoice',100);
    $t->date('purchase_date');
    $t->string('batch_no',60)->unique();
    $t->string('created_by',128);
    $t->timestamps();
    $t->unique(['vendor_id','supplier_invoice'],'am260_vendor_invoice_once');
 },
 'am260_grn_bill_allocations'=>function(Blueprint $t){
    $t->bigIncrements('id');
    $t->unsignedBigInteger('bill_id')->index();
    $t->unsignedBigInteger('grn_line_id')->index();
    $t->unsignedBigInteger('purchase_id')->unique();
    $t->decimal('matched_qty',13,3);
    $t->timestamps();
 },
];
foreach($tables as $name=>$callback){
    if(!Schema::hasTable($name)){
        Schema::create($name,$callback);
        echo 'AM260_NEW_TABLE_CREATED='.$name."\n";
    }else echo 'AM260_TABLE_EXISTS='.$name."\n";
}
echo "AM260_SCHEMA_READY_NO_EXISTING_FINANCIAL_DATA_CHANGED\n";
