<?php
/**
 * AM262: append-only audit of GRN physical receipts whose Part Master has
 * no recorded opening stock. The operational zero is PROVISIONAL only.
 * Do not overwrite any prior movements, verified opening or existing tables.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=\Illuminate\Support\Facades\Schema::class;
if(!$schema::hasTable('am262_grn_opening_exceptions')){
    $schema::create('am262_grn_opening_exceptions',static function(\Illuminate\Database\Schema\Blueprint $t):void{
        $t->bigIncrements('id');
        $t->unsignedBigInteger('grn_id')->index();
        $t->unsignedBigInteger('part_master_id')->index();
        $t->decimal('prior_stock_qty',13,3)->nullable();
        $t->decimal('provisional_base_qty',13,3);
        $t->decimal('received_qty',13,3);
        $t->decimal('provisional_after_qty',13,3);
        $t->unsignedBigInteger('prior_movement_id')->nullable();
        $t->decimal('prior_movement_balance',13,3)->nullable();
        $t->string('status',40)->default('PENDING_RECONCILIATION');
        $t->string('acknowledged_by',128);
        $t->timestamps();
        $t->unique(['grn_id','part_master_id'],'am262_one_exception_per_part_grn');
    });
    echo "AM262_OPENING_EXCEPTION_AUDIT_TABLE_CREATED\n";
}else echo "AM262_OPENING_EXCEPTION_AUDIT_TABLE_EXISTS\n";
echo "AM262_SCHEMA_READY_EXISTING_STOCK_UNCHANGED\n";
