<?php
/** FIX53: stable Floor Supervisor separate from Job Card deciding technician. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan'))exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=Illuminate\Support\Facades\Schema::class;
if(!$schema::hasTable('job_cards'))throw new RuntimeException('job_cards missing');
if(!$schema::hasColumn('job_cards','floor_supervisor_id')){
    $schema::table('job_cards',function(Illuminate\Database\Schema\Blueprint $table){
        $table->unsignedBigInteger('floor_supervisor_id')->nullable()->after('technician_id');
    });
    echo "FIX53_FLOOR_SUPERVISOR_ID_CREATED\n";
}
if(!$schema::hasColumn('job_cards','floor_supervisor_name')){
    $schema::table('job_cards',function(Illuminate\Database\Schema\Blueprint $table){
        $table->string('floor_supervisor_name',150)->nullable()->after('floor_supervisor_id');
    });
    echo "FIX53_FLOOR_SUPERVISOR_NAME_CREATED\n";
}
foreach(['floor_supervisor_id','floor_supervisor_name'] as $field)
    if(!$schema::hasColumn('job_cards',$field))throw new RuntimeException('Missing '.$field);
echo "FIX53_SCHEMA_OK\n";
