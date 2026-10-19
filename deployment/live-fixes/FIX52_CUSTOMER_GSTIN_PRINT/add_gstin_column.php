<?php
/** FIX52: additive nullable customer GSTIN field. */
declare(strict_types=1);
chdir((string)($argv[1]??''));
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=Illuminate\Support\Facades\Schema::class;
if(!$schema::hasTable('am_customer_master'))throw new RuntimeException('Customer master missing');
if(!$schema::hasColumn('am_customer_master','gstin')){
  $schema::table('am_customer_master',function(Illuminate\Database\Schema\Blueprint $table){
    $table->string('gstin',15)->nullable()->after('address');
  });
  echo "FIX52_GSTIN_COLUMN_ADDED\n";
}else{
  echo "FIX52_GSTIN_COLUMN_ALREADY_PRESENT\n";
}
if(!$schema::hasColumn('am_customer_master','gstin'))exit(3);
echo "FIX52_SCHEMA_VERIFIED\n";
