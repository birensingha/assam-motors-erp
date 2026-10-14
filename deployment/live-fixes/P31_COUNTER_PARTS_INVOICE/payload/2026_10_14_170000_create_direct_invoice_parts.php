<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P31_COUNTER_PARTS_SALE — additive, immutable invoice line snapshots and request keys. */
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasTable('direct_invoice_requests')){
            Schema::create('direct_invoice_requests',function(Blueprint $t){
                $t->bigIncrements('id');
                $t->string('request_key',64)->unique();
                $t->unsignedBigInteger('invoice_id')->nullable()->unique();
                $t->unsignedBigInteger('created_by_user_id');
                $t->string('source_kind',40)->default('COUNTER_PARTS');
                $t->string('narration',500)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if(!Schema::hasTable('direct_invoice_lines')){
            Schema::create('direct_invoice_lines',function(Blueprint $t){
                $t->bigIncrements('id');
                $t->unsignedBigInteger('invoice_id')->index();
                $t->unsignedInteger('part_master_id')->index();
                $t->string('part_code',50);
                $t->string('part_name',255);
                $t->string('hsn_code',20)->nullable();
                $t->string('unit',20)->default('PCS');
                $t->decimal('qty',12,3);
                $t->decimal('rate',12,2);
                $t->decimal('discount_percent',5,2)->default(0);
                $t->decimal('gross_amount',12,2);
                $t->decimal('discount_amount',12,2);
                $t->decimal('taxable_amount',12,2);
                $t->decimal('gst_percent',5,2)->default(0);
                $t->decimal('gst_amount',12,2)->default(0);
                $t->decimal('total_amount',12,2);
                $t->decimal('stock_before',12,3)->nullable();
                $t->decimal('stock_after',12,3)->nullable();
                $t->string('note',500)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }
    public function down(): void {
        // Financial invoices/line snapshots are never dropped implicitly.
    }
};
