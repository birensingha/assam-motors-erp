<?php
/** FIX56 additive Credit Note register. No historic invoice rewritten by migration. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan')) exit(2);
chdir($root); require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=Illuminate\Support\Facades\Schema::class;
if(!$schema::hasTable('invoice_credit_notes')){
    $schema::create('invoice_credit_notes',function(Illuminate\Database\Schema\Blueprint $t){
        $t->bigIncrements('id');
        $t->string('credit_note_no',45)->unique();
        $t->unsignedBigInteger('invoice_id')->unique();
        $t->unsignedBigInteger('job_card_id')->index();
        $t->unsignedBigInteger('customer_id')->nullable();
        $t->date('credit_date');
        $t->decimal('taxable_amount',14,2);
        $t->decimal('gst_amount',14,2);
        $t->decimal('credit_amount',14,2);
        $t->string('status',20)->default('posted');
        $t->text('reason');
        $t->longText('original_snapshot');
        $t->unsignedBigInteger('posted_by')->nullable();
        $t->timestamp('created_at')->nullable();
    });
    echo "FIX56_CREDIT_NOTE_TABLE_CREATED\n";
}else echo "FIX56_CREDIT_NOTE_TABLE_ALREADY_EXISTS\n";
foreach(['invoice_id','credit_note_no','original_snapshot','credit_amount'] as $c){
    if(!$schema::hasColumn('invoice_credit_notes',$c))throw new RuntimeException('Incorrect credit-note schema '.$c);
}
echo "FIX56_SCHEMA_OK\n";
