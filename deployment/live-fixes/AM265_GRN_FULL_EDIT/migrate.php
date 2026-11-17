<?php
/**
 * AM265 additive immutable audit for corrected GRN header metadata.
 * No existing transaction, stock movement, vendor ledger or Job Card rows
 * modified by migration. Old and new document headers are JSON snapshots.
 */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if(!Schema::hasTable('am265_grn_header_audits')){
    Schema::create('am265_grn_header_audits',static function(Blueprint $t):void{
        $t->bigIncrements('id');
        $t->unsignedBigInteger('grn_id')->index();
        $t->string('grn_no',50);
        $t->string('edit_no',55)->index();
        $t->json('before_json');
        $t->json('after_json');
        $t->string('changed_fields',255);
        $t->text('reason');
        $t->string('actor',128);
        $t->timestamps();
    });
    echo "AM265_GRN_HEADER_AUDIT_CREATED\n";
}else echo "AM265_GRN_HEADER_AUDIT_ALREADY_EXISTS\n";
echo "AM265_SCHEMA_READY_NO_EXISTING_DOCUMENT_OR_STOCK_MUTATED\n";
